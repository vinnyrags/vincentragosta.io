<?php
/**
 * One-shot migration: regenerate card attachment sub-sizes as JPEG and
 * delete the orphaned PNG sub-sizes.
 *
 * Card art originals are 734x1024 PNG. After the PngSubsizesAsJpeg hook
 * is in place, regenerating attachment metadata produces JPEG sub-sizes
 * (~115 KB) instead of PNG (~760 KB) — roughly an 85% size reduction.
 *
 * Safe to re-run: regen on attachments that already have JPEG sub-sizes
 * is a no-op for our purposes, and the cleanup pass only deletes PNGs
 * that have a sibling JPEG.
 *
 * Usage: ddev wp eval-file scripts/migrate-card-images-to-jpeg.php
 *        wp eval-file scripts/migrate-card-images-to-jpeg.php --path=/var/www/site/wp --allow-root
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI.\n";
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/image.php';

$cardIds = get_posts([
    'post_type'      => 'card',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'post_status'    => ['publish', 'draft', 'pending'],
]);

$attachmentIds = get_posts([
    'post_type'         => 'attachment',
    'post_parent__in'   => $cardIds,
    'posts_per_page'    => -1,
    'fields'            => 'ids',
]);

echo count($attachmentIds) . " card attachments found\n";

// 1) Regenerate metadata so sub-sizes are JPEG under the active filter
$regenerated = 0;
$skippedMissing = 0;
foreach ($attachmentIds as $id) {
    $file = get_attached_file($id);
    if (!$file || !file_exists($file)) {
        $skippedMissing++;
        continue;
    }
    $meta = wp_generate_attachment_metadata($id, $file);
    wp_update_attachment_metadata($id, $meta);
    $regenerated++;
}
echo "Regenerated metadata for {$regenerated} attachments";
if ($skippedMissing > 0) {
    echo " (skipped {$skippedMissing} with missing files)";
}
echo "\n";

// 2) Delete orphan PNG sub-sizes whose JPEG sibling now exists
$deleted = 0;
$bytes = 0;
foreach ($attachmentIds as $id) {
    $original = get_attached_file($id);
    if (!$original) {
        continue;
    }
    $dir = dirname($original);
    $basename = pathinfo($original, PATHINFO_FILENAME);

    $candidates = glob($dir . '/' . $basename . '-*x*.png');
    if (!$candidates) {
        continue;
    }

    foreach ($candidates as $png) {
        $jpgSibling = preg_replace('/\.png$/i', '.jpg', $png);
        if (!file_exists($jpgSibling)) {
            continue;
        }
        $bytes += filesize($png);
        if (@unlink($png)) {
            $deleted++;
        }
    }
}
printf("Deleted %d orphan PNG sub-sizes (%.1f MB freed)\n", $deleted, $bytes / 1048576);
