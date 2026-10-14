<?php
/**
 * Migrate Nous Signal posts to the redesigned single-post template.
 *
 * Every legacy post is a full-width hero group (a columns block holding the
 * "Back To Nous" button, the title heading, a "Date:" / time-to-read row and
 * the tag list, then the intro paragraph) followed by full-width section
 * groups (an underlined H2 + paragraphs) divided by wide separators. The new
 * template renders the title, date, tags, read time and the way back itself,
 * so each post becomes:
 *
 *   intro paragraph
 *   Section group per section — the H2 un-underlined and given an anchor
 *   (the Contents card lists anchored H2s), paragraphs verbatim
 *   the last section ("What This Means") as an accent Panel instead
 *
 * Only an exact match of that structure is rewritten; anything else is
 * skipped and reported. Already-migrated posts are recognized and skipped,
 * so the script is safe to re-run.
 *
 * DRY_RUN=1 writes nothing to the database: it writes a CSV report to
 * REPORT (default: {BACKUP_DIR}/nous-migration-{timestamp}.csv) and one sample
 * rewritten post beside it. A real run first writes every original
 * post_content to {BACKUP_DIR}/nous-posts-{timestamp}.json.
 * POSTS=12,34 limits the run.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   ddev exec "DRY_RUN=1 BACKUP_DIR=/var/www/html/tmp wp eval-file scripts/redesign/migrate-nous-posts.php"
 *   ddev exec "BACKUP_DIR=/var/www/html/tmp wp eval-file scripts/redesign/migrate-nous-posts.php"
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/migrate-nous-posts.php\n";
    exit(1);
}

$dryRun    = !empty(getenv('DRY_RUN'));
$backupDir = rtrim(getenv('BACKUP_DIR') ?: sys_get_temp_dir(), '/');
$stamp     = gmdate('Ymd-His');
$report    = getenv('REPORT') ?: "{$backupDir}/nous-migration-{$stamp}.csv";
$only      = array_filter(array_map('intval', explode(',', (string) getenv('POSTS'))));
$prefix    = $dryRun ? '[dry-run] ' : '';

/** The legacy hero's first child, as a block-name signature. */
const NOUS_LEGACY_HEADER = 'core/columns[core/column[core/buttons[core/button],core/heading,core/group[core/paragraph,core/post-time-to-read],core/post-terms],core/column]';

/** A legacy section heading: an H2 wrapping the text in an underline span. */
const NOUS_LEGACY_H2 = '#^<h2 class="wp-block-heading"><span style="text-decoration: underline;">(.+)</span></h2>$#s';

function nous_signature(array $block): string
{
    $inner = array_values(array_filter($block['innerBlocks'], static fn(array $b): bool => !empty($b['blockName'])));

    return $block['blockName'] . ($inner ? '[' . implode(',', array_map('nous_signature', $inner)) . ']' : '');
}

/**
 * @return array<int, array<string, mixed>> Named blocks only.
 */
function nous_named(array $blocks): array
{
    return array_values(array_filter($blocks, static fn(array $b): bool => !empty($b['blockName'])));
}

/**
 * A paragraph block re-serialized in the standard compact form, its HTML verbatim.
 */
function nous_paragraph(array $block): string
{
    $html = trim((string) $block['innerHTML']);
    $block['innerHTML'] = "\n{$html}\n";
    $block['innerContent'] = ["\n{$html}\n"];

    return serialize_block($block);
}

/**
 * Rewrite one legacy post. Returns [content, sections, anchors] or a skip reason.
 *
 * @return array{0: string, 1: int, 2: string[]}|string
 */
