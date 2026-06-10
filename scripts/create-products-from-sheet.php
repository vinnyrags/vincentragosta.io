<?php
/**
 * Create brand-new `product` posts in WordPress from a Sheet export
 * (export-new-products.mjs) — Stripe-free sibling of create-cards-from-sheet.php
 * for sealed products (boxes, ETBs, collections). For products added to the
 * Products tab but never created in WP.
 *
 * Deduped by post_title (the product name) so re-runs are safe. Each created
 * product gets price (col B), stock (col D), language (col H), the `category`
 * taxonomy (col C), skip_shipping_at_checkout=1, and a sideloaded featured
 * image (col G). stripe_product_id is left blank.
 *
 * Products with no image are SKIPPED (the Whatnot CSV requires an image and
 * the storefront needs one too) — reported so the operator can add col G.
 *
 * Dry-run by default — prints what WOULD be created. Set APPLY=1 to write.
 *
 * Usage:  NEW_PRODUCTS_JSON=/tmp/new-products.json wp eval-file scripts/create-products-from-sheet.php
 *  apply: NEW_PRODUCTS_JSON=... APPLY=1 wp eval-file scripts/create-products-from-sheet.php
 */

if (!defined('ABSPATH')) {
    echo "Run via WP-CLI: wp eval-file scripts/create-products-from-sheet.php\n";
    exit(1);
}

$jsonPath = getenv('NEW_PRODUCTS_JSON') ?: '/tmp/new-products.json';
if (!file_exists($jsonPath)) {
    echo "Error: products JSON not found at {$jsonPath}\n";
    exit(1);
}
$products = json_decode(file_get_contents($jsonPath), true);
if (!is_array($products)) {
    echo "Error: could not parse {$jsonPath}\n";
    exit(1);
}

$apply = !empty(getenv('APPLY'));
echo ($apply ? 'APPLYING' : 'DRY RUN (no writes — set APPLY=1 to write)') . "\n";
echo 'Source: ' . $jsonPath . ' (' . count($products) . " product rows)\n\n";

$created = 0;
$skipped = 0;
$noImage = [];

foreach ($products as $p) {
    $name = trim($p['name'] ?? '');
    if ($name === '') {
        continue;
    }
    if (productExistsByTitle($name)) {
        $skipped++;
        continue;
    }
    if (empty($p['image'])) {
        $noImage[] = $name;
        continue;
    }

    echo '  + ' . $name . ' | ' . ($p['price'] ?? '?') . ' | stock ' . (int) ($p['stock'] ?? 0) . ' | ' . ($p['category'] ?? '') . "\n";
    if (!$apply) {
        $created++;
        continue;
    }

    $postId = wp_insert_post([
        'post_type'   => 'product',
        'post_title'  => $name,
        'post_status' => 'publish',
    ], true);
    if (is_wp_error($postId)) {
        echo "      ! create failed: {$postId->get_error_message()}\n";
        continue;
    }

    if (($p['price'] ?? '') !== '') {
        update_field('price', $p['price'], $postId);
    }
    update_field('stock_quantity', (int) ($p['stock'] ?? 0), $postId);
    update_field('language', ($p['language'] ?? '') ?: 'ENG', $postId);
    update_field('skip_shipping_at_checkout', true, $postId);

    syncProductCategory($postId, $p['category'] ?? '');
    sideloadFeaturedImage($postId, $p['image'], $name);

    $created++;
}

echo "\n" . ($apply ? 'Created' : 'Would create') . ": {$created} product(s); {$skipped} already present (skipped).\n";
if ($noImage) {
    echo 'Skipped (no image in col G — add an image URL first): ' . count($noImage) . "\n";
    foreach ($noImage as $n) {
        echo "  - {$n}\n";
    }
}
if (!$apply) {
    echo "\nRe-run with APPLY=1 to write. Image sideload makes the apply slower (one download per product).\n";
}

function productExistsByTitle(string $name): bool
{
    $q = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => ['publish', 'draft', 'pending', 'trash'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'title'          => $name,
    ]);
    return !empty($q->posts);
}

function syncProductCategory(int $postId, string $category): void
{
    $category = trim($category);
    if ($category === '') {
        return;
    }
    $name = ucwords(str_replace('-', ' ', $category)); // "pokemon" -> "Pokemon"
    $slug = sanitize_title($category);
    $term = get_term_by('slug', $slug, 'category');
    if (!$term) {
        $result = wp_insert_term($name, 'category', ['slug' => $slug]);
        if (!is_wp_error($result)) {
            wp_set_object_terms($postId, [(int) $result['term_id']], 'category');
        }
    } else {
        wp_set_object_terms($postId, [$term->term_id], 'category');
    }
}

function sideloadFeaturedImage(int $postId, string $imageUrl, string $title): void
{
    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    $attachmentId = media_sideload_image($imageUrl, $postId, $title, 'id');
    if (is_wp_error($attachmentId)) {
        echo "      ! image sideload failed: {$attachmentId->get_error_message()}\n";
        return;
    }
    set_post_thumbnail($postId, $attachmentId);
    update_post_meta($attachmentId, '_source_url', $imageUrl);
}
