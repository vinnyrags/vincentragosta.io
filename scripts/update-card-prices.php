<?php
/**
 * Update card price/stock in WordPress directly from the Singles sheet.
 *
 * Stripe-free replacement for the price half of pull-cards.php. Stripe is
 * parked (Whatnot pivot), so the old Sheet → Stripe → WP chain no longer
 * refreshes card `price`/`stock_quantity`. This reads a JSON exported from the
 * sheet by Nous/scripts/shop/export-card-prices.mjs and applies it to existing
 * `card` posts, joined by `stripe_product_id` meta (still stable in both
 * places). It does NOT create cards or touch images/taxonomies — those already
 * exist from the original pipeline; only price, stock, sale fields and (for red
 * "do not sell" rows) post status change.
 *
 * Per card it will:
 *   - set `price` (col D) and `stock_quantity` (col F) when they differ,
 *   - clear `sale_price` / `sale_price_id` (sales are retired — col G is now
 *     the Whatnot Auction Price Override, not a sale price),
 *   - move red "do not sell" rows to `draft` so they drop off the catalog.
 * Un-redding a card does NOT auto-republish it (one-way, to avoid publishing a
 * half-ready draft); republish manually.
 *
 * Dry-run by default — prints what would change. Set APPLY=1 to write.
 *
 * Usage (local):  CARD_PRICES_JSON=/path/card-prices.json ddev wp eval-file scripts/update-card-prices.php
 *         apply:  CARD_PRICES_JSON=... APPLY=1 ddev wp eval-file scripts/update-card-prices.php
 * Remote: CARD_PRICES_JSON=/tmp/card-prices.json wp eval-file scripts/update-card-prices.php --path=/var/www/site/wp --allow-root
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: wp eval-file scripts/update-card-prices.php\n";
    exit(1);
}

$jsonPath = getenv('CARD_PRICES_JSON') ?: '/tmp/card-prices.json';
if (!file_exists($jsonPath)) {
    echo "Error: card prices JSON not found at {$jsonPath}\n";
    echo "Generate it first: node Nous/scripts/shop/export-card-prices.mjs > /tmp/card-prices.json\n";
    exit(1);
}

$entries = json_decode(file_get_contents($jsonPath), true);
if (!is_array($entries)) {
    echo "Error: could not parse JSON at {$jsonPath}\n";
    exit(1);
}

$apply = !empty(getenv('APPLY'));
echo ($apply ? 'APPLYING' : 'DRY RUN (no writes — set APPLY=1 to write)') . "\n";
echo 'Source: ' . $jsonPath . ' (' . count($entries) . " rows with a Stripe product id)\n\n";

$changed = 0;
$unchanged = 0;
$unmatched = 0;
$salesCleared = 0;
$drafted = 0;

foreach ($entries as $e) {
    $pid = $e['stripeProductId'] ?? '';
    if ($pid === '') {
        continue;
    }

    $q = new WP_Query([
        'post_type'      => 'card',
        'post_status'    => ['publish', 'draft', 'pending'],
        'posts_per_page' => 1,
        'no_found_rows'  => true,
        'meta_query'     => [['key' => 'stripe_product_id', 'value' => $pid]],
    ]);

    if (!$q->have_posts()) {
        $unmatched++;
        echo "  [no WP card] {$pid} — " . ($e['name'] ?? '') . "\n";
        continue;
    }

    $post = $q->posts[0];
    $postId = $post->ID;
    $changes = [];

    if (isset($e['price'])) {
        $cur = (string) get_field('price', $postId);
        if ($cur !== $e['price']) {
            $changes[] = "price {$cur} → {$e['price']}";
            if ($apply) {
                update_field('price', $e['price'], $postId);
            }
        }
    }

    if (array_key_exists('stock', $e) && $e['stock'] !== null) {
        $cur = (int) get_field('stock_quantity', $postId);
        if ($cur !== (int) $e['stock']) {
            $changes[] = "stock {$cur} → {$e['stock']}";
            if ($apply) {
                update_field('stock_quantity', (int) $e['stock'], $postId);
            }
        }
    }

    // Retire sale fields — col G is the Whatnot override now, not a sale price.
    $curSale = (string) get_field('sale_price', $postId);
    $curSaleId = (string) get_field('sale_price_id', $postId);
    if ($curSale !== '' || $curSaleId !== '') {
        $changes[] = 'clear sale (' . ($curSale ?: '—') . ' / ' . ($curSaleId ?: '—') . ')';
        $salesCleared++;
        if ($apply) {
            update_field('sale_price', '', $postId);
            update_field('sale_price_id', '', $postId);
        }
    }

    // Red "do not sell" rows → draft (one-way).
    if (!empty($e['doNotSell']) && $post->post_status !== 'draft') {
        $changes[] = "status {$post->post_status} → draft (do not sell)";
        $drafted++;
        if ($apply) {
            wp_update_post(['ID' => $postId, 'post_status' => 'draft']);
        }
    }

    if ($changes) {
        $changed++;
        echo "  #{$postId} {$post->post_title}\n      " . implode("\n      ", $changes) . "\n";
    } else {
        $unchanged++;
    }
}

echo "\n" . ($apply ? 'Applied' : 'Would change') . ": {$changed} card(s), {$unchanged} already current, {$unmatched} sheet row(s) with no WP card.\n";
echo "  sales cleared: {$salesCleared}, drafted as do-not-sell: {$drafted}\n";
if (!$apply) {
    echo "\nRe-run with APPLY=1 to write these changes.\n";
}
