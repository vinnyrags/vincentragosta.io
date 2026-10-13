<?php
/**
 * Apply redesigned page content from scripts/redesign/content/pages/{slug}.html.
 *
 * Page content lives only in the database, and production must never receive
 * a full DB push (it holds itzenzo.tv's live data), so redesigned pages ship
 * as files and are applied by this script at cutover.
 *
 * For each content file: finds the page by path (the file's slug), backs up
 * the current post_content to $BACKUP_DIR/{slug}-{timestamp}.html, then
 * replaces it. Pages whose content already matches are skipped.
 * PAGES=projects,home limits the run; DRY_RUN=1 writes nothing.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   DRY_RUN=1 ddev wp eval-file scripts/redesign/apply-page-content.php
 *   ddev exec "PAGES=projects BACKUP_DIR=/var/www/html/tmp wp eval-file scripts/redesign/apply-page-content.php"
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/apply-page-content.php\n";
    exit(1);
}

$dryRun    = !empty(getenv('DRY_RUN'));
$backupDir = rtrim(getenv('BACKUP_DIR') ?: sys_get_temp_dir(), '/');
$only      = array_filter(array_map('trim', explode(',', (string) getenv('PAGES'))));
$files     = glob(__DIR__ . '/content/pages/*.html') ?: [];
$prefix    = $dryRun ? '[dry-run] ' : '';
$errors    = 0;

if (!$files) {
    echo "Error: no content files in " . __DIR__ . "/content/pages/\n";
    exit(1);
}

foreach ($files as $file) {
    $slug = basename($file, '.html');
    if ($only && !in_array($slug, $only, true)) {
        continue;
    }

    $page = get_page_by_path($slug === 'home' ? 'home' : $slug, OBJECT, 'page');
    if (!$page) {
        echo "Error: no page at /{$slug}/\n";
        $errors++;
        continue;
    }

    $content = trim((string) file_get_contents($file));
    // Content files use the production host; rewrite to this environment's.
    $content = str_replace('https://vincentragosta.io/', trailingslashit(home_url()), $content);

    if (trim($page->post_content) === $content) {
        echo "{$prefix}✓ {$slug} (#{$page->ID}) already up to date\n";
        continue;
    }

    if ($dryRun) {
        printf("%sWould replace %s (#%d): %d → %d bytes\n", $prefix, $slug, $page->ID, strlen($page->post_content), strlen($content));
        continue;
    }

    $backup = sprintf('%s/%s-%s.html', $backupDir, $slug, gmdate('Ymd-His'));
    if (file_put_contents($backup, $page->post_content) === false) {
        echo "Error: could not write backup {$backup} — {$slug} left unchanged\n";
        $errors++;
        continue;
    }

    $result = wp_update_post(['ID' => $page->ID, 'post_content' => wp_slash($content)], true);
    if (is_wp_error($result)) {
        echo "Error: {$slug}: " . $result->get_error_message() . "\n";
        $errors++;
        continue;
    }

    echo "✓ Replaced {$slug} (#{$page->ID}); backup: {$backup}\n";
}

if (!$dryRun) {
    wp_cache_flush();
}

echo $errors ? "Finished with {$errors} error(s).\n" : "Done.\n";
exit($errors ? 1 : 0);
