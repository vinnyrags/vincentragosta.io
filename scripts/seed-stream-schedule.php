<?php
/**
 * Seed the itzenzo.tv stream-schedule ACF repeater.
 *
 * Writes the canonical weekly stream schedule into the
 * `itzenzo_stream_schedule` repeater on the shop-settings ACF options
 * page. Each entry is a `{ days, description }` row. itzenzo.tv reads
 * this through WPGraphQL (`itzenzoStreamSchedule`) and renders it as a
 * synthetic "Stream Schedule" section on /the-stream.
 *
 * Mirrors the seed-itzenzo-pages.php pattern: idempotent by default,
 * `FORCE=1` to overwrite. Once seeded, WordPress is the source of
 * truth — editors change it via WP admin.
 *
 * Usage:
 *   ddev wp eval-file scripts/seed-stream-schedule.php
 *   FORCE=1 ddev wp eval-file scripts/seed-stream-schedule.php
 *
 * Remote:
 *   wp eval-file scripts/seed-stream-schedule.php --path=/var/www/site/wp --allow-root
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/seed-stream-schedule.php\n";
    exit(1);
}

if (!function_exists('update_field')) {
    echo "Error: ACF is not loaded. Make sure Advanced Custom Fields Pro is active.\n";
    exit(1);
}

$force = !empty(getenv('FORCE'));

$schedule = [
    [
        'days'        => 'Monday – Wednesday',
        'description' => 'Card nights — Pokemon and anime, 8PM EST',
    ],
];

$existing = get_field('itzenzo_stream_schedule', 'option');

if (!empty($existing) && !$force) {
    echo "Refusing to overwrite existing itzenzo_stream_schedule (" . count($existing) . " row(s)).\n";
    echo "Set FORCE=1 to replace whatever is already in the repeater.\n";
    exit(0);
}

// Mirror the pages-seed pattern: clear-then-write avoids ACF row-identity
// edge cases when the new payload has fewer rows than the old.
update_field('itzenzo_stream_schedule', [], 'option');
$ok = update_field('itzenzo_stream_schedule', $schedule, 'option');

if ($ok === false) {
    echo "Error: update_field returned false. Check ACF field key.\n";
    exit(1);
}

$action = !empty($existing) ? 'Overwrote' : 'Wrote';
echo "✓ {$action} itzenzo_stream_schedule with " . count($schedule) . " row(s):\n";
foreach ($schedule as $row) {
    echo "  - {$row['days']}: {$row['description']}\n";
}
