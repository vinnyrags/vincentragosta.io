<?php
/**
 * Export every in-stock `card` + `product` post as a flat JSON array
 * consumable by Nous/scripts/shop/build-whatnot-full-import.mjs.
 *
 * Run via: wp eval-file scripts/export-inventory-json.php > /tmp/inventory.json
 *
 * Shape (one element per item):
 *   {
 *     id, post_type, title, image,
 *     meta: { price, stock_quantity, language, ...card-only keys... }
 *   }
 *
 * Filters:
 *   - post_status = publish
 *   - stock_quantity > 0
 *   - cards with is_personal_collection = true are skipped
 *     (not for sale on either itzenzo.tv or Whatnot)
 */

$out = [];

$card_meta_keys = [
    'price', 'stock_quantity', 'language', 'condition',
    'card_name', 'card_number', 'set_name', 'set_code',
    'game', 'rarity', 'variant', 'artist', 'release_date',
    // Join key to the Singles sheet (col T) so the Whatnot builder can
    // look up the sheet's BIN Price (col F) for Buy-it-Now listings.
    'stripe_product_id',
];
$product_meta_keys = ['price', 'stock_quantity', 'language'];

foreach (['card', 'product'] as $post_type) {
    $posts = get_posts([
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'DESC',
    ]);

    foreach ($posts as $post) {
        $stock = (int) get_post_meta($post->ID, 'stock_quantity', true);
        if ($stock < 1) {
            continue;
        }

        if (
            $post_type === 'card'
            && get_post_meta($post->ID, 'is_personal_collection', true)
        ) {
            continue;
        }

        $keys = $post_type === 'card' ? $card_meta_keys : $product_meta_keys;
        $meta = [];
        foreach ($keys as $k) {
            $v = get_post_meta($post->ID, $k, true);
            $meta[$k] = is_string($v) ? $v : (string) $v;
        }

        $image_url = '';
        $thumb_id = (int) get_post_thumbnail_id($post->ID);
        if ($thumb_id) {
            $src = wp_get_attachment_image_src($thumb_id, 'full');
            if ($src && !empty($src[0])) {
                $image_url = $src[0];
            }
        }

        $out[] = [
            'id'        => $post->ID,
            'post_type' => $post_type,
            'title'     => $post->post_title,
            'image'     => $image_url,
            'meta'      => $meta,
        ];
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
