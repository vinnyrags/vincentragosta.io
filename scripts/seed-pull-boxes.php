<?php
/**
 * Seed Pull Box Entry — Stripe product + V/VMAX prices.
 *
 * Pull boxes are livestream buy-in tickets, not catalog SKUs. We model them as
 * a single Stripe product `Pull Box Entry` with two recurring-style one-time
 * prices ($1 V tier, $2 VMAX tier), each tagged with metadata.tier so this
 * script and pull-products.php can both recognize them.
 *
 * Idempotent: re-running finds the existing product/prices by metadata.type
 * and metadata.tier and skips creation. Any newly-created or rediscovered
 * price IDs are written into the `pullBoxes` ACF group on shop-settings.
 *
 * Usage:
 *   ddev wp eval-file scripts/seed-pull-boxes.php
 *   wp eval-file scripts/seed-pull-boxes.php --path=/var/www/site/wp --allow-root
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/seed-pull-boxes.php\n";
    exit(1);
}

if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '') {
    echo "Error: STRIPE_SECRET_KEY is not defined in wp-config.\n";
    exit(1);
}

$stripeAutoload = get_stylesheet_directory() . '/vendor/autoload.php';
if (!file_exists($stripeAutoload)) {
    echo "Error: Stripe SDK not found. Run composer install in the child theme.\n";
    exit(1);
}
require_once $stripeAutoload;

$stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);

$PRODUCT_NAME = 'Pull Box Entry';
$TIERS = [
    'v' => [
        'label'      => '$1 V Pulls',
        'unit_amount' => 100,
        'acf_field'  => 'pb_v_price_id',
    ],
    'vmax' => [
        'label'       => '$2 VMAX Pulls',
        'unit_amount' => 200,
        'acf_field'   => 'pb_vmax_price_id',
    ],
];

/**
 * Find an existing Pull Box Entry product by metadata.type. The Stripe Search
 * API doesn't expose `metadata.type` directly, so we paginate through active
 * products and filter client-side. Volume is tiny (one entry per shop) so this
 * cost is fine.
 */
function findPullBoxProduct(\Stripe\StripeClient $stripe): ?\Stripe\Product
{
    $params = ['limit' => 100, 'active' => true];

    while (true) {
        $page = $stripe->products->all($params);

        foreach ($page->data as $product) {
            $type = $product->metadata['type'] ?? '';
            if ($type === 'pull_box') {
                return $product;
            }
        }

        if (!$page->has_more) {
            return null;
        }

        $params['starting_after'] = end($page->data)->id;
    }
}

function findTierPrice(\Stripe\StripeClient $stripe, string $productId, string $tier): ?\Stripe\Price
{
    $params = ['product' => $productId, 'limit' => 100, 'active' => true];

    while (true) {
        $page = $stripe->prices->all($params);

        foreach ($page->data as $price) {
            if (($price->metadata['tier'] ?? '') === $tier) {
                return $price;
            }
        }

        if (!$page->has_more) {
            return null;
        }

        $params['starting_after'] = end($page->data)->id;
    }
}

echo "Looking up Pull Box Entry product on Stripe...\n";

$product = findPullBoxProduct($stripe);

if ($product) {
    echo "  Found existing product: {$product->id}\n";
} else {
    echo "  No existing product — creating Pull Box Entry...\n";
    $product = $stripe->products->create([
        'name'        => $PRODUCT_NAME,
        'description' => 'Buy-in entry for the itzenzo.tv livestream pull box. Each entry pulls a card from the active pool.',
        'metadata'    => [
            'type' => 'pull_box',
        ],
    ]);
    echo "  Created: {$product->id}\n";
}

$priceIdsByTier = [];

foreach ($TIERS as $tier => $config) {
    echo "\nLooking up {$config['label']} price...\n";

    $price = findTierPrice($stripe, $product->id, $tier);

    if ($price) {
        echo "  Found existing price: {$price->id} (\${$price->unit_amount} cents)\n";

        if ((int) $price->unit_amount !== (int) $config['unit_amount']) {
            echo "  WARNING: existing price amount ({$price->unit_amount}c) differs from configured ({$config['unit_amount']}c). Stripe prices are immutable — to change, archive this price and re-run.\n";
        }
    } else {
        echo "  No existing price — creating {$config['label']}...\n";
        $price = $stripe->prices->create([
            'product'     => $product->id,
            'currency'    => 'usd',
            'unit_amount' => $config['unit_amount'],
            'metadata'    => [
                'tier' => $tier,
            ],
        ]);
        echo "  Created: {$price->id}\n";
    }

    $priceIdsByTier[$tier] = $price->id;
}

echo "\nWriting price IDs to shop-settings ACF options...\n";

if (!function_exists('update_field')) {
    echo "Error: ACF update_field() unavailable. Is ACF Pro active?\n";
    exit(1);
}

foreach ($TIERS as $tier => $config) {
    $field = $config['acf_field'];
    $previous = (string) get_field($field, 'option');
    $next     = $priceIdsByTier[$tier];

    if ($previous === $next) {
        echo "  {$field}: unchanged ({$next})\n";
        continue;
    }

    update_field($field, $next, 'option');
    echo "  {$field}: {$previous} -> {$next}\n";
}

echo "\nDone.\n";
echo "  Stripe product: {$product->id}\n";
echo "  V price:        {$priceIdsByTier['v']}\n";
echo "  VMAX price:     {$priceIdsByTier['vmax']}\n";
echo "\n";
echo "Verify in WP admin: itzenzo.tv -> Pull Boxes tab.\n";
