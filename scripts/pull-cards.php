<?php
/**
 * Pull Card Singles from Stripe.
 *
 * Syncs Stripe products with metadata.type === "card" into the WordPress
 * `card` CPT. Creates new cards as drafts (or published with PUBLISH=1),
 * updates existing cards in place, downloads images as featured images,
 * and assigns card_game and card_set taxonomies from Stripe metadata.
 *
 * Usage: ddev wp eval-file scripts/pull-cards.php
 *        PUBLISH=1 ddev wp eval-file scripts/pull-cards.php
 *        CLEAN=1 PUBLISH=1 ddev wp eval-file scripts/pull-cards.php
 *
 * Remote: wp eval-file scripts/pull-cards.php --path=/var/www/site/wp --allow-root
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/pull-cards.php\n";
    exit(1);
}

if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '') {
    echo "Error: STRIPE_SECRET_KEY is not defined in wp-config.\n";
    exit(1);
}

$publish = !empty(getenv('PUBLISH')) || file_exists(__DIR__ . '/.publish');
$clean = !empty(getenv('CLEAN')) || file_exists(__DIR__ . '/.clean');

$stripeAutoload = get_stylesheet_directory() . '/vendor/autoload.php';
if (!file_exists($stripeAutoload)) {
    echo "Error: Stripe SDK not found. Run composer install in the child theme.\n";
    exit(1);
}
require_once $stripeAutoload;

$stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);

if ($clean) {
    echo "Cleaning: deleting all existing WordPress cards...\n";
    $existingCards = new WP_Query([
        'post_type'      => 'card',
        'post_status'    => ['publish', 'draft', 'pending', 'trash'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $deleteCount = 0;
    foreach ($existingCards->posts as $postId) {
        $thumbnailId = get_post_thumbnail_id($postId);
        if ($thumbnailId) {
            wp_delete_attachment($thumbnailId, true);
        }
        wp_delete_post($postId, true);
        $deleteCount++;
    }
    echo "  {$deleteCount} card(s) permanently deleted.\n\n";
}

echo "Fetching card products from Stripe...\n";

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

    foreach ($products->data as $stripeProduct) {
        $productId = $stripeProduct->id;
        $metadata = $stripeProduct->metadata ? $stripeProduct->metadata->toArray() : [];
        $startingAfter = $productId;

        // Skip anything that isn't a card
        if (($metadata['type'] ?? '') !== 'card') {
            continue;
        }

        $name = $stripeProduct->name;
        $description = $stripeProduct->description ?? '';
        $images = $stripeProduct->images ?? [];
        $defaultPrice = $stripeProduct->default_price;

        $cardName = $metadata['card_name'] ?? $name;
        $game = $metadata['game'] ?? '';
        $setName = $metadata['set_name'] ?? '';
        $setCode = $metadata['set_code'] ?? '';
        $cardNumber = $metadata['card_number'] ?? '';
        $rarity = $metadata['rarity'] ?? '';
        $variant = $metadata['variant'] ?? '';
        $artist = $metadata['artist'] ?? '';
        // Prefer the new release_date key; fall back to the legacy
        // release_year value for Stripe products that predate the switch.
        $releaseDate = $metadata['release_date'] ?? ($metadata['release_year'] ?? '');
        $stock = $metadata['stock'] ?? '';
        $cost = $metadata['cost'] ?? '';
        $language = $metadata['language'] ?? '';
        $salePriceId = $metadata['sale_price_id'] ?? '';
        $onSale = $salePriceId !== '';

        $priceId = '';
        $displayPrice = '';
        if ($defaultPrice && is_object($defaultPrice)) {
            $priceId = $defaultPrice->id;
            $amount = $defaultPrice->unit_amount;
            $currency = strtoupper($defaultPrice->currency);
            $displayPrice = $currency === 'USD'
                ? '$' . number_format($amount / 100, 2)
                : number_format($amount / 100, 2) . ' ' . $currency;
        }

        if (!$priceId) {
            echo "  Skipping {$name} — no default price set\n";
            $skipped++;
            continue;
        }

        $saleDisplayPrice = '';
        if ($onSale && $salePriceId) {
            try {
                $salePriceObj = $stripe->prices->retrieve($salePriceId);
                $saleAmount = $salePriceObj->unit_amount;
                $saleCurrency = strtoupper($salePriceObj->currency);
                $saleDisplayPrice = $saleCurrency === 'USD'
                    ? '$' . number_format($saleAmount / 100, 2)
                    : number_format($saleAmount / 100, 2) . ' ' . $saleCurrency;
            } catch (\Throwable $e) {
                echo "    Warning: Could not resolve sale price for {$name}: {$e->getMessage()}\n";
            }
        }

        $existing = new WP_Query([
            'post_type'      => 'card',
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
            $postId = $existing->posts[0]->ID;
            // Keep post_title in sync with the Stripe product name. Without
            // this, sheet renames (e.g. fixing a wrong set or card number)
            // propagate to ACF fields and the featured image but never
            // reach the title — leaving alt-text and breadcrumbs stale.
            if ($existing->posts[0]->post_title !== $name) {
                wp_update_post([
                    'ID'         => $postId,
                    'post_title' => $name,
                ]);
            }
            update_field('stripe_price_id', $priceId, $postId);
            update_field('price', $displayPrice, $postId);
            update_field('stock_quantity', $stock !== '' ? (int) $stock : 1, $postId);
            update_field('sale_price', $saleDisplayPrice, $postId);
            update_field('sale_price_id', $salePriceId, $postId);
            if ($cost !== '') {
                update_field('cost', '$' . number_format((float) $cost, 2), $postId);
            }
            if ($language !== '') {
                update_field('language', $language, $postId);
            }
            maybeUpdateCardFields($postId, [
                'card_name'    => $cardName,
                'card_number'  => $cardNumber,
                'set_name'     => $setName,
                'set_code'     => $setCode,
                'game'         => $game,
                'rarity'       => $rarity,
                'variant'      => $variant,
                'artist'       => $artist,
                'release_date' => $releaseDate,
            ]);

            if (!empty($images)) {
                maybeUpdateCardFeaturedImage($postId, $images[0], $name);
            }

            maybeSyncCardTaxonomies($postId, $game, $setName, $setCode);

            $info = array_filter([$setName, $cardNumber, $rarity, $stock !== '' ? "stock:{$stock}" : '', $onSale ? "SALE:{$saleDisplayPrice}" : '']);
            echo "  Updated: {$name} (ID {$postId})" . ($info ? ' [' . implode(', ', $info) . ']' : '') . "\n";
            $updated++;
        } else {
            $postId = wp_insert_post([
                'post_type'    => 'card',
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
            update_field('stock_quantity', $stock !== '' ? (int) $stock : 1, $postId);
            update_field('sale_price', $saleDisplayPrice, $postId);
            update_field('sale_price_id', $salePriceId, $postId);
            if ($cost !== '') {
                update_field('cost', '$' . number_format((float) $cost, 2), $postId);
            }
            if ($language !== '') {
                update_field('language', $language, $postId);
            }
            update_field('condition', 'near-mint', $postId);
            maybeUpdateCardFields($postId, [
                'card_name'    => $cardName,
                'card_number'  => $cardNumber,
                'set_name'     => $setName,
                'set_code'     => $setCode,
                'game'         => $game,
                'rarity'       => $rarity,
                'variant'      => $variant ?: 'regular',
                'artist'       => $artist,
                'release_date' => $releaseDate,
            ]);

            if (!empty($images)) {
                maybeUpdateCardFeaturedImage($postId, $images[0], $name);
            }

            maybeSyncCardTaxonomies($postId, $game, $setName, $setCode);

            $status = $publish ? 'published' : 'draft';
            $info = array_filter([$setName, $cardNumber, $rarity, $stock !== '' ? "stock:{$stock}" : '']);
            echo "  Created ({$status}): {$name} (ID {$postId})" . ($info ? ' [' . implode(', ', $info) . ']' : '') . "\n";
            $created++;
        }
    }

    $hasMore = $products->has_more;
}

echo "\nDone: {$created} created, {$updated} updated, {$skipped} skipped.\n";

function maybeUpdateCardFields(int $postId, array $fields): void
{
    foreach ($fields as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }

        update_field($key, $value, $postId);
    }
}

function maybeUpdateCardFeaturedImage(int $postId, string $imageUrl, string $title): void
{
    $currentThumbnailId = get_post_thumbnail_id($postId);
    if ($currentThumbnailId) {
        $currentSource = get_post_meta($currentThumbnailId, '_stripe_source_url', true);
        if ($currentSource === $imageUrl) {
            return;
        }
    }

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

    convertCardAttachmentToJpeg($attachmentId);

    set_post_thumbnail($postId, $attachmentId);
    update_post_meta($attachmentId, '_stripe_source_url', $imageUrl);
}

/**
 * Convert a freshly-sideloaded PNG attachment to JPEG in place.
 *
 * Pokemon TCG card art is opaque (no transparency to preserve) and PNG
 * compresses it poorly. Storing originals as JPEG keeps the source-of-truth
 * file ~5–10x smaller and means sub-sizes are generated from a JPEG source.
 */
