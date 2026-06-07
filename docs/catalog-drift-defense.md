# Catalog Drift Defense

> Extracted from `CLAUDE.md` (2026-06-04) to keep that file under the size limit. Stripe checkout is parked behind `STRIPE_ENABLED` post-Whatnot-pivot, but these layers remain in place for the reversal path.

A Stripe product getting archived (or deleted) while a WP catalog post still references it would silently kill that buyer's cart — Stripe rejects creating a session if any line item references an inactive product. Four layers prevent and recover from this:

1. **Push scripts delete instead of archive** — `Nous/scripts/shop/push-products.js --clean` and `push-cards.js --clean` hard-delete prices+products in test mode and archive in live mode. Mode is auto-detected from the key prefix via `Nous/lib/stripe-mode.cjs`; `STRIPE_DELETE_WHEN_REMOVING=true|false` overrides. Live `--clean` is gated behind `--allow-live-clean` so an accidental run can't archive every active product. Falls back to archive automatically when Stripe rejects a delete.
2. **Real-time webhook auto-cleanup** — Nous's stripe webhook handler subscribes to `product.updated` (active true→false), `product.deleted`, `price.updated`, and `price.deleted`. Each calls `notifyCatalogProductDeactivated()` which POSTs to `/shop/v1/catalog/stripe-product-deactivated` (`CatalogStripeProductDeactivatedEndpoint`). WP sets `stock=0` and clears the stale `stripe_price_id` / `stripe_product_id` meta on every referenced post. Idempotent. **Manual setup**: those four events must be enabled on the Stripe webhook endpoint in the Dashboard.
3. **Pre-flight in `CreateCheckoutEndpoint`** — `StripeService::findFirstInactivePriceId()` runs before stock decrement. Inactive priceId returns a 409 `item_unavailable` naming the offending item and auto-sets stock=0 on it. Saves the stock-decrement-then-restore round-trip and avoids polluting Stripe's incomplete-sessions view.
4. **Friendly catch (backstop)** — if a Stripe rejection slips past pre-flight (race with a dashboard archive, etc.), `unavailableItemResponse()` parses the priceId out of the exception message and returns the same 409 + auto-cleanup.

Manual sweep: `node Nous/scripts/shop/audit-stripe-active.js [--apply]` lists every WP post pointing at an inactive Stripe product. Cron candidate (`TODO.md` HIGH PRIORITY).

## Testing the catalog drift path

WP-side: `tests/Integration/Providers/Shop/CatalogStripeProductDeactivatedEndpointTest.php` (shape + permission only — behavior is verified end-to-end against prod because WorDBless mocks `update_post_meta`/`get_post_meta` in memory and direct `$wpdb` queries against `wp_postmeta` return zero rows in the test environment; this matches the convention used by `QueueResetEndpoint` and other endpoints that touch the DB through raw SQL).

Bot-side: `Nous/tests/catalog-deactivate.test.js` (envelope shape, early-return guards on empty productId, error handling, log emission). The `npm run test:critical` smoke flow has a probe step that POSTs a fake `stripeProductId` to the WP endpoint and asserts 200 + `matched=0` — catches a broken route or auth gate before a livestream relies on the real-time cleanup path.
