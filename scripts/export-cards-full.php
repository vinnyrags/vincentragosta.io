<?php
/**
 * Full-fidelity export of every `card` post as JSON, for an additive
 * cross-environment card import (see import-cards-from-json.php).
 *
 * Unlike export-inventory-json.php (in-stock catalog slice for the Whatnot
 * CSV), this dumps EVERY card — publish + draft, in- and out-of-stock,
 * personal-collection included — with all ACF meta, the featured image URL,
 * and card_game / card_set term slugs. The importer recreates anything the
 * target environment is missing, keyed by stripe_product_id, without touching
 * existing posts.
 *
 * Usage: wp eval-file scripts/export-cards-full.php --path=/var/www/site/wp --allow-root > /tmp/cards-full.json
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "This script must be run via WP-CLI: wp eval-file scripts/export-cards-full.php\n");
    exit(1);
}

$metaKeys = [
    'stripe_product_id', 'stripe_price_id', 'price', 'sale_price', 'sale_price_id',
    'stock_quantity', 'cost', 'language', 'condition', 'card_name', 'card_number',
    'set_name', 'set_code', 'game', 'rarity', 'variant', 'artist', 'release_date',
    'is_personal_collection',
];

$q = new WP_Query([
    'post_type'      => 'card',
    'post_status'    => ['publish', 'draft', 'pending'],
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
]);

$out = [];
foreach ($q->posts as $id) {
    $post = get_post($id);

    // Raw post_meta (not get_field) so ACF select slugs / stored values
    // round-trip exactly; update_field re-stores them on import.
    $meta = [];
    foreach ($metaKeys as $k) {
        $meta[$k] = get_post_meta($id, $k, true);
    }

    $out[] = [
        'stripe_product_id' => (string) $meta['stripe_product_id'],
        'title'             => $post->post_title,
        'content'           => $post->post_content,
        'status'            => $post->post_status,
        'slug'              => $post->post_name,
        'date'              => $post->post_date,
        'image'             => get_the_post_thumbnail_url($id, 'full') ?: '',
        'meta'              => $meta,
        'card_game'         => wp_get_object_terms($id, 'card_game', ['fields' => 'slugs']),
        'card_set'          => wp_get_object_terms($id, 'card_set', ['fields' => 'slugs']),
    ];
}

echo json_encode($out);
