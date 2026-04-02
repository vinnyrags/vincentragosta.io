# Shop Deployment Guide

Deploying the card shop and Nous bot to staging and production. The shop uses Stripe Checkout for payments. The Nous bot handles Discord notifications, role promotion, and livestream commands.

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
| **Bot** | `/bot/webhooks/stripe` | Discord order notifications, role promotion, battle/queue tracking |

Both listen for: `checkout.session.completed`, `checkout.session.expired`

Each webhook has its own signing secret (`whsec_...`).

## wp-config-env.php

Each environment needs these constants (in addition to DB, URLs, debug flags):

```php
// Stripe API keys (test mode until business launch, then live mode)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
define('STRIPE_SECRET_KEY', 'sk_test_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');       // WordPress webhook signing secret

// Discord bot
define('DISCORD_BOT_TOKEN', '<token>');

// Bot Stripe webhook (separate from WordPress webhook)
define('STRIPE_BOT_WEBHOOK_SECRET', 'whsec_...');   // Bot webhook signing secret

// Bot config
define('SHOP_URL', 'https://vincentragosta.io/shop');
define('SITE_URL', 'https://vincentragosta.io');
define('LIVESTREAM_SECRET', 'itzenzo-live');
```

## Deploying Code

```bash
make release              # merge develop → main, push both to origin
git push production main  # deploy to production
```

The post-receive hook handles: code checkout, composer install, npm ci + build for both themes, npm ci for bot, and `nous-bot` service restart.

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

## Switching to Stripe Live Mode

When the business officially launches:

1. Get live API keys from Stripe Dashboard (toggle to Live mode)
2. Update `wp-config-env.php`:
   - Replace `pk_test_`/`sk_test_` with `pk_live_`/`sk_live_`
3. Create new webhooks in Stripe (live mode) — same endpoints, new signing secrets:
   - WordPress: `https://vincentragosta.io/wp-json/shop/v1/webhook`
   - Bot: `https://vincentragosta.io/bot/webhooks/stripe`
4. Update `STRIPE_WEBHOOK_SECRET` and `STRIPE_BOT_WEBHOOK_SECRET` in wp-config
5. Re-run `pull-products.php` to sync live mode products

## Bot Service

The Nous bot runs as a systemd service on the same droplet:

```bash
# Check status
systemctl status nous-bot

# View logs
journalctl -u nous-bot -f

# Restart
systemctl restart nous-bot

# Health check
curl https://vincentragosta.io/bot/health
```

**Port 3100:** Both staging and production use port 3100. Only one can run at a time. The staging bot (`nous-bot-staging`) is disabled in production.

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
- Ensure the signing secret in `wp-config-env.php` matches Stripe (WordPress and bot have separate secrets)

### 500 error on checkout endpoint
- Check that `STRIPE_SECRET_KEY` is defined in `wp-config-env.php`
- Check server error logs: `tail -f /var/log/nginx/error.log`
- Check WordPress debug log: `tail -f /var/www/{site}/wp/wp-content/debug.log`

### Products not showing
- Verify the Product CPT is registered: check WordPress admin sidebar for "Products"
- Run `composer dump-autoload` on the server if classes aren't found
- Run `npm run build` on the server if assets are missing
- Check ACF field groups are synced: WP admin → ACF → check for "Sync available" badge

### Bot won't start (EADDRINUSE)
- Another process is using port 3100
- Check: `ss -tlnp | grep 3100`
- Kill the stale process or stop the conflicting service
- Common cause: staging bot still running after a deploy triggered its restart via post-receive hook
