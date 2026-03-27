# Shop Deployment Guide

Step-by-step process for deploying the card shop to staging and production. The shop uses Stripe Checkout for payments — each environment needs its own Stripe configuration.

## Prerequisites

- Stripe account with test mode keys (Dashboard → Developers → API keys)
- SSH access to the server (`root@174.138.70.29`)
- Stripe webhook endpoint created in the Stripe Dashboard

## Staging Deployment

### 1. Deploy code to staging

```bash
make deploy-staging
# or: git push production develop
```

### 2. Add Stripe constants to staging wp-config

SSH into the server and create/update the env config:

```bash
ssh root@174.138.70.29
nano /var/www/vincentragosta.dev/wp-config-env.php
```

Add the following (use **test mode** keys for staging):

```php
<?php
// Stripe API keys (test mode)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
define('STRIPE_SECRET_KEY', 'sk_test_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
```

### 3. Create Stripe webhook endpoint

In the Stripe Dashboard (test mode):

1. Go to **Developers → Webhooks → Add destination**
2. Events from: **Your account**
3. Listen to: **checkout.session.completed**
4. Endpoint URL: `https://staging.vincentragosta.io/wp-json/shop/v1/webhook`
5. Destination name: `vincentragosta-shop-checkout-staging`
6. Save and copy the **Signing secret** (`whsec_...`)
7. Update the `STRIPE_WEBHOOK_SECRET` value in the staging `wp-config-env.php`

### 4. Push local database to staging

```bash
make push-staging
```

### 5. Create ACF field group

In the staging WordPress admin (or locally first, then sync via push-staging):

1. Go to **ACF → Field Groups → Add New**
2. Create fields for the `product` post type:
   - `stripe_price_id` (Text, required)
   - `price` (Text — display price, e.g., "$24.99")
   - `condition` (Select: Near Mint, Lightly Played, Moderately Played, Heavily Played, Damaged)
   - `stock_quantity` (Number, default 1, min 0)
   - `gallery_images` (Gallery)
3. Location rule: Post Type → is equal to → Product
4. Publish the field group

### 6. Create shop page and test products

1. Create a new Page titled "Shop" with the **Products** block
2. Create a few test products with:
   - Title, featured image, category (Pokemon/Anime/Mature Content)
   - Fill in ACF fields (use Stripe test Price IDs)
3. Create Stripe test products/prices in the Stripe Dashboard to get Price IDs

### 7. Verify

- [ ] Shop page loads with product grid
- [ ] Sort/filter/search works
- [ ] "Add to Cart" adds items to the drawer
- [ ] Checkout redirects to Stripe test checkout
- [ ] Use Stripe test card `4242 4242 4242 4242` to complete payment
- [ ] Webhook fires and stock decrements
- [ ] Thank-you page clears the cart

---

## Production Deployment

### 1. Switch to live Stripe keys

In the Stripe Dashboard, toggle from **Test mode** to **Live mode** and get your live API keys.

### 2. Create production webhook endpoint

Same process as staging but with the production URL:

1. Endpoint URL: `https://vincentragosta.io/wp-json/shop/v1/webhook`
2. Destination name: `vincentragosta-shop-checkout-production`
3. Copy the signing secret

### 3. Add Stripe constants to production wp-config

```bash
ssh root@174.138.70.29
nano /var/www/vincentragosta.io/wp-config-env.php
```

```php
<?php
// Stripe API keys (live mode)
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_...');
define('STRIPE_SECRET_KEY', 'sk_live_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
```

### 4. Deploy code to production

```bash
make release
# This merges develop → main and pushes both branches
```

Or manually:

```bash
git checkout main
git merge develop
git push origin main
git push production main
```

### 5. Push database to production

```bash
make push-production
```

### 6. Verify

- [ ] Shop page loads
- [ ] Products display correctly
- [ ] Checkout flow completes with a real (small) test purchase
- [ ] Webhook fires and stock updates
- [ ] Refund the test purchase in Stripe Dashboard

---

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
- Check webhook logs in Stripe Dashboard → Developers → Webhooks → select endpoint → Recent deliveries
- Ensure `STRIPE_WEBHOOK_SECRET` matches the signing secret shown in Stripe

### 500 error on checkout endpoint
- Check that `STRIPE_SECRET_KEY` is defined in `wp-config-env.php`
- Check server error logs: `tail -f /var/log/nginx/error.log`
- Check WordPress debug log: `tail -f /var/www/{site}/wp-content/debug.log`

### Products not showing
- Verify the Product CPT is registered: check WordPress admin sidebar for "Products"
- Run `composer dump-autoload` on the server if classes aren't found
- Run `npm run build` on the server if assets are missing
