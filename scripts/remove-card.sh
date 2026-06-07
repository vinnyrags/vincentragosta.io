#!/usr/bin/env bash
#
# Remove a card from production WP (Stripe-free).
#
# Invoked only via `make remove-card` — the Makefile target pipes this
# script over SSH with STRIPE_ID / WP_ID / WP_PATH in the env. The
# script never runs on the operator's laptop.
#
# Stripe retired 2026-06-04 (Whatnot pivot): the old Stripe-archive step
# is gone. STRIPE_ID is still accepted, but purely as a lookup key — the
# stripe_product_id postmeta remains a stable join handle between the
# Singles sheet (col S) and WP posts.
#
# Flow:
#   1. Resolve WP_ID (directly, or from STRIPE_ID via postmeta).
#   2. Delete the WP post.
#   3. Flush Redis so cached responses see the new state immediately.
#   4. Revalidate itzenzo.tv /cards so the storefront drops the card.

set -euo pipefail

STRIPE_ID="${STRIPE_ID:-}"
WP_ID="${WP_ID:-}"
WP_PATH="${WP_PATH:-/var/www/vincentragosta.io/wp}"

if [[ -z "$STRIPE_ID" && -z "$WP_ID" ]]; then
    echo "Usage: make remove-card WP_ID=123"
    echo "   or: make remove-card STRIPE_ID=prod_xxx   (lookup key only — no Stripe call)"
    exit 1
fi

# Resolve WP_ID from the stripe_product_id join key if not given directly.
if [[ -z "$WP_ID" && -n "$STRIPE_ID" ]]; then
    WP_ID=$(wp post list --post_type=card --meta_key=stripe_product_id --meta_value="$STRIPE_ID" --field=ID --path="$WP_PATH" --allow-root 2>/dev/null | head -1 || true)
fi

if [[ -z "$WP_ID" ]]; then
    echo "ABORT: no WP card post matched. Nothing to remove."
    echo "  STRIPE_ID: ${STRIPE_ID:-(not given)}"
    exit 1
fi

TITLE=$(wp post get "$WP_ID" --field=post_title --path="$WP_PATH" --allow-root 2>/dev/null || echo "")

echo "About to remove:"
echo "  WP post ID:   $WP_ID"
echo "  WP title:     ${TITLE:-(none)}"
echo ""

echo ">> Deleting WP post..."
wp post delete "$WP_ID" --force --path="$WP_PATH" --allow-root
echo "   ✓ WP post deleted"

# Belt-and-braces: wp post delete invalidates per-post cache already,
# but flushing all of Redis is cheap and removes any stale fragment.
wp cache flush --path="$WP_PATH" --allow-root >/dev/null

REVAL_SECRET=$(grep '^REVALIDATION_SECRET=' /var/www/itzenzo.tv/.env.production 2>/dev/null | cut -d= -f2- || true)
if [[ -n "$REVAL_SECRET" ]]; then
    echo ">> Revalidating itzenzo.tv /cards..."
    curl -s -X POST "https://itzenzo.tv/api/revalidate?secret=$REVAL_SECRET&paths=/cards" >/dev/null || true
    echo "   ✓ Revalidated"
fi

echo ""
echo "✓ Card removed."
