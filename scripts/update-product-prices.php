<?php
/**
 * Update sealed-product price + stock in WordPress directly from the Products
 * sheet tab. Stripe-free sibling of update-card-prices.php for the `product`
 * CPT. The Products tab is the source of truth for both price (col B) and
 * stock (col D); WP — and the Whatnot CSV built from WP inventory — follow it.
 *
 * The Products tab has no Stripe/WP id column, so we join by product NAME →
 * post_title: exact match first (WP_Query handles HTML-entity titles like
 * "JoJo&#8217;s" / "Scarlet &#038; Violet"), then a UNIQUE prefix fallback
 * ("{name} …") for rows whose sheet name is a shortened form of the WP title
 * (e.g. sheet "Pokemon Astral Radiance" → WP "Pokemon Astral Radiance Booster
 * Pack"). Ambiguous (multi-hit) or missing names are reported, never guessed.
 *
 * Updates `price` and `stock_quantity` on existing products — never
 * creates/deletes. Stock 0 is honored (product drops from the CSV but the WP
 * post stays for later restock). Dry-run by default; APPLY=1 writes.
 *
 * Usage:  PRODUCT_PRICES_JSON=/tmp/product-prices.json ddev wp eval-file scripts/update-product-prices.php
 *  apply: PRODUCT_PRICES_JSON=... APPLY=1 ddev wp eval-file scripts/update-product-prices.php
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: wp eval-file scripts/update-product-prices.php\n";
    exit(1);
}

$jsonPath = getenv('PRODUCT_PRICES_JSON') ?: '/tmp/product-prices.json';
if (!file_exists($jsonPath)) {
    echo "Error: product prices JSON not found at {$jsonPath}\n";
    exit(1);
}
$entries = json_decode(file_get_contents($jsonPath), true);
if (!is_array($entries)) {
    echo "Error: could not parse JSON at {$jsonPath}\n";
    exit(1);
}

$apply = !empty(getenv('APPLY'));
echo ($apply ? 'APPLYING' : 'DRY RUN (no writes — set APPLY=1 to write)') . "\n";
echo 'Source: ' . $jsonPath . ' (' . count($entries) . " products)\n\n";

// Preload all product titles for the prefix fallback.
$allQ = new WP_Query([
    'post_type'      => 'product',
    'post_status'    => ['publish', 'draft', 'pending'],
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
]);
$titles = [];
foreach ($allQ->posts as $id) {
    $titles[$id] = get_the_title($id);
}

$findId = function (string $name) use ($titles): int {
    // Exact title match (WP_Query normalizes entity-encoded titles).
    $q = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => ['publish', 'draft', 'pending'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'title'          => $name,
    ]);
    if (!empty($q->posts)) {
        return (int) $q->posts[0];
    }
    // Unique prefix fallback: exactly one WP title starting with "{name} ".
    $hits = [];
    foreach ($titles as $id => $t) {
        if (strpos($t, $name . ' ') === 0) {
            $hits[] = $id;
        }
    }
    return count($hits) === 1 ? (int) $hits[0] : 0;
};

$changed = 0;
$unchanged = 0;
$unmatched = [];

foreach ($entries as $e) {
    $name = $e['name'] ?? '';
    if ($name === '') {
        continue;
    }
    $id = $findId($name);
    if (!$id) {
        $unmatched[] = $name;
        continue;
    }

    $changes = [];

    $price = $e['price'] ?? '';
    if ($price !== '') {
        $cur = (string) get_field('price', $id);
        if ($cur !== $price) {
            $changes[] = "price {$cur} → {$price}";
            if ($apply) {
                update_field('price', $price, $id);
            }
        }
    }

    if (array_key_exists('stock', $e) && $e['stock'] !== null) {
        $cur = (int) get_field('stock_quantity', $id);
        if ($cur !== (int) $e['stock']) {
            $changes[] = "stock {$cur} → {$e['stock']}";
            if ($apply) {
                update_field('stock_quantity', (int) $e['stock'], $id);
            }
        }
    }

    if ($changes) {
        $changed++;
        echo "  #{$id} {$name}: " . implode(', ', $changes) . "\n";
    } else {
        $unchanged++;
    }
}

echo "\n" . ($apply ? 'Applied' : 'Would change') . ": {$changed} product(s), {$unchanged} already current.\n";
if ($unmatched) {
    echo "Unmatched (no WP product — missing or name differs): " . count($unmatched) . "\n";
    foreach ($unmatched as $u) {
        echo "  - {$u}\n";
    }
}
if (!$apply) {
    echo "\nRe-run with APPLY=1 to write.\n";
}
