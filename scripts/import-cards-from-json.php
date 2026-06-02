<?php
/**
 * Additively import `card` posts from a full-fidelity export (see
 * export-cards-full.php) into the current WordPress environment.
 *
 * NON-DESTRUCTIVE: only creates cards the target is MISSING, matched by
 * stripe_product_id (falling back to exact title when a row has no Stripe id).
 * Existing cards are left completely untouched — no price/stock/status writes,
 * no deletes. Use this to backfill cards that exist on another environment
 * (e.g. the Japanese singles that live on production but not in a local DB)
 * before a local → prod/staging push, without overwriting the whole database.
 *
 * For each missing card it creates the post (preserving title, content, slug,
 * status, date), writes all ACF meta, sideloads the featured image from the
 * source URL, and assigns card_game / card_set taxonomies.
 *
 * Dry-run by default — prints what WOULD be created. Set APPLY=1 to write.
 *
 * Usage:  CARDS_JSON=/tmp/cards-full.json ddev wp eval-file scripts/import-cards-from-json.php
 *  apply: CARDS_JSON=/tmp/cards-full.json APPLY=1 ddev wp eval-file scripts/import-cards-from-json.php
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: wp eval-file scripts/import-cards-from-json.php\n";
    exit(1);
}

$jsonPath = getenv('CARDS_JSON') ?: '/tmp/cards-full.json';
if (!file_exists($jsonPath)) {
    echo "Error: cards JSON not found at {$jsonPath}\n";
    echo "Generate it on the source env: wp eval-file scripts/export-cards-full.php > /tmp/cards-full.json\n";
    exit(1);
}

$cards = json_decode(file_get_contents($jsonPath), true);
if (!is_array($cards)) {
    echo "Error: could not parse JSON at {$jsonPath}\n";
    exit(1);
}

$apply = !empty(getenv('APPLY'));
echo ($apply ? 'APPLYING' : 'DRY RUN (no writes — set APPLY=1 to write)') . "\n";
echo 'Source: ' . $jsonPath . ' (' . count($cards) . " cards in export)\n\n";

$created = 0;
$skipped = 0;

foreach ($cards as $c) {
    $pid = (string) ($c['stripe_product_id'] ?? '');
    $title = $c['title'] ?? '';

    $existingId = cardExistsLocally($pid, $title);
    if ($existingId) {
        $skipped++;
        continue;
    }

    $lang = $c['meta']['language'] ?? '';
    echo '  + ' . $title . ($lang ? " [{$lang}]" : '') . ($pid ? " ({$pid})" : ' (no stripe id — title match)') . "\n";

    if (!$apply) {
        $created++;
        continue;
    }

    $postId = wp_insert_post([
        'post_type'    => 'card',
        'post_title'   => $title,
        'post_content' => $c['content'] ?? '',
        'post_status'  => in_array($c['status'] ?? 'publish', ['publish', 'draft', 'pending'], true) ? $c['status'] : 'draft',
        'post_name'    => $c['slug'] ?? '',
        'post_date'    => $c['date'] ?? '',
    ], true);

    if (is_wp_error($postId)) {
        echo "      ! create failed: {$postId->get_error_message()}\n";
        continue;
    }

    foreach (($c['meta'] ?? []) as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        update_field($k, $v, $postId);
    }

    if (!empty($c['image'])) {
        sideloadFeaturedImage($postId, $c['image'], $title);
    }

    syncCardTaxonomies(
        $postId,
        $c['meta']['game'] ?? '',
        $c['meta']['set_name'] ?? '',
        $c['meta']['set_code'] ?? ''
    );

    $created++;
}

echo "\n" . ($apply ? 'Created' : 'Would create') . ": {$created} card(s); {$skipped} already present (untouched).\n";
if (!$apply) {
    echo "\nRe-run with APPLY=1 to write. Image sideload makes the apply slower (one download per new card).\n";
}

/**
 * Returns the local card ID matching this card, or 0 if missing.
 * Prefer stripe_product_id; fall back to an exact title match for rows
 * with no Stripe id so they don't get duplicated on re-runs.
 */
function cardExistsLocally(string $pid, string $title): int
{
    if ($pid !== '') {
        $q = new WP_Query([
            'post_type'      => 'card',
            'post_status'    => ['publish', 'draft', 'pending', 'trash'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [['key' => 'stripe_product_id', 'value' => $pid]],
        ]);
        if (!empty($q->posts)) {
            return (int) $q->posts[0];
        }
        return 0;
    }

    if ($title !== '') {
        $q = new WP_Query([
            'post_type'      => 'card',
            'post_status'    => ['publish', 'draft', 'pending', 'trash'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'title'          => $title,
        ]);
        if (!empty($q->posts)) {
            return (int) $q->posts[0];
        }
    }

    return 0;
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
        echo "      ! image sideload failed for {$title}: {$attachmentId->get_error_message()}\n";
        return;
    }

    set_post_thumbnail($postId, $attachmentId);
    update_post_meta($attachmentId, '_source_url', $imageUrl);
}

/**
 * Mirror of pull-cards.php::maybeSyncCardTaxonomies — create/assign the
 * card_game and card_set terms from the card's game + set_name/set_code meta.
 */
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
