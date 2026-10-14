<?php
/**
 * Make two-letter words searchable ("AI", "UX", "VR").
 *
 * Relevanssi drops words shorter than `relevanssi_min_word_length` both when
 * it indexes and when it parses a query; the default (3) left "AI" — the most
 * common term in Nous Signal — unsearchable. This sets it to 2 and rebuilds
 * the index for every indexed post type, since existing entries were built
 * without the short words.
 *
 * Free Relevanssi has no WP-CLI command, so the rebuild loops
 * relevanssi_index_doc() over published posts (as proven for projects in the
 * cutover checklist). Re-running is safe: the option is idempotent and
 * re-indexing a post replaces its entries.
 *
 * DRY_RUN=1 reports the current setting, how many posts would be re-indexed,
 * and how many results "ai" returns today; it writes nothing.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   ddev exec "DRY_RUN=1 wp eval-file scripts/redesign/relevanssi-short-words.php"
 *   ddev exec "wp eval-file scripts/redesign/relevanssi-short-words.php"
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/relevanssi-short-words.php\n";
    exit(1);
}

if (!function_exists('relevanssi_index_doc') || !function_exists('relevanssi_do_query')) {
    echo "Error: Relevanssi is not active.\n";
    exit(1);
}

const VR_MIN_WORD_LENGTH = 2;

$dryRun = !empty(getenv('DRY_RUN'));
$prefix = $dryRun ? '[dry-run] ' : '';

/**
 * Number of results a search returns right now.
 */
$hits = static function (string $term): int {
    $query = new WP_Query();
    $query->parse_query(['s' => $term, 'posts_per_page' => -1, 'fields' => 'ids', 'post_type' => 'any']);
    relevanssi_do_query($query);

    return count($query->posts);
};

$current = (int) get_option('relevanssi_min_word_length', 3);
$types   = array_values(array_filter((array) get_option('relevanssi_index_post_types', []), 'post_type_exists'));
$ids     = get_posts([
    'post_type'      => $types,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);

echo "{$prefix}Min word length: {$current} → " . VR_MIN_WORD_LENGTH . "\n";
echo "{$prefix}Indexed types: " . implode(', ', $types) . ' — ' . count($ids) . " published posts to re-index\n";
echo "{$prefix}\"ai\" today: " . $hits('ai') . " results\n";

if ($dryRun) {
    echo "{$prefix}Done. Nothing written.\n";
    exit(0);
}

update_option('relevanssi_min_word_length', VR_MIN_WORD_LENGTH);

$customFields = function_exists('relevanssi_get_custom_fields') ? relevanssi_get_custom_fields() : false;
$done = 0;

foreach ($ids as $id) {
    relevanssi_index_doc((int) $id, true, $customFields, true);
    $done++;
}

wp_cache_flush();

echo "✓ Min word length set to " . VR_MIN_WORD_LENGTH . "; re-indexed {$done} posts.\n";
echo "✓ \"ai\" now: " . $hits('ai') . " results\n";
