<?php
/**
 * Pull Products from Stripe
 *
 * Syncs Stripe products to WordPress product CPT.
 * Creates new products as drafts, updates existing products in place.
 * Downloads Stripe product images and sets them as featured images.
 *
 * Usage: ddev wp eval-file scripts/pull-products.php
 *        PUBLISH=1 ddev wp eval-file scripts/pull-products.php
 *
 * Remote: wp eval-file scripts/pull-products.php --path=/var/www/site/wp --allow-root
 *         PUBLISH=1 wp eval-file scripts/pull-products.php --path=/var/www/site/wp --allow-root
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/pull-products.php\n";
    exit(1);
}

if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '') {
    echo "Error: STRIPE_SECRET_KEY is not defined in wp-config.\n";
    exit(1);
}

// Check for PUBLISH=1 environment variable
$publish = !empty(getenv('PUBLISH'));

// Load Stripe SDK from child theme vendor
$stripeAutoload = get_stylesheet_directory() . '/vendor/autoload.php';
if (!file_exists($stripeAutoload)) {
    echo "Error: Stripe SDK not found. Run composer install in the child theme.\n";
    exit(1);
}
require_once $stripeAutoload;

$stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);

echo "Fetching products from Stripe...\n";

$created = 0;
$updated = 0;
$skipped = 0;

$hasMore = true;
$startingAfter = null;

while ($hasMore) {
    $params = ['limit' => 100, 'active' => true, 'expand' => ['data.default_price']];
    if ($startingAfter) {
        $params['starting_after'] = $startingAfter;
    }

    $products = $stripe->products->all($params);

    echo "  Found " . count($products->data) . " product(s) in this batch\n";

    foreach ($products->data as $stripeProduct) {
        $productId = $stripeProduct->id;
        $name = $stripeProduct->name;
        $description = $stripeProduct->description ?? '';
        $images = $stripeProduct->images ?? [];
        $defaultPrice = $stripeProduct->default_price;
        $metadata = $stripeProduct->metadata ? $stripeProduct->metadata->toArray() : [];
        $category = $metadata['category'] ?? '';
        $stock = $metadata['stock'] ?? '';

        // Get price info
        $priceId = '';
        $displayPrice = '';
        if ($defaultPrice && is_object($defaultPrice)) {
            $priceId = $defaultPrice->id;
            $amount = $defaultPrice->unit_amount;
            $currency = strtoupper($defaultPrice->currency);
            if ($currency === 'USD') {
                $displayPrice = '$' . number_format($amount / 100, 2);
            } else {
                $displayPrice = number_format($amount / 100, 2) . ' ' . $currency;
            }
        }

        if (!$priceId) {
            echo "  Skipping {$name} — no default price set\n";
            $skipped++;
            continue;
        }

        // Check if product already exists in WordPress (by stripe_product_id)
        $existing = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'stripe_product_id',
                    'value' => $productId,
                ],
            ],
        ]);

        if ($existing->have_posts()) {
            // Update existing product
            $postId = $existing->posts[0]->ID;
            update_field('stripe_price_id', $priceId, $postId);
            update_field('price', $displayPrice, $postId);

            // Update image if changed
            if (!empty($images)) {
                maybeUpdateFeaturedImage($postId, $images[0], $name);
            }

            // Sync category from Stripe metadata
            if ($category) {
                maybeSyncCategory($postId, $category);
            }

            // Sync stock from Stripe metadata
            if ($stock !== '') {
                update_field('stock_quantity', (int) $stock, $postId);
            }

            $info = array_filter([$category, $stock !== '' ? "stock:{$stock}" : '']);
            echo "  Updated: {$name} (ID {$postId})" . ($info ? " [" . implode(', ', $info) . "]" : '') . "\n";
            $updated++;
        } else {
            // Create new product as draft (or publish if flag set)
            $postId = wp_insert_post([
                'post_type'    => 'product',
                'post_title'   => $name,
                'post_content' => $description ? "<!-- wp:paragraph -->\n<p>{$description}</p>\n<!-- /wp:paragraph -->" : '',
                'post_status'  => $publish ? 'publish' : 'draft',
            ]);

            if (is_wp_error($postId)) {
                echo "  Error creating {$name}: {$postId->get_error_message()}\n";
                continue;
            }

            update_field('stripe_product_id', $productId, $postId);
            update_field('stripe_price_id', $priceId, $postId);
            update_field('price', $displayPrice, $postId);
            update_field('stock_quantity', $stock !== '' ? (int) $stock : 10, $postId);

            // Download and set featured image
            if (!empty($images)) {
                maybeUpdateFeaturedImage($postId, $images[0], $name);
            }

            // Sync category from Stripe metadata
            if ($category) {
                maybeSyncCategory($postId, $category);
            }

            $status = $publish ? 'published' : 'draft';
            $info = array_filter([$category, $stock !== '' ? "stock:{$stock}" : '']);
            echo "  Created ({$status}): {$name} (ID {$postId})" . ($info ? " [" . implode(', ', $info) . "]" : '') . "\n";
            $created++;
        }

        $startingAfter = $productId;
    }

    $hasMore = $products->has_more;
}

echo "\nDone: {$created} created, {$updated} updated, {$skipped} skipped.\n";

/**
 * Download an image from a URL and set it as the featured image for a post.
 * Skips if the post already has a featured image with the same source URL.
 */
function maybeUpdateFeaturedImage(int $postId, string $imageUrl, string $title): void
{
    // Check if already has a featured image from the same URL
    $currentThumbnailId = get_post_thumbnail_id($postId);
    if ($currentThumbnailId) {
        $currentSource = get_post_meta($currentThumbnailId, '_stripe_source_url', true);
        if ($currentSource === $imageUrl) {
            return; // Same image, skip
        }
    }

    // Require WordPress media functions
    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $attachmentId = media_sideload_image($imageUrl, $postId, $title, 'id');

    if (is_wp_error($attachmentId)) {
        echo "    Warning: Could not download image for {$title}: {$attachmentId->get_error_message()}\n";
        return;
    }

    set_post_thumbnail($postId, $attachmentId);
    update_post_meta($attachmentId, '_stripe_source_url', $imageUrl);
}

/**
 * Assign a WordPress category to a product based on Stripe metadata.
 * Creates the category if it doesn't exist.
 */
function maybeSyncCategory(int $postId, string $categorySlug): void
{
    $slug = sanitize_title($categorySlug);
    $term = get_term_by('slug', $slug, 'category');

    if (!$term) {
        // Create the category with a capitalized name
        $result = wp_insert_term(ucfirst($categorySlug), 'category', ['slug' => $slug]);
        if (is_wp_error($result)) {
            echo "    Warning: Could not create category '{$categorySlug}': {$result->get_error_message()}\n";
            return;
        }
        $termId = $result['term_id'];
    } else {
        $termId = $term->term_id;
    }

    wp_set_object_terms($postId, [$termId], 'category');
}