function convertCardAttachmentToJpeg(int $attachmentId): void
{
    $file = get_attached_file($attachmentId);
    if (!$file || !file_exists($file)) {
        return;
    }

    if (get_post_mime_type($attachmentId) !== 'image/png') {
        return;
    }

    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return;
    }

    $editor->set_quality(85);
    // Use wp_unique_filename so concurrent conversions can't overwrite
    // each other. Without this, two attachments with the same source
    // basename (e.g. multiple sets each with #152) collide on the
    // output path: the first card's PNG gets unlinked → wp_unique_filename
    // for the next upload sees the slot is free and reuses it →
    // conversion overwrites the existing JPEG → both attachments end
    // up pointing at the same file showing whichever was processed
    // last.
    $dir = dirname($file);
    $jpegBasename = preg_replace('/\.png$/i', '.jpg', basename($file));
    $uniqueBasename = wp_unique_filename($dir, $jpegBasename);
    $jpegPath = $dir . '/' . $uniqueBasename;
    $saved = $editor->save($jpegPath, 'image/jpeg');
    if (is_wp_error($saved) || !is_array($saved)) {
        return;
    }

    @unlink($file);
    update_attached_file($attachmentId, $saved['path']);
    wp_update_post([
        'ID'             => $attachmentId,
        'post_mime_type' => 'image/jpeg',
    ]);

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $meta = wp_generate_attachment_metadata($attachmentId, $saved['path']);
    wp_update_attachment_metadata($attachmentId, $meta);
}

