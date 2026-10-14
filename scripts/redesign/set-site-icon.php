<?php
/**
 * Install the redesign's site icon (favicon / app icons).
 *
 * The icon is the header's V mark: content/site-icon/site-icon.png, rendered
 * at 512 px from site-icon.svg beside it. This imports it, generates the sizes
 * WordPress serves for a Site Icon (32, 180, 192, 270, 512) exactly as the
 * Customizer's crop step does, marks the attachment as a site icon, and points
 * Settings → General → Site Icon at it. The previous icon attachment is left
 * in the media library, so rolling back is choosing it again.
 *
 * Idempotent: an icon already imported from this file (matched by the hash
 * stored on the attachment) is reused rather than imported twice.
 * DRY_RUN=1 reports the current and planned icon and writes nothing.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   ddev exec "DRY_RUN=1 wp eval-file scripts/redesign/set-site-icon.php"
 *   ddev exec "wp eval-file scripts/redesign/set-site-icon.php"
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/set-site-icon.php\n";
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';

const VR_ICON_FILE = __DIR__ . '/content/site-icon/site-icon.png';
const VR_ICON_HASH_META = '_vr_site_icon_sha1';

$dryRun = !empty(getenv('DRY_RUN'));
$prefix = $dryRun ? '[dry-run] ' : '';

if (!is_readable(VR_ICON_FILE)) {
    echo "Error: missing " . VR_ICON_FILE . "\n";
    exit(1);
}

$hash    = sha1_file(VR_ICON_FILE);
$current = (int) get_option('site_icon');

$existing = get_posts([
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'posts_per_page' => 1,
    'fields'         => 'ids',
    'meta_key'       => VR_ICON_HASH_META,
    'meta_value'     => $hash,
]);
$existingId = (int) ($existing[0] ?? 0);

echo "{$prefix}Current site icon: " . ($current ? "#{$current} " . basename((string) get_attached_file($current)) : 'none') . "\n";

if ($existingId && $current === $existingId) {
    echo "{$prefix}✓ Already the site icon (#{$existingId}). Nothing to do.\n";
    exit(0);
}

if ($dryRun) {
    echo $existingId
        ? "{$prefix}Would point the site icon at the already-imported #{$existingId}\n"
        : "{$prefix}Would import site-icon.png, generate 32/180/192/270/512 sizes, and set it as the site icon\n";
    echo "{$prefix}Done. Nothing written.\n";
    exit(0);
}

$id = $existingId;

if (!$id) {
    // One generation pass, at import: add the site-icon sizes (as the
    // Customizer's crop does) and keep every size PNG — the Shop provider's
    // PngSubsizesAsJpeg turns PNG sub-sizes into JPEG, which is right for card
    // art and wrong for a flat-color icon at 32 px.
    $siteIcon = new WP_Site_Icon();
    $keepPng  = static function (array $formats): array {
        unset($formats['image/png']);
        return $formats;
    };
    add_filter('intermediate_image_sizes_advanced', [$siteIcon, 'additional_sizes']);
    add_filter('image_editor_output_format', $keepPng, PHP_INT_MAX);

    // Import a copy (media_handle_sideload moves the file it is given).
    $tmp = wp_tempnam('site-icon.png');
    copy(VR_ICON_FILE, $tmp);
    $id = media_handle_sideload(['name' => 'site-icon.png', 'tmp_name' => $tmp], 0, 'Site icon');

    remove_filter('image_editor_output_format', $keepPng, PHP_INT_MAX);
    remove_filter('intermediate_image_sizes_advanced', [$siteIcon, 'additional_sizes']);

    if (is_wp_error($id)) {
        @unlink($tmp);
        echo "Error: import failed: " . $id->get_error_message() . "\n";
        exit(1);
    }

    update_post_meta($id, '_wp_attachment_context', 'site-icon');
    update_post_meta($id, VR_ICON_HASH_META, $hash);
    echo "✓ Imported site icon (#{$id})\n";
}

update_option('site_icon', $id);
echo "✓ Site icon set to #{$id}" . ($current ? " (was #{$current}, kept in the media library)" : '') . "\n";

foreach ([32, 180, 192, 270] as $size) {
    echo "  ↳ {$size}px: " . basename((string) get_site_icon_url($size)) . "\n";
}
