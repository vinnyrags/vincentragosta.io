#!/usr/bin/env bash
#
# Release any wp-pending-* slot rows on wp_pull_box_slots — these
# accumulate when a pull-box checkout's Stripe call throws AFTER the
# slot claim. PullBoxCheckoutEndpoint now releases on failure
# automatically (commit 2718230), but this script is a manual safety
# valve for older stuck rows or future races that escape the catch.
#
# Invoked only via `make release-stuck-pull-box-slots` — the Makefile
# target pipes this script over SSH with WP_PATH in the env. Never
# runs on the operator's laptop.

set -euo pipefail

WP_PATH="${WP_PATH:-/var/www/vincentragosta.io/wp}"

STUCK=$(wp db query --path="$WP_PATH" --allow-root \
    "SELECT COUNT(*) FROM wp_pull_box_slots WHERE stripe_session_id LIKE 'wp-pending-%';" \
    --skip-column-names | tr -d '[:space:]')

if [[ "$STUCK" == "0" ]]; then
    echo "No stuck wp-pending-* slots."
    exit 0
fi

echo "Releasing $STUCK stuck slot(s)..."

# Delete the row — that's how PullBoxRepository::releaseClaimsByStripeSession()
# releases pending claims. Setting claim_status='open' would create an
# orphan row with an invalid status (only 'pending' and 'confirmed' are
# valid per the schema), which then breaks the homepage slot grid.
wp db query --path="$WP_PATH" --allow-root \
    "DELETE FROM wp_pull_box_slots WHERE stripe_session_id LIKE 'wp-pending-%';"

# Cache flush is critical — Redis would otherwise serve stale slot
# state to the homepage grid until next natural invalidation.
wp cache flush --path="$WP_PATH" --allow-root

echo "Done. $STUCK slot(s) released and cache flushed."