function maybeSyncCardTaxonomies(int $postId, string $game, string $setName, string $setCode): void
{
    if ($game !== '') {
        $gameSlug = sanitize_title($game);
        $term = get_term_by('slug', $gameSlug, 'card_game');
        if (!$term) {
            $result = wp_insert_term(ucwords(str_replace('-', ' ', $gameSlug)), 'card_game', ['slug' => $gameSlug]);
            if (is_wp_error($result)) {
                echo "    Warning: Could not create card_game '{$game}': {$result->get_error_message()}\n";
            } else {
                wp_set_object_terms($postId, [(int) $result['term_id']], 'card_game');
            }
        } else {
            wp_set_object_terms($postId, [$term->term_id], 'card_game');
        }
    }

    if ($setName !== '' || $setCode !== '') {
        // Slug from set NAME (not set code) so two cards sharing a setName
        // always land on the same term — prevents duplicates like `aor` +
        // `xy7` both surfacing as "Ancient Origins" in the dropdown when
        // some sheet rows use internal shorthand and others use the API's
        // set.id. setCode stays available as displayable per-card metadata.
        $slugSource = $setName !== '' ? $setName : $setCode;
        $slug = sanitize_title($slugSource);
        $term = get_term_by('slug', $slug, 'card_set');
        if (!$term) {
            $result = wp_insert_term($setName ?: $setCode, 'card_set', ['slug' => $slug]);
            if (is_wp_error($result)) {
                echo "    Warning: Could not create card_set '{$setName}': {$result->get_error_message()}\n";
            } else {
                wp_set_object_terms($postId, [(int) $result['term_id']], 'card_set');
            }
        } else {
            wp_set_object_terms($postId, [$term->term_id], 'card_set');
        }
    }
}
