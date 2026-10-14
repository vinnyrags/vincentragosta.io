<?php
/**
 * Configure the Instagram feed (Smash Balloon feed #1) for the Meet me page:
 * four square tiles, no captions or like counts, and GDPR mode forced on so
 * images are always served from this site's uploads — the browser never
 * contacts Instagram's CDN (keeps the privacy policy's "no third-party
 * requests" statement true).
 *
 * Feed settings live in the database (Instagram Feed Lite ignores most
 * shortcode attributes), so — like page content — they ship by script.
 * Idempotent; values already in place are skipped. DRY_RUN=1 writes nothing.
 * The account itself is connected by hand in the plugin's Settings page; this
 * script does not touch sources.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   DRY_RUN=1 ddev wp eval-file scripts/redesign/update-instagram-feed.php
 *   ddev wp eval-file scripts/redesign/update-instagram-feed.php
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/update-instagram-feed.php\n";
    exit(1);
}

global $wpdb;

$feedId = (int) (getenv('FEED_ID') ?: 1);
$dryRun = !empty(getenv('DRY_RUN'));
$prefix = $dryRun ? '[dry-run] ' : '';
$table  = $wpdb->prefix . 'sbi_feeds';

$feedSettings = [
    'num'          => '4',
    'nummobile'    => '4',
    'cols'         => '4',
    'colstablet'   => '2',
    'colsmobile'   => '2',
    'imagepadding' => '7',
    'showcaption'  => false,
    'showlikes'    => false,
    'showheader'   => false,
    'showbutton'   => false,
    'showfollow'   => false,
];

if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
    echo "Error: Instagram Feed is not installed ({$table} missing).\n";
    exit(1);
}

$raw = $wpdb->get_var($wpdb->prepare("SELECT settings FROM {$table} WHERE id = %d", $feedId));
$settings = $raw ? json_decode($raw, true) : null;
if (!is_array($settings)) {
    echo "Error: feed #{$feedId} not found or unreadable.\n";
    exit(1);
}

$changes = 0;

foreach ($feedSettings as $key => $value) {
    if (($settings[$key] ?? null) === $value) {
        echo "✓ feed {$key} already " . json_encode($value) . "\n";
        continue;
    }
    echo "{$prefix}✓ feed {$key}: " . json_encode($settings[$key] ?? null) . ' → ' . json_encode($value) . "\n";
    $settings[$key] = $value;
    $changes++;
}

$global = get_option('sb_instagram_settings', []);
$global = is_array($global) ? $global : [];
$gdprChanged = ($global['gdpr'] ?? 'auto') !== 'yes';
if ($gdprChanged) {
    echo "{$prefix}✓ GDPR mode: " . ($global['gdpr'] ?? 'auto') . " → yes (images served locally)\n";
    $changes++;
} else {
    echo "✓ GDPR mode already yes\n";
}

if ($changes === 0 || $dryRun) {
    echo "Done ({$changes} change(s)" . ($dryRun ? ', none written' : '') . ").\n";
    exit(0);
}

$wpdb->update($table, ['settings' => wp_json_encode($settings)], ['id' => $feedId]);

if ($gdprChanged) {
    $global['gdpr'] = 'yes';
    update_option('sb_instagram_settings', $global);
}

// Drop the rendered-feed cache so the new layout shows on the next request.
$wpdb->query("DELETE FROM {$wpdb->prefix}sbi_feed_caches");
wp_cache_flush();

echo "Done ({$changes} change(s) written).\n";
