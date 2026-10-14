<?php
/**
 * Clear legacy project post_content once the "Project" ACF fields hold it.
 *
 * Projects render entirely from their fields (migrate-project-fields.php
 * copied the content across). The old block markup stays in post_content
 * until this runs — harmless to the new template, but Relevanssi indexes it,
 * so every project would match twice and the stale copy would outlive any
 * edit made in the fields.
 *
 * A project is cleared only when its fields are filled (a lead and at least
 * one section); anything else is skipped and reported, so a project whose
 * migration didn't take can never lose its only copy. Every cleared post's
 * content is written to {BACKUP_DIR}/project-content-{timestamp}.json first.
 * Re-running is safe: empty projects are skipped.
 *
 * DRY_RUN=1 reports what would be cleared and writes nothing.
 * Run it AFTER migrate-project-fields.php (step 2) and split-wicked.php
 * (step 4), and BEFORE the Relevanssi reindex.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   ddev exec "DRY_RUN=1 BACKUP_DIR=/var/www/html/tmp wp eval-file scripts/redesign/clear-project-content.php"
 *   ddev exec "BACKUP_DIR=/var/www/html/tmp wp eval-file scripts/redesign/clear-project-content.php"
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/clear-project-content.php\n";
    exit(1);
}

$dryRun    = !empty(getenv('DRY_RUN'));
$backupDir = rtrim(getenv('BACKUP_DIR') ?: sys_get_temp_dir(), '/');
$prefix    = $dryRun ? '[dry-run] ' : '';

$projects = get_posts([
    'post_type'      => 'project',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'orderby'        => 'ID',
    'order'          => 'ASC',
]);

$clear = [];
$skipped = [];
$empty = 0;

foreach ($projects as $project) {
    if (trim($project->post_content) === '') {
        $empty++;
        continue;
    }

    $lead     = trim(wp_strip_all_tags((string) get_post_meta($project->ID, 'project_lead', true)));
    $sections = (int) get_post_meta($project->ID, 'project_sections', true);

    if ($lead === '' || $sections < 1) {
        $skipped[] = sprintf('#%d %s (lead: %s, sections: %d)', $project->ID, $project->post_name, $lead === '' ? 'empty' : 'ok', $sections);
        continue;
    }

    $clear[$project->ID] = $project->post_content;
}

echo "{$prefix}" . count($projects) . " projects: " . count($clear) . " to clear, {$empty} already empty, " . count($skipped) . " skipped\n";
foreach ($skipped as $line) {
    echo "{$prefix}  skipped (fields not filled — content kept): {$line}\n";
}

if ($dryRun || !$clear) {
    echo $dryRun ? "{$prefix}Done. Nothing written.\n" : "Nothing to clear.\n";
    exit(0);
}

if (!is_dir($backupDir) && !wp_mkdir_p($backupDir)) {
    echo "Error: cannot create {$backupDir}; nothing changed.\n";
    exit(1);
}

$backup = $backupDir . '/project-content-' . gmdate('Ymd-His') . '.json';
if (file_put_contents($backup, wp_json_encode($clear, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
    echo "Error: could not write backup {$backup}; nothing changed.\n";
    exit(1);
}
echo "Backup: {$backup}\n";

$errors = 0;
foreach (array_keys($clear) as $id) {
    $result = wp_update_post(['ID' => $id, 'post_content' => ''], true);
    if (is_wp_error($result)) {
        $errors++;
        echo "  ✗ #{$id}: " . $result->get_error_message() . "\n";
    }
}

echo sprintf("✓ Cleared %d projects%s.\n", count($clear) - $errors, $errors ? ", {$errors} failed" : '');
exit($errors ? 1 : 0);
