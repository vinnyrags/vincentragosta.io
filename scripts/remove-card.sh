#!/usr/bin/env bash
#
# Atomically remove a card from production WP + Stripe.
#
# Invoked only via `make remove-card` — the Makefile target pipes this
# script over SSH with STRIPE_ID / WP_ID / WP_PATH in the env. The
# script never runs on the operator's laptop.
#
# Flow:
#   1. Resolve the missing input (STRIPE_ID or WP_ID) from postmeta.
#   2. Archive the Stripe product (active=false, idempotent).
#   3. If Stripe succeeded, delete the WP post.
#   4. Flush Redis so cached responses see the new state immediately.
#
# Stripe is archived BEFORE WP so a failed Stripe call doesn't leave
# the operator with a deleted-post-but-active-Stripe-product orphan
# that's hard to retry.

set -euo pipefail

STRIPE_ID="${STRIPE_ID:-}"
WP_ID="${WP_ID:-}"
WP_PATH="${WP_PATH:-/var/www/vincentragosta.io/wp}"

if [[ -z "$STRIPE_ID" && -z "$WP_ID" ]]; then
    echo "Usage: make remove-card STRIPE_ID=prod_xxx [WP_ID=123]"
    echo "   or: make remove-card WP_ID=123 [STRIPE_ID=prod_xxx]"
    exit 1
fi

# Resolve the missing half from the other.
if [[ -z "$STRIPE_ID" && -n "$WP_ID" ]]; then
    STRIPE_ID=$(wp post meta get "$WP_ID" stripe_product_id --path="$WP_PATH" --allow-root 2>/dev/null || true)
fi
if [[ -z "$WP_ID" && -n "$STRIPE_ID" ]]; then
    WP_ID=$(wp post list --post_type=card --meta_key=stripe_product_id --meta_value="$STRIPE_ID" --field=ID --path="$WP_PATH" --allow-root 2>/dev/null | head -1 || true)
fi

if [[ -z "$STRIPE_ID" ]]; then
    echo "ABORT: could not resolve a Stripe product ID. Provide STRIPE_ID= explicitly."
    exit 1
fi

# Best-effort title for the summary.
TITLE=""
if [[ -n "$WP_ID" ]]; then
    TITLE=$(wp post get "$WP_ID" --field=post_title --path="$WP_PATH" --allow-root 2>/dev/null || echo "")
fi

echo "About to remove:"
echo "  WP post ID:   ${WP_ID:-(not in WP — Stripe-only orphan)}"
echo "  WP title:     ${TITLE:-(none)}"
echo "  Stripe ID:    $STRIPE_ID"
echo ""

# The live Stripe key lives on Nous's env file on this host. Same
# source the manual orphan-cleanup command pulled from — consistent
# with how the rest of the bot's Stripe operations authenticate.
STRIPE_KEY=$(grep '^STRIPE_SECRET_KEY=' /opt/nous-bot/.env | cut -d= -f2- || true)
if [[ -z "$STRIPE_KEY" ]]; then
    echo "ABORT: could not read STRIPE_SECRET_KEY from /opt/nous-bot/.env"
    exit 1
fi

echo ">> Archiving Stripe product..."
RESP_FILE=$(mktemp)
HTTP_STATUS=$(curl -s -o "$RESP_FILE" -w '%{http_code}' \
    -X POST "https://api.stripe.com/v1/products/$STRIPE_ID" \
    -u "$STRIPE_KEY:" \
    -d active=false)
if [[ "$HTTP_STATUS" != "200" ]]; then
    echo "   Stripe responded $HTTP_STATUS:"
    cat "$RESP_FILE"
    rm -f "$RESP_FILE"
    exit 1
fi
rm -f "$RESP_FILE"
echo "   ✓ Stripe product archived"

if [[ -n "$WP_ID" ]]; then
    echo ">> Deleting WP post..."
    wp post delete "$WP_ID" --force --path="$WP_PATH" --allow-root
    echo "   ✓ WP post deleted"
else
    echo ">> Skipping WP delete — no WP post matched this Stripe ID"
fi

# Belt-and-braces: wp post delete invalidates per-post cache already,
# but flushing all of Redis is cheap and removes any stale fragment.
wp cache flush --path="$WP_PATH" --allow-root >/dev/null
echo ""
echo "✓ Card removed."
