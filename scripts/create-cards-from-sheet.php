<?php
/**
 * Create brand-new `card` posts in WordPress from a Sheet export
 * (export-new-cards.mjs) — Stripe-free. For cards added to the Singles tab and
 * enriched but never run through the old Stripe → WP pipeline (parked).
 *
 * Deduped by card_name + card_number (NOT title or Stripe id) so re-runs are
 * safe and same-name cards (e.g. three Gengars at different numbers) don't
 * collapse. Each created card gets the full ACF metadata, a sideloaded featured
 * image, and card_game / card_set taxonomy. stripe_product_id is left blank.
 *
 * Dry-run by default — prints what WOULD be created. Set APPLY=1 to write.
 *
 * Usage:  NEW_CARDS_JSON=/tmp/new-cards.json ddev wp eval-file scripts/create-cards-from-sheet.php
 *  apply: NEW_CARDS_JSON=... APPLY=1 ddev wp eval-file scripts/create-cards-from-sheet.php
 */

if (!defined('ABSPATH')) {
    echo "Run via WP-CLI: wp eval-file scripts/create-cards-from-sheet.php\n";
    exit(1);
}

$jsonPath = getenv('NEW_CARDS_JSON') ?: '/tmp/new-cards.json';
if (!file_exists($jsonPath)) {
    echo "Error: cards JSON not found at {$jsonPath}\n";
    exit(1);
}
$cards = json_decode(file_get_contents($jsonPath), true);
if (!is_array($cards)) {
    echo "Error: could not parse {$jsonPath}\n";
    exit(1);
}

$apply = !empty(getenv('APPLY'));
echo ($apply ? 'APPLYING' : 'DRY RUN (no writes — set APPLY=1 to write)') . "\n";
echo 'Source: ' . $jsonPath . ' (' . count($cards) . " new-card rows)\n\n";

$created = 0;
$skipped = 0;

foreach ($cards as $c) {
    $name = $c['name'] ?? '';
    $number = $c['number'] ?? '';
    if ($name === '') {
        continue;
    }

    if (cardExistsByNameNumber($name, $number)) {
        $skipped++;
        continue;
    }

    $setName = $c['set_name'] ?? '';
    $title = trim($name . ($number !== '' ? " #{$number}" : '') . ($setName !== '' ? " — {$setName}" : ''));
    $lang = $c['language'] ?? '';
    echo '  + ' . $title . ($lang ? " [{$lang}]" : '') . "\n";

    if (!$apply) {
        $created++;
        continue;
    }

    $postId = wp_insert_post([
        'post_type'   => 'card',
        'post_title'  => $title,
        'post_status' => 'publish',
    ], true);
    if (is_wp_error($postId)) {
        echo "      ! create failed: {$postId->get_error_message()}\n";
        continue;
    }

    update_field('card_name', $name, $postId);
    update_field('card_number', $number, $postId);
    update_field('set_name', $setName, $postId);
    update_field('set_code', $c['set_code'] ?? '', $postId);
    update_field('variant', ($c['variant'] ?? '') ?: 'regular', $postId);
    update_field('rarity', $c['rarity'] ?? '', $postId);
    update_field('game', $c['game'] ?? 'pokemon', $postId);
    update_field('release_date', $c['release_date'] ?? '', $postId);
    update_field('artist', $c['artist'] ?? '', $postId);
    update_field('condition', 'near-mint', $postId);
    update_field('is_personal_collection', false, $postId);
    if (($c['language'] ?? '') !== '') {
        update_field('language', $c['language'], $postId);
    }
    if (($c['price'] ?? '') !== '') {
        update_field('price', $c['price'], $postId);
    }
    update_field('stock_quantity', (int) ($c['stock'] ?? 0), $postId);

    if (!empty($c['image'])) {
        sideloadFeaturedImage($postId, $c['image'], $title);
    }
    syncCardTaxonomies($postId, $c['game'] ?? 'pokemon', $setName, $c['set_code'] ?? '');

    $created++;
}

echo "\n" . ($apply ? 'Created' : 'Would create') . ": {$created} card(s); {$skipped} already present (skipped).\n";
if (!$apply) {
    echo "\nRe-run with APPLY=1 to write. Image sideload makes the apply slower (one download per card).\n";
}

function cardExistsByNameNumber(string $name, string $number): bool
{
    $q = new WP_Query([
        'post_type'      => 'card',
        'post_status'    => ['publish', 'draft', 'pending', 'trash'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'card_name', 'value' => $name],
            ['key' => 'card_number', 'value' => $number],
        ],
    ]);
    return !empty($q->posts);
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

function syncCardTaxonomies(int $postId, string $game, string $setName, string $setCode): void
{
    if ($game !== '') {
        $gameSlug = sanitize_title($game);
        $term = get_term_by('slug', $gameSlug, 'card_game');
        if (!$term) {
            $result = wp_insert_term(ucwords(str_replace('-', ' ', $gameSlug)), 'card_game', ['slug' => $gameSlug]);
            if (!is_wp_error($result)) {
                wp_set_object_terms($postId, [(int) $result['term_id']], 'card_game');
            }
        } else {
            wp_set_object_terms($postId, [$term->term_id], 'card_game');
        }
    }
    if ($setName !== '' || $setCode !== '') {
        $slugSource = $setName !== '' ? $setName : $setCode;
        $slug = sanitize_title($slugSource);
        $term = get_term_by('slug', $slug, 'card_set');
        if (!$term) {
            $result = wp_insert_term($setName ?: $setCode, 'card_set', ['slug' => $slug]);
            if (!is_wp_error($result)) {
                wp_set_object_terms($postId, [(int) $result['term_id']], 'card_set');
            }
        } else {
            wp_set_object_terms($postId, [$term->term_id], 'card_set');
        }
    }
}
