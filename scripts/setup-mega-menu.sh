#!/usr/bin/env bash
#
# setup-mega-menu.sh — establish the vincentragosta.io Primary mega-menu structure.
#
# The mega-menu CONTENT — submenu children, the dynamic-feed markers
# (nav-dynamic-project / nav-dynamic-post), the Nous Signal hover marker
# (nous-signal-nav-item), and the top-level order (Contact last) — is DATABASE
# content. It does NOT travel with a code deploy, and it is wiped whenever an
# environment's DB is refreshed from one that lacks it (e.g. staging pulled from
# production). Run this AFTER the mega-menu code is deployed to an environment,
# and again any time that environment's DB is re-synced.
#
# Usage:
#   bash scripts/setup-mega-menu.sh staging
#   bash scripts/setup-mega-menu.sh production
#
# Idempotent: removes any existing Primary-menu children first, then rebuilds.

set -euo pipefail

ENV="${1:-}"
case "$ENV" in
  staging)    WP_PATH="/var/www/staging.vincentragosta.io/wp" ;;
  production) WP_PATH="/var/www/vincentragosta.io/wp" ;;
  *) echo "Usage: $0 <staging|production>" >&2; exit 1 ;;
esac

HOST="root@174.138.70.29"

# Primary Navigation menu term + stable parent item IDs
# (shared DB lineage — same IDs on local, staging, and production).
MENU=3
MEET_ME=206; PROJECTS=28; FRAMEWORK=74; NOUS=433; CONTACT=34

echo "Applying mega-menu structure to ${ENV} (${WP_PATH})…"

ssh "$HOST" \
  "WP_PATH='$WP_PATH' MENU='$MENU' MEET_ME='$MEET_ME' PROJECTS='$PROJECTS' \
   FRAMEWORK='$FRAMEWORK' NOUS='$NOUS' CONTACT='$CONTACT' bash -s" <<'REMOTE'
set -e
# --skip-plugins/--skip-themes keeps wp-cli output clean (some plugins emit PHP
# deprecation notices that corrupt parsed output) and speeds these core-only
# menu operations up. The rendered site is unaffected.
WP="--path=$WP_PATH --allow-root --skip-plugins --skip-themes"
add() { wp menu item add-custom "$MENU" "$1" "$2" --parent-id="$3" $WP --porcelain 2>/dev/null; }

# Flush first so the item list below reads fresh from the DB — a stale object
# cache can otherwise hide existing children and cause a duplicate rebuild.
wp cache flush $WP --quiet 2>/dev/null || true

echo "  → removing existing Primary-menu items (everything except the 5 parents)"
KEEP=" $MEET_ME $PROJECTS $FRAMEWORK $NOUS $CONTACT "
for id in $(wp menu item list "$MENU" --fields=db_id --format=ids $WP 2>/dev/null); do
  case "$KEEP" in
    *" $id "*) ;;                                   # keep top-level parent
    *) wp menu item delete "$id" $WP >/dev/null 2>&1 || true ;;
  esac
done

echo "  → top-level order (Contact last)"
wp post update "$MEET_ME"   --menu_order=1 $WP >/dev/null
wp post update "$PROJECTS"  --menu_order=2 $WP >/dev/null
wp post update "$FRAMEWORK" --menu_order=3 $WP >/dev/null
wp post update "$NOUS"      --menu_order=4 $WP >/dev/null
wp post update "$CONTACT"   --menu_order=5 $WP >/dev/null

echo "  → children"
add "Overview"              "/meet-me/"                         "$MEET_ME"   >/dev/null
add "My Approach"           "/meet-me/#my-approach"             "$MEET_ME"   >/dev/null
add "Career"                "/meet-me/#career"                  "$MEET_ME"   >/dev/null
add "Skills"                "/meet-me/#skills"                  "$MEET_ME"   >/dev/null
add "AI Integration"        "/meet-me/#ai-integration"          "$MEET_ME"   >/dev/null
add "Résumé"                "/meet-me/#resume"                  "$MEET_ME"   >/dev/null
add "Overview"              "/framework/"                       "$FRAMEWORK" >/dev/null
add "Architecture Overview" "/framework/#architecture-overview" "$FRAMEWORK" >/dev/null
add "Tech Stack"            "/framework/#tech-stack"            "$FRAMEWORK" >/dev/null
add "Block Architecture"    "/framework/#block-architecture"    "$FRAMEWORK" >/dev/null
add "Design System"         "/framework/#design-system"         "$FRAMEWORK" >/dev/null
add "Testing"               "/framework/#testing"               "$FRAMEWORK" >/dev/null
add "Security"              "/framework/#security"              "$FRAMEWORK" >/dev/null
add "View all"              "/projects/"                        "$PROJECTS"  >/dev/null
add "View all"              "/nous-signal/"                     "$NOUS"      >/dev/null

# Markers must be set LAST: `wp post update --menu_order` above re-saves the
# parent nav-menu-item posts, which resets their _menu_item_classes back to
# empty. Applying the markers after all other item mutations makes them stick.
echo "  → dynamic-feed + Nous Signal markers"
wp post meta update "$PROJECTS" _menu_item_classes '["nav-dynamic-project"]'                     --format=json $WP >/dev/null
wp post meta update "$NOUS"     _menu_item_classes '["nav-dynamic-post","nous-signal-nav-item"]' --format=json $WP >/dev/null

wp cache flush $WP --quiet 2>/dev/null || true
COUNT=$(wp menu item list "$MENU" --format=count $WP 2>/dev/null)
echo "  ✓ done — $COUNT menu items"
[ "$COUNT" = "20" ] || echo "  ⚠ WARNING: expected 20 items, got $COUNT — menu may be duplicated; re-run to reconcile."
REMOTE

echo "✓ Mega-menu applied to ${ENV}."
