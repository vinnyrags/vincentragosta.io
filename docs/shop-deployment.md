# Shop Deployment Guide

Deploying the card shop to staging and production. The shop uses Stripe Checkout for payments. The Nous bot (Discord notifications, role promotion, livestream commands) is now managed separately in the [itzenzoTTV](https://github.com/vinnyrags/itzenzoTTV) repository.

**Current state (2026-04-02):** Production is live with Stripe in **test mode**. The switch to live mode happens when the business officially launches.

## Prerequisites

- Stripe account with API keys (Dashboard → Developers → API keys)
- SSH access to the server (`root@174.138.70.29`)
- Stripe webhook endpoints configured in the Stripe Dashboard

## Architecture

Two separate Stripe webhooks per environment:

| Webhook | Endpoint | Purpose |
|---------|----------|---------|
| **WordPress** | `/wp-json/shop/v1/webhook` | Stock management (decrement on purchase, restore on expiry) |
| **Bot** | `/bot/webhooks/stripe` | Discord order notifications, role promotion, battle/queue tracking (managed in itzenzoTTV repo) |

Both listen for: `checkout.session.completed`, `checkout.session.expired`

Each webhook has its own signing secret (`whsec_...`).

## wp-config-env.php

Each environment needs these constants (in addition to DB, URLs, debug flags):

```php
// Stripe API keys (test mode until business launch, then live mode)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
define('STRIPE_SECRET_KEY', 'sk_test_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');       // WordPress webhook signing secret
```

Bot-specific secrets (`DISCORD_BOT_TOKEN`, `STRIPE_BOT_WEBHOOK_SECRET`, `SHOP_URL`, `SITE_URL`, `LIVESTREAM_SECRET`) now live in the bot's `.env` file at `/opt/nous-bot/.env`. See the itzenzoTTV repo for details.

## Deploying Code

```bash
make release              # merge develop → main, push both to origin
git push production main  # deploy to production
```

The post-receive hook handles: code checkout, composer install, npm ci + build for both themes.

## Syncing Products

**Never push the full database.** Use `pull-products.php` to sync from Stripe:

```bash
ssh root@174.138.70.29
cd /var/www/vincentragosta.io

# Auto-publish new products and clean stale ones
touch scripts/.publish scripts/.clean
wp eval-file scripts/pull-products.php --path=wp --allow-root
rm -f scripts/.publish scripts/.clean
```

Flags:
- `.publish` — auto-publish new products (without it, they're created as drafts)
- `.clean` — delete WordPress products that no longer exist in Stripe

This creates/updates product CPT posts with correct price IDs, stock, images, and ACF fields from Stripe.

The full sync pipeline (Google Sheets → Stripe → WordPress) is now handled via the bot's `/sync` command in Discord, or via the itzenzoTTV repo's scripts directly.

## Switching to Stripe Live Mode

One-way switch. Live products and prices have different IDs than test, so every catalog row's `stripe_price_id` / `stripe_product_id` postmeta must be repopulated. The full checklist:

### 0. Before the switch — pin the test rig

`bin/run-test-suite.mjs` (the `npm run test:critical` entrypoint) layers `Nous/.env.test` on top of `.env`. Add an explicit test key to `.env.test` so the suite stays on test Stripe regardless of what production runs:

```env
# Nous/.env.test
STRIPE_SECRET_KEY=sk_test_…
```

The runner refuses to start with a live key (exit 2). Push scripts (`push-products.js`, `push-cards.js`) refuse `--clean` against live Stripe unless invoked with `--allow-live-clean`. See `Nous/lib/stripe-mode.cjs`.

### 1. Get live API keys

Stripe Dashboard → toggle Live mode → Developers → API keys. Note the publishable + secret key.

### 2. Update keys everywhere

Three files, mirror-image swap from `*_test_*` → `*_live_*`:

```php
// wp-config-env.php (local DDEV, staging, production — three copies)
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_…');
define('STRIPE_SECRET_KEY',      'sk_live_…');
define('STRIPE_WEBHOOK_SECRET',  'whsec_…'); // updated in step 3
```

```env
# /opt/nous-bot/.env (production server)
STRIPE_SECRET_KEY=sk_live_…
STRIPE_BOT_WEBHOOK_SECRET=whsec_…  # updated in step 3
```

```env
# itzenzo.tv server env (used by /thank-you email enrichment)
STRIPE_SECRET_KEY=sk_live_…
```

`STRIPE_BOT_WEBHOOK_SECRET` defined in `wp-config-env.php` is currently unused on the WP side — kept for human reference. The real value lives in `/opt/nous-bot/.env`.

### 3. Create live-mode webhooks

In the Stripe Dashboard (Live mode) → Developers → Webhooks → Add endpoint. Two endpoints, each with its own signing secret:

| Endpoint | URL | Events |
|----------|-----|--------|
| **WordPress** | `https://vincentragosta.io/wp-json/shop/v1/webhook` | `checkout.session.completed`, `checkout.session.expired`, `product.updated`, `product.deleted`, `price.updated`, `price.deleted` |
| **Nous bot** | `https://vincentragosta.io/bot/webhooks/stripe` | `checkout.session.completed`, `checkout.session.expired`, `product.updated`, `product.deleted`, `price.updated`, `price.deleted` |

Copy each new `whsec_…` into the corresponding env (WP `STRIPE_WEBHOOK_SECRET` / Nous `STRIPE_BOT_WEBHOOK_SECRET`).

The four catalog-drift events (`product.updated`, `product.deleted`, `price.updated`, `price.deleted`) drive real-time auto-cleanup of stale catalog references — see CLAUDE.md → "Catalog Drift Defense". Forgetting them disables layer 2 of that defense (pre-flight in `CreateCheckoutEndpoint` still works).

### 4. Restart services

```bash
ssh root@174.138.70.29
systemctl restart nous-bot
pm2 reload itzenzo-tv
# WP picks up the constant on next request — no restart needed, but a cache flush helps
wp cache flush --path=/var/www/vincentragosta.io --allow-root
```

### 5. Reseed the catalog (live-mode IDs)

Stripe products and prices created in test mode do not exist in live mode. Recreate them, then write the new IDs back to WP:

```bash
# On a workstation with the live STRIPE_SECRET_KEY in env / wp-config-env.php
cd Nous

# Push catalog products (Sheets → Stripe). --allow-live-clean is required
# because the script auto-defaults to archive in live mode and the guard
# refuses --clean without explicit opt-in.
node scripts/shop/push-products.js --clean --allow-live-clean
node scripts/shop/push-cards.js     --clean --allow-live-clean

# Seed the Pull Box Entry product + V/VMAX prices
ssh root@174.138.70.29 'cd /var/www/vincentragosta.io && wp eval-file scripts/seed-pull-boxes.php --path=wp --allow-root'

# Pull live IDs back to WP postmeta
ssh root@174.138.70.29
cd /var/www/vincentragosta.io
touch scripts/.publish scripts/.clean
wp eval-file scripts/pull-products.php --path=wp --allow-root
wp eval-file scripts/pull-cards.php    --path=wp --allow-root
rm -f scripts/.publish scripts/.clean
```

### 6. Verify

```bash
# Catalog drift sweep — confirms no WP postmeta still points at archived/test products
node Nous/scripts/shop/audit-stripe-active.js

# Smoke test
# - hit /shop, add to cart, checkout (use a live test card if Stripe Dashboard supports test-in-live, else use a real card and refund)
# - hit /cards/, request-to-see — confirm the card request flows through
# - confirm webhook deliveries in Stripe Dashboard → Developers → Webhooks → Recent deliveries
```

### 7. Cross-mode push protection

After local DDEV's `wp-config-env.php` flips to `sk_live_`, the Makefile guard (`make check-stripe-mode-match`) will block `make push-staging` / `make push-production` if the remote env is still on test (or vice versa) — this prevents a DB push from cross-pollinating live + test catalog IDs. Override only when intentional:

```bash
ALLOW_STRIPE_MODE_MISMATCH=1 make push-staging  # rarely correct
make check-stripe-modes                          # non-destructive verify
```

Once everything is on live, `make push-staging` resumes working normally.

## Bot Service

The Nous bot is deployed from the [itzenzoTTV](https://github.com/vinnyrags/itzenzoTTV) repository to `/opt/nous-bot/` on the same server. See that repo's documentation for service management, deployment, and configuration.

Quick reference:

```bash
systemctl status nous-bot
journalctl -u nous-bot -f
curl https://vincentragosta.io/bot/health
```

## Stripe Test Cards

| Card Number | Result |
|-------------|--------|
| `4242 4242 4242 4242` | Successful payment |
| `4000 0000 0000 3220` | Requires 3D Secure authentication |
| `4000 0000 0000 0002` | Declined |

Use any future expiry date, any 3-digit CVC, and any billing ZIP.

---

## Troubleshooting

### Webhook not firing
- Verify the endpoint URL is correct in Stripe Dashboard
- Check webhook logs: Stripe Dashboard → Developers → Webhooks → select endpoint → Recent deliveries
- Ensure the signing secret matches (WordPress secret in `wp-config-env.php`, bot secret in `/opt/nous-bot/.env`)

### 500 error on checkout endpoint
- Check that `STRIPE_SECRET_KEY` is defined in `wp-config-env.php`
- Check server error logs: `tail -f /var/log/nginx/error.log`
- Check WordPress debug log: `tail -f /var/www/{site}/wp/wp-content/debug.log`

### Products not showing
- Verify the Product CPT is registered: check WordPress admin sidebar for "Products"
- Run `composer dump-autoload` on the server if classes aren't found
- Run `npm run build` on the server if assets are missing
- Check ACF field groups are synced: WP admin → ACF → check for "Sync available" badge