function nous_migrate(string $content)
{
    $blocks = nous_named(parse_blocks($content));

    if (!$blocks) {
        return 'empty content';
    }

    if ($blocks[0]['blockName'] === 'core/paragraph' && str_contains($content, 'is-style-section')) {
        return 'already migrated';
    }

    // Hero group: the legacy header, then exactly one intro paragraph.
    $hero = $blocks[0];
    $heroInner = nous_named($hero['innerBlocks']);

    if ($hero['blockName'] !== 'core/group' || count($heroInner) !== 2) {
        return 'hero group shape differs';
    }

    if (nous_signature($heroInner[0]) !== NOUS_LEGACY_HEADER) {
        return 'legacy header signature differs';
    }

    if ($heroInner[1]['blockName'] !== 'core/paragraph') {
        return 'intro is not a paragraph';
    }

    // Then (separator, section group) pairs.
    $rest = array_slice($blocks, 1);

    if (!$rest || count($rest) % 2 !== 0) {
        return 'separator/section pairing differs';
    }

    $sections = [];
    foreach (array_chunk($rest, 2) as [$separator, $group]) {
        if ($separator['blockName'] !== 'core/separator' || $group['blockName'] !== 'core/group') {
            return 'separator/section pairing differs';
        }

        $inner = nous_named($group['innerBlocks']);
        $heading = $inner[0] ?? null;

        if (
            !$heading
            || $heading['blockName'] !== 'core/heading'
            || (int) ($heading['attrs']['level'] ?? 2) !== 2
            || !empty(array_diff(array_keys($heading['attrs']), ['level']))
            || preg_match(NOUS_LEGACY_H2, trim((string) $heading['innerHTML']), $m) !== 1
        ) {
            return 'section heading differs';
        }

        $paragraphs = array_slice($inner, 1);

        if (!$paragraphs || array_filter($paragraphs, static fn(array $b): bool => $b['blockName'] !== 'core/paragraph')) {
            return 'section body is not only paragraphs';
        }

        $sections[] = ['heading' => $m[1], 'paragraphs' => $paragraphs];
    }

    // Build the new content.
    $anchors = [];
    $out = [nous_paragraph($heroInner[1])];
    $last = count($sections) - 1;

    foreach ($sections as $index => $section) {
        $base = sanitize_title(wp_strip_all_tags($section['heading'])) ?: 'section';
        $anchor = $base;
        for ($n = 2; in_array($anchor, $anchors, true); $n++) {
            $anchor = "{$base}-{$n}";
        }
        $anchors[] = $anchor;

        $class = $index === $last ? 'is-style-panel is-accent' : 'is-style-section';
        $inner = ["<!-- wp:heading -->\n<h2 class=\"wp-block-heading\" id=\"{$anchor}\">{$section['heading']}</h2>\n<!-- /wp:heading -->"];
        foreach ($section['paragraphs'] as $paragraph) {
            $inner[] = nous_paragraph($paragraph);
        }

        $out[] = "<!-- wp:group {\"className\":\"{$class}\",\"layout\":{\"type\":\"default\"}} -->\n"
            . "<div class=\"wp-block-group {$class}\">" . implode("\n\n", $inner) . "</div>\n"
            . '<!-- /wp:group -->';
    }

    return [implode("\n\n", $out) . "\n", count($sections), $anchors];
}

$posts = get_posts([
    'post_type'   => 'post',
    'post_status' => 'any',
    'numberposts' => -1,
    'orderby'     => 'date',
    'order'       => 'ASC',
    'include'     => $only,
]);

if (!is_dir($backupDir) && !wp_mkdir_p($backupDir)) {
    echo "Error: cannot create {$backupDir}\n";
    exit(1);
}

$rows = [];
$updates = [];
$counts = [];

foreach ($posts as $post) {
    $result = nous_migrate($post->post_content);
    $row = [
        'id'      => $post->ID,
        'date'    => substr($post->post_date, 0, 10),
        'slug'    => $post->post_name,
        'status'  => is_array($result) ? 'migrate' : 'skip: ' . $result,
        'sections' => is_array($result) ? $result[1] : '',
        'anchors' => is_array($result) ? implode(' ', $result[2]) : '',
        'bytes_before' => strlen($post->post_content),
        'bytes_after'  => is_array($result) ? strlen($result[0]) : '',
    ];
    $rows[] = $row;
    $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;

    if (is_array($result)) {
        $updates[$post->ID] = ['before' => $post->post_content, 'after' => $result[0]];
    }
}

// Report.
$fh = fopen($report, 'w');
fputcsv($fh, array_keys($rows[0] ?? ['id' => '']));
foreach ($rows as $row) {
    fputcsv($fh, $row);
}
fclose($fh);
echo "{$prefix}Report: {$report}\n";

foreach ($counts as $status => $count) {
    echo "{$prefix}  {$count} × {$status}\n";
}

if ($dryRun) {
    if ($updates) {
        $sampleId = array_key_last($updates);
        $sample = preg_replace('/\.csv$/', '', $report) . "-sample-{$sampleId}.html";
        file_put_contents($sample, $updates[$sampleId]['after']);
        echo "{$prefix}Sample rewrite (post #{$sampleId}): {$sample}\n";
    }
    echo "{$prefix}Done. Nothing written to the database.\n";
    exit(0);
}

if (!$updates) {
    echo "Nothing to migrate.\n";
    exit(0);
}

// Back up every post about to change, then write.
$backup = "{$backupDir}/nous-posts-{$stamp}.json";
$saved = file_put_contents($backup, wp_json_encode(array_map(static fn(array $u): string => $u['before'], $updates), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

if ($saved === false) {
    echo "Error: could not write backup {$backup}; nothing changed.\n";
    exit(1);
}

echo "Backup: {$backup}\n";

$errors = 0;
foreach ($updates as $id => $update) {
    // wp_update_post unslashes its input; slash it so block-comment JSON
    // (e.g. escaped quotes) survives intact.
    $result = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $update['after']]), true);

    if (is_wp_error($result)) {
        $errors++;
        echo "  ✗ #{$id}: " . $result->get_error_message() . "\n";
    }
}

echo sprintf("✓ Migrated %d posts%s.\n", count($updates) - $errors, $errors ? ", {$errors} failed" : '');
exit($errors ? 1 : 0);
