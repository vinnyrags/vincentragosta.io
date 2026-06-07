#!/usr/bin/env bash
#
# Set stock for a card or product in WordPress + Next.js cache (Stripe-free).
#
# Invoked only via `make update-stock` — the Makefile target pipes
# this script over SSH with STRIPE_ID / WP_ID / STOCK / WP_PATH in
# the env. The script never runs on the operator's laptop.
#
# Stripe retired 2026-06-04 (Whatnot pivot): the old metadata.stock
# write is gone. STRIPE_ID is still accepted, but purely as a lookup
# key — the stripe_product_id postmeta remains a stable join handle
# between the Singles sheet (col S) and WP posts.
#
# Flow:
#   1. Resolve WP_ID (directly, or from STRIPE_ID via postmeta).
#   2. Update WP stock_quantity.
#   3. Revalidate the relevant itzenzo.tv path (/cards for cards,
#      / for sealed products) so the storefront reflects the new
#      stock immediately instead of waiting up to 10s for ISR.
#
# Does NOT touch the Google Sheet. The Sheet is the operator's
# manual source of truth; update it separately so the next
# `make update-card-prices-*-apply` doesn't revert this change.

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
    echo "ABORT: provide WP_ID (or STRIPE_ID as a lookup key)."
    exit 1
fi

# Resolve WP_ID from the stripe_product_id join key if not given directly.
# Both card and product post types use the stripe_product_id meta key.
if [[ -z "$WP_ID" && -n "$STRIPE_ID" ]]; then
    WP_ID=$(wp post list --post_type=card,product --meta_key=stripe_product_id --meta_value="$STRIPE_ID" --field=ID --path="$WP_PATH" --allow-root 2>/dev/null | head -1 || true)
fi

if [[ -z "$WP_ID" ]]; then
    echo "ABORT: could not resolve a WP post."
    echo "  STRIPE_ID: ${STRIPE_ID:-(not given)}"
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
echo "  Stock:        ${CURRENT_STOCK:-?} → $STOCK"
echo ""

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
