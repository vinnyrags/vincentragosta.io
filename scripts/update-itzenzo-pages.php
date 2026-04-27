<?php
/**
 * One-off targeted update for the itzenzo_pages ACF repeater.
 *
 * Replaces only the rows whose slugs are listed in $TARGET_SLUGS,
 * sourcing the new payload from seed-itzenzo-pages.php's $pages array.
 * Untouched rows are preserved as-is so any in-WP-admin edits to other
 * pages survive. Idempotent — re-running rewrites the same rows.
 *
 * Usage (production):
 *   wp eval-file /var/www/vincentragosta.io/scripts/update-itzenzo-pages.php \
 *       --path=/var/www/vincentragosta.io/wp --allow-root
 *
 * Local (DDEV):
 *   ddev wp eval-file scripts/update-itzenzo-pages.php
 *
 * Delete after successful prod run — this is a one-shot for the
 * voice-pass copy refresh on 2026-04-27.
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: wp eval-file scripts/update-itzenzo-pages.php\n";
    exit(1);
}

if (!function_exists('update_field')) {
    echo "Error: ACF is not loaded.\n";
    exit(1);
}

$TARGET_SLUGS = [
    'about',
    'how-it-works-buying',
    'how-it-works-refund-policy',
    'gaming',
];

// Extract the canonical $pages array from seed-itzenzo-pages.php
// without triggering its write logic. Regex-pull the literal block,
// then eval it in a closure so we get a clean local var.
$seedPath = __DIR__ . '/seed-itzenzo-pages.php';
if (!file_exists($seedPath)) {
    echo "Error: seed-itzenzo-pages.php not found at {$seedPath}.\n";
    exit(1);
}

$src = file_get_contents($seedPath);
if (!preg_match('/(\$pages\s*=\s*\[.*?\n\];)\s*\n\s*\$existing\s*=\s*get_field/s', $src, $m)) {
    echo "Error: could not locate \$pages literal in seed file.\n";
    exit(1);
}

$pages = (static function () use ($m) {
    $pages = null;
    eval($m[1]);
    return $pages;
})();

if (!is_array($pages)) {
    echo "Error: failed to evaluate \$pages array from seed file.\n";
    exit(1);
}

$canonical = [];
foreach ($pages as $row) {
    if (isset($row['slug'])) {
        $canonical[$row['slug']] = $row;
    }
}

$existing = get_field('itzenzo_pages', 'option');
if (!is_array($existing) || count($existing) === 0) {
    echo "Error: itzenzo_pages is empty on this site. Run seed-itzenzo-pages.php first.\n";
    exit(1);
}

$updated = [];
$skipped = [];
$merged  = [];
foreach ($existing as $row) {
    $slug = $row['slug'] ?? null;
    if ($slug && in_array($slug, $TARGET_SLUGS, true) && isset($canonical[$slug])) {
        $merged[]  = $canonical[$slug];
        $updated[] = $slug;
    } else {
        $merged[]  = $row;
        if ($slug) {
            $skipped[] = $slug;
        }
    }
}

foreach ($TARGET_SLUGS as $slug) {
    if (!in_array($slug, $updated, true) && isset($canonical[$slug])) {
        $merged[]  = $canonical[$slug];
        $updated[] = $slug . ' (appended — was missing)';
    }
}

// ACF's update_field on a populated repeater can fail when row identity
// changes. Force-clear first, then write the merged set.
update_field('itzenzo_pages', [], 'option');
$ok = update_field('itzenzo_pages', $merged, 'option');

if ($ok === false) {
    echo "Error: update_field returned false.\n";
    exit(1);
}

echo "✓ Updated " . count($updated) . " row(s): " . implode(', ', $updated) . "\n";
echo "  Preserved " . count($skipped) . " row(s): " . implode(', ', $skipped) . "\n";
