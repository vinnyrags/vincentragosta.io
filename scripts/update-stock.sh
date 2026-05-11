#!/usr/bin/env bash
#
# Atomically set stock for a card or product across Stripe + WordPress
# + Next.js cache.
#
# Invoked only via `make update-stock` — the Makefile target pipes
# this script over SSH with STRIPE_ID / WP_ID / STOCK / WP_PATH in
# the env. The script never runs on the operator's laptop.
#
# Flow:
#   1. Resolve missing input (STRIPE_ID or WP_ID) from postmeta.
#   2. Update Stripe metadata.stock.
#   3. Update WP stock_quantity (only if step 2 succeeded).
#   4. Revalidate the relevant itzenzo.tv path (/cards for cards,
#      / for sealed products) so the storefront reflects the new
#      stock immediately instead of waiting up to 10s for ISR.
#
# Stripe is updated first so a Stripe failure leaves WP untouched
# and the operator can retry cleanly without drift.
#
# Does NOT touch the Google Sheet. The Sheet is the operator's
# manual source of truth; update it separately so the next
# `make sync-cards-*` / `make sync-products` doesn't revert this
# change.

set -euo pipefail

STRIPE_ID="${STRIPE_ID:-}"
WP_ID="${WP_ID:-}"
STOCK="${STOCK:-}"
WP_PATH="${WP_PATH:-/var/www/vincentragosta.io/wp}"

if [[ -z "$STOCK" ]]; then
    echo "ABORT: STOCK is required."
    exit 1
fi

if ! [[ "$STOCK" =~ ^[0-9]+$ ]]; then
    echo "ABORT: STOCK must be a non-negative integer, got: $STOCK"
    exit 1
fi

if [[ -z "$STRIPE_ID" && -z "$WP_ID" ]]; then
    echo "ABORT: provide STRIPE_ID or WP_ID."
    exit 1
fi

# Resolve the missing half from postmeta. Both card and product post
# types use the stripe_product_id meta key.
if [[ -z "$STRIPE_ID" && -n "$WP_ID" ]]; then
    STRIPE_ID=$(wp post meta get "$WP_ID" stripe_product_id --path="$WP_PATH" --allow-root 2>/dev/null || true)
fi
if [[ -z "$WP_ID" && -n "$STRIPE_ID" ]]; then
    WP_ID=$(wp post list --post_type=card,product --meta_key=stripe_product_id --meta_value="$STRIPE_ID" --field=ID --path="$WP_PATH" --allow-root 2>/dev/null | head -1 || true)
fi

if [[ -z "$STRIPE_ID" || -z "$WP_ID" ]]; then
    echo "ABORT: could not resolve both STRIPE_ID and WP_ID."
    echo "  STRIPE_ID: ${STRIPE_ID:-(missing)}"
    echo "  WP_ID:     ${WP_ID:-(missing)}"
    exit 1
fi

# Best-effort title + post_type for the summary and revalidate-path
# mapping. POST_TYPE is also load-bearing — it determines which
# itzenzo.tv path to revalidate.
POST_TYPE=$(wp post get "$WP_ID" --field=post_type --path="$WP_PATH" --allow-root 2>/dev/null || echo "")
TITLE=$(wp post get "$WP_ID" --field=post_title --path="$WP_PATH" --allow-root 2>/dev/null || echo "")
CURRENT_STOCK=$(wp post meta get "$WP_ID" stock_quantity --path="$WP_PATH" --allow-root 2>/dev/null || echo "")

echo "Updating stock:"
echo "  WP post ID:   $WP_ID ($POST_TYPE)"
echo "  WP title:     $TITLE"
echo "  Stripe ID:    $STRIPE_ID"
echo "  Stock:        ${CURRENT_STOCK:-?} → $STOCK"
echo ""

STRIPE_KEY=$(grep '^STRIPE_SECRET_KEY=' /opt/nous-bot/.env | cut -d= -f2- || true)
if [[ -z "$STRIPE_KEY" ]]; then
    echo "ABORT: could not read STRIPE_SECRET_KEY from /opt/nous-bot/.env"
    exit 1
fi

echo ">> Updating Stripe metadata.stock..."
RESP_FILE=$(mktemp)
HTTP_STATUS=$(curl -s -o "$RESP_FILE" -w '%{http_code}' \
    -X POST "https://api.stripe.com/v1/products/$STRIPE_ID" \
    -u "$STRIPE_KEY:" \
    -d "metadata[stock]=$STOCK")
if [[ "$HTTP_STATUS" != "200" ]]; then
    echo "   Stripe responded $HTTP_STATUS:"
    cat "$RESP_FILE"
    rm -f "$RESP_FILE"
    exit 1
fi
rm -f "$RESP_FILE"
echo "   ✓ Stripe metadata.stock = $STOCK"

echo ">> Updating WP stock_quantity..."
wp post meta update "$WP_ID" stock_quantity "$STOCK" --path="$WP_PATH" --allow-root >/dev/null
echo "   ✓ WP stock_quantity = $STOCK"

# card → /cards (catalog page renders the grid)
# product → / (homepage renders the product grid)
REVAL_PATH="/"
if [[ "$POST_TYPE" == "card" ]]; then
    REVAL_PATH="/cards"
fi

REVAL_SECRET=$(grep '^REVALIDATION_SECRET=' /var/www/itzenzo.tv/.env.production 2>/dev/null | cut -d= -f2- || true)
if [[ -n "$REVAL_SECRET" ]]; then
    echo ">> Revalidating itzenzo.tv $REVAL_PATH..."
    curl -s -X POST "https://itzenzo.tv/api/revalidate?secret=$REVAL_SECRET&paths=$REVAL_PATH" >/dev/null || true
    echo "   ✓ Revalidated"
else
    echo ">> Skipping revalidate — REVALIDATION_SECRET not found in /var/www/itzenzo.tv/.env.production"
fi

echo ""
echo "✓ Stock updated."
