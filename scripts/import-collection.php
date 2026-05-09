<?php
/**
 * Import personal-collection cards from JSON via STDIN.
 *
 * Reads the JSON output from `Nous/scripts/shop/seed-collection-tab.mjs`
 * and creates/updates `card` CPT posts with `is_personal_collection=true`.
 * No Stripe interaction — these cards are vault, not catalog.
 *
 * Idempotent on `apiId` (Pokemon TCG API stable id, e.g. "base1-4")
 * stored as post meta. Re-running with the same JSON updates the
 * existing posts in place. Cards with no apiId (the 8 promos that
 * couldn't auto-enrich) are deduped by slug derived from
 * `<setName>-<cardName>-<cardNumber>`.
 *
 * Usage (locally via DDEV):
 *   cat tmp/collection-enriched.json | ddev wp eval-file scripts/import-collection.php
 *
 * Usage (production via SSH):
 *   cat tmp/collection-enriched.json | \
 *     ssh root@DROPLET 'cd /var/www/vincentragosta.io && \
 *       wp eval-file scripts/import-collection.php --allow-root'
 *
 * Env flags (read from getenv):
 *   PUBLISH=1   — create posts as `publish` (default `draft`)
 *   DRY_RUN=1   — log what would happen, write nothing
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: wp eval-file scripts/import-collection.php\n";
    exit(1);
}

$publish = !empty(getenv('PUBLISH')) || file_exists(__DIR__ . '/.publish');
$dryRun = !empty(getenv('DRY_RUN'));

$stdin = file_get_contents('php://stdin');
if (!$stdin) {
    echo "Error: No JSON received on STDIN.\n";
    echo "Pipe the enriched JSON in: cat tmp/collection-enriched.json | wp eval-file ...\n";
    exit(1);
}

$rows = json_decode($stdin, true);
if (!is_array($rows)) {
    echo "Error: Invalid JSON on STDIN: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "Importing " . count($rows) . " collection card(s)" . ($dryRun ? ' [DRY RUN]' : '') . "...\n";
echo "Status target: " . ($publish ? 'publish' : 'draft') . "\n\n";

$created = 0;
$updated = 0;
$skipped = 0;
$imageFailures = 0;

foreach ($rows as $i => $row) {
    $cardName   = trim((string) ($row['cardName'] ?? ''));
    $setName    = trim((string) ($row['setName'] ?? ''));
    $cardNumber = trim((string) ($row['cardNumber'] ?? ''));
    $variant    = trim((string) ($row['variant'] ?? ''));
    $language   = trim((string) ($row['language'] ?? 'English'));
    $rarity     = trim((string) ($row['rarity'] ?? ''));
    $releaseDate = trim((string) ($row['releaseDate'] ?? ''));
    $artist     = trim((string) ($row['artist'] ?? ''));
    $imageUrl   = trim((string) ($row['imageUrl'] ?? ''));
    $apiId      = trim((string) ($row['apiId'] ?? ''));

    if (!$cardName) {
        echo "  [{$i}] SKIP (no cardName)\n";
        $skipped++;
        continue;
    }

    // Title carries the full identity so /collection grids show "Charizard
    // Base Set 4/102" the way the curator sees it in Sheets, matching how
    // tmp/card-updates.txt was originally written.
    $title = trim($cardName . ' ' . $setName . ($cardNumber ? " {$cardNumber}" : ''));

    // Stable slug for re-runs when apiId is missing (promos / specials).
    $slug = sanitize_title(implode('-', array_filter([
        $setName,
        $cardName,
        $cardNumber,
        $variant,
    ])));

    // Idempotency: prefer apiId match; fall back to slug match.
    $existing = null;
    if ($apiId) {
        $byApi = get_posts([
            'post_type'   => 'card',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => 1,
            'meta_key'    => 'pokemon_tcg_api_id',
            'meta_value'  => $apiId,
        ]);
        if ($byApi) {
            $existing = $byApi[0];
        }
    }
    if (!$existing) {
        $bySlug = get_page_by_path($slug, OBJECT, 'card');
        if ($bySlug) {
            $existing = $bySlug;
        }
    }

    if ($dryRun) {
        $action = $existing ? "would-update (#{$existing->ID})" : 'would-create';
        echo "  [{$i}] {$action}: {$title}" . ($apiId ? " [{$apiId}]" : ' [no api id]') . "\n";
        continue;
    }

    if ($existing) {
        // Update title in case the curator renamed the card; leave
        // status alone (don't unpublish a published card on re-run).
        wp_update_post([
            'ID'         => $existing->ID,
            'post_title' => $title,
        ]);
        $postId = $existing->ID;
        echo "  [{$i}] updated: {$title} (#{$postId})\n";
        $updated++;
    } else {
        $postId = wp_insert_post([
            'post_type'   => 'card',
            'post_status' => $publish ? 'publish' : 'draft',
            'post_title'  => $title,
            'post_name'   => $slug,
        ]);
        if (is_wp_error($postId)) {
            echo "  [{$i}] ERROR creating {$title}: {$postId->get_error_message()}\n";
            $skipped++;
            continue;
        }
        echo "  [{$i}] created: {$title} (#{$postId})" . ($apiId ? " [{$apiId}]" : '') . "\n";
        $created++;
    }

    // ACF fields. update_field tolerates empty values fine — clearing
    // a field on re-run is intentional behavior.
    update_field('is_personal_collection', true, $postId);
    update_field('card_name', $cardName, $postId);
    update_field('set_name', $setName, $postId);
    update_field('card_number', $cardNumber, $postId);
    update_field('language', $language, $postId);
    if ($rarity) {
        update_field('rarity', [$rarity], $postId);
    }
    if ($releaseDate) {
        update_field('release_date', $releaseDate, $postId);
    }
    if ($artist) {
        update_field('artist', $artist, $postId);
    }
    if ($variant) {
        update_field('variant', [$variant], $postId);
    }
    // Stock 0 + condition near-mint by default (these aren't for sale,
    // but the fields are required by the catalog grid renderer).
    update_field('stock_quantity', 0, $postId);
    update_field('condition', ['near-mint'], $postId);

    // Pokemon TCG API id as post meta — idempotency key for re-runs.
    if ($apiId) {
        update_post_meta($postId, 'pokemon_tcg_api_id', $apiId);
    }

    // Featured image — only download if we have a URL and the post
    // doesn't already have one from the same source.
    if ($imageUrl) {
        $ok = importCardImage($postId, $imageUrl, $title);
        if (!$ok) {
            $imageFailures++;
        }
    }

    // card_game taxonomy — every entry here is Pokemon.
    wp_set_object_terms($postId, 'pokemon', 'card_game', false);

    // card_set taxonomy — derive a clean slug from setName.
    if ($setName) {
        $setSlug = sanitize_title($setName);
        $term = get_term_by('slug', $setSlug, 'card_set');
        if (!$term) {
            $newTerm = wp_insert_term($setName, 'card_set', ['slug' => $setSlug]);
            if (!is_wp_error($newTerm)) {
                wp_set_object_terms($postId, [$newTerm['term_id']], 'card_set', false);
            }
        } else {
            wp_set_object_terms($postId, [$term->term_id], 'card_set', false);
        }
    }
}

echo "\nDone: {$created} created, {$updated} updated, {$skipped} skipped";
if ($imageFailures) {
    echo ", {$imageFailures} image failure(s)";
}
echo ".\n";

function importCardImage(int $postId, string $imageUrl, string $title): bool
{
    $currentThumbnailId = get_post_thumbnail_id($postId);
    if ($currentThumbnailId) {
        $currentSource = get_post_meta($currentThumbnailId, '_collection_source_url', true);
        if ($currentSource === $imageUrl) {
            // Already attached from this same URL — nothing to do.
            return true;
        }
    }

    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $attachmentId = media_sideload_image($imageUrl, $postId, $title, 'id');
    if (is_wp_error($attachmentId)) {
        echo "    Warning: image download failed for {$title}: {$attachmentId->get_error_message()}\n";
        return false;
    }

    set_post_thumbnail($postId, $attachmentId);
    update_post_meta($attachmentId, '_collection_source_url', $imageUrl);
    return true;
}
