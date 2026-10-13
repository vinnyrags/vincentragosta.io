<?php
/**
 * Migrate project content and metadata from post content into the "Project" ACF fields.
 *
 * Projects move off the block editor entirely: the detail page renders from
 * the "Project" ACF group. Every project's content was built from the same
 * pattern, so this parses (via parse_blocks) and writes:
 *
 *   - Details: CLIENT / TECHNOLOGIES / YEAR / EXTERNAL URL spec list →
 *     project_client, project_technologies, project_year, project_live_url;
 *     case-study flags (and stated roles) for the six featured builds
 *   - Overview: intro column → project_lead (paragraphs) + project_lead_note
 *     (the muted line)
 *   - Sections: each anchored section group (Background / Implementation /
 *     Results) → a project_sections repeater row: label, heading (the bold
 *     lede), body (regular paragraphs), note (the muted closing line)
 *
 * Additive only — post content is not touched (it is cleared at cutover,
 * after a backup). Meta is written raw, in ACF's own storage shape (value +
 * `_name` → field-key reference), so this is safe to run on the pre-redesign
 * theme, which doesn't load the field group.
 *
 * Nothing is guessed: values that can't be parsed are left empty and flagged.
 * Role and status label are only set where stated below; everything else is
 * for editing in the CMS.
 *
 * DRY_RUN=1 writes nothing and produces a CSV report (one row per project) at
 * $CSV (default: <system tmp>/project-fields.csv). Review it before writing.
 * Re-runs skip projects that already have a client unless FORCE=1.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   ddev exec "DRY_RUN=1 CSV=/var/www/html/tmp/project-fields.csv wp eval-file scripts/redesign/migrate-project-fields.php"
 *   ddev wp eval-file scripts/redesign/migrate-project-fields.php
 *
 * Remote:
 *   $(call remote-wp-eval-with-env,STAGING,redesign/migrate-project-fields.php,DRY_RUN=1)
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/migrate-project-fields.php\n";
    exit(1);
}

$force   = !empty(getenv('FORCE'));
$dryRun  = !empty(getenv('DRY_RUN'));
$csvPath = getenv('CSV') ?: sys_get_temp_dir() . '/project-fields.csv';

/** Labels in content → field name. */
const VR_LABEL_FIELDS = [
    'CLIENT'       => 'project_client',
    'TECHNOLOGIES' => 'project_technologies',
    'YEAR'         => 'project_year',
    'EXTERNAL URL' => 'project_live_url',
];

/**
 * Case studies: slug => extra fields. Role only where the case study itself
 * states it; status labels are left for the CMS.
 */
const VR_CASE_STUDIES = [
    'celebrity-autobiography' => ['project_role' => 'Design + full-stack'],
    'itzenzo-tv'              => [],
    'a-view-from-the-bridge'  => [],
    'matchbook-festival'      => [],
    'shucked'                 => [],
    'ellen-harvey'            => [],
];

/** Field-key reference for each field (ACF's `_name` meta). */
const VR_FIELD_KEYS = [
    'project_lead'         => 'field_project_lead',
    'project_lead_note'    => 'field_project_lead_note',
    'project_sections'     => 'field_project_sections',
    'project_client'       => 'field_project_client',
    'project_role'         => 'field_project_role',
    'project_year'         => 'field_project_year',
    'project_live_url'     => 'field_project_live_url',
    'project_technologies' => 'field_project_technologies',
    'project_case_study'   => 'field_project_case_study',
];

/** Repeater sub field keys. */
const VR_SECTION_KEYS = [
    'label'   => 'field_project_section_label',
    'heading' => 'field_project_section_heading',
    'body'    => 'field_project_section_body',
    'note'    => 'field_project_section_note',
];

/** Legacy meta from a deleted field group, compared for the report only. */
const VR_LEGACY_META = [
    'client'       => 'project_client',
    'technologies' => 'project_technologies',
    'year'         => 'project_year',
    'external_url' => 'project_live_url',
];

/**
 * Pull the raw inner HTML of the value paragraph that follows a label.
 */
function vr_spec_value(string $content, string $label): ?string
{
    $pattern = '/<strong>' . preg_quote($label, '/') . '<\/strong>.*?<p[^>]*class="[^"]*has-30-font-size[^"]*"[^>]*>(.*?)<\/p>/si';

    return preg_match($pattern, $content, $m) ? trim($m[1]) : null;
}

/**
 * Normalize a raw spec value for its field. Returns [value, warning|null].
 *
 * @return array{0: string, 1: ?string}
 */
function vr_normalize(string $field, ?string $raw): array
{
    if ($raw === null) {
        return ['', 'label not found'];
    }

    switch ($field) {
        case 'project_live_url':
            if (preg_match('/href="([^"]*)"/i', $raw, $m)) {
                $url = html_entity_decode($m[1], ENT_QUOTES);
            } else {
                $url = trim(wp_strip_all_tags($raw));
            }
            $url = trim($url);
            if ($url === '' || $url === '#' || preg_match('#^https?://\#?$#', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return ['', "no usable URL (\"{$url}\")"];
            }
            return [esc_url_raw($url), null];

        case 'project_year':
            // Same rule the retired ProjectYearExtractor used: the latest 4-digit year.
            preg_match_all('/\b(19|20)\d{2}\b/', wp_strip_all_tags($raw), $m);
            if (empty($m[0])) {
                return ['', 'no year found'];
            }
            $years = array_map('intval', $m[0]);
            $year  = (string) max($years);
            return [$year, count(array_unique($years)) > 1 ? 'multiple years (' . implode(', ', $m[0]) . '), used ' . $year : null];

        case 'project_technologies':
            $text  = html_entity_decode(wp_strip_all_tags($raw), ENT_QUOTES);
            $items = array_filter(array_map('trim', explode(',', $text)), 'strlen');
            return $items ? [implode(', ', $items), null] : ['', 'empty'];

        default:
            $text = trim(html_entity_decode(wp_strip_all_tags($raw), ENT_QUOTES));
            return $text !== '' ? [$text, null] : ['', 'empty'];
    }
}

/**
 * Depth-first list of every block in a tree.
 *
 * @return array<int, array>
 */
function vr_flatten(array $blocks): array
{
    $flat = [];
    foreach ($blocks as $block) {
        if (empty($block['blockName'])) {
            continue;
        }
        $flat[] = $block;
        $flat   = array_merge($flat, vr_flatten($block['innerBlocks'] ?? []));
    }
    return $flat;
}

function vr_is_muted(array $block): bool
{
    return str_contains((string) ($block['attrs']['className'] ?? ''), 'is-style-muted');
}

/**
 * A paragraph block's HTML, normalized to a bare <p>…</p> (classes/styles dropped).
 */
function vr_paragraph_html(array $block): string
{
    $html = trim((string) $block['innerHTML']);
    return preg_replace('/^<p\b[^>]*>/i', '<p>', $html) ?? $html;
}

/**
 * A block's inner text with inline tags kept (a, em, strong, code) — for notes.
 */
function vr_inline_text(array $block): string
{
    $html = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', trim((string) $block['innerHTML'])) ?? '';
    return trim(wp_kses($html, ['a' => ['href' => true, 'target' => true, 'rel' => true], 'em' => [], 'strong' => [], 'code' => []]));
}

/**
 * A heading block's plain text (strips <strong>, underline spans, entities).
 */
function vr_heading_text(array $block): string
{
    return trim(html_entity_decode(wp_strip_all_tags((string) $block['innerHTML']), ENT_QUOTES));
}

/**
 * Split a column's paragraphs into [regular paragraphs HTML, muted note text].
 *
 * @return array{0: string, 1: string}
 */
function vr_split_paragraphs(array $blocks): array
{
    $body = [];
    $note = [];
    foreach ($blocks as $block) {
        if (($block['blockName'] ?? '') !== 'core/paragraph') {
            continue;
        }
        if (vr_is_muted($block)) {
            $note[] = vr_inline_text($block);
        } else {
            $body[] = vr_paragraph_html($block);
        }
    }
    return [implode("\n", $body), implode(' ', array_filter($note))];
}

/**
 * Extract lead, lead note, and sections from a project's block content.
 *
 * @return array{lead: string, lead_note: string, sections: array<int, array{label: string, heading: string, body: string, note: string}>, warnings: string[]}
 */
function vr_extract_story(string $content): array
{
    $blocks   = parse_blocks($content);
    $warnings = [];

    // Intro: the first column made only of paragraphs that isn't the spec list.
    $lead = $leadNote = '';
    foreach (vr_flatten($blocks) as $block) {
        if ($block['blockName'] !== 'core/column' || empty($block['innerBlocks'])) {
            continue;
        }
        $names = array_unique(array_column($block['innerBlocks'], 'blockName'));
        if ($names !== ['core/paragraph'] || str_contains(serialize_blocks([$block]), '<strong>CLIENT</strong>')) {
            continue;
        }
        [$lead, $leadNote] = vr_split_paragraphs($block['innerBlocks']);
        break;
    }
    if ($lead === '') {
        $warnings[] = 'lead not found';
    }

    // Sections: anchored top-level groups — [label column | heading + paragraphs column].
    $sections = [];
    foreach ($blocks as $block) {
        if (($block['blockName'] ?? '') !== 'core/group' || !preg_match('/\bid="([a-z0-9-]+)"/', (string) $block['innerHTML'])) {
            continue;
        }
        $columns = null;
        foreach ($block['innerBlocks'] as $inner) {
            if ($inner['blockName'] === 'core/columns') {
                $columns = $inner;
                break;
            }
        }
        if (!$columns || count($columns['innerBlocks']) < 2) {
            continue;
        }
        [$labelCol, $contentCol] = $columns['innerBlocks'];

        $label = '';
        foreach ($labelCol['innerBlocks'] as $inner) {
            if ($inner['blockName'] === 'core/heading') {
                $label = vr_heading_text($inner);
                break;
            }
        }

        $heading = '';
        $rest    = [];
        foreach ($contentCol['innerBlocks'] as $inner) {
            if ($heading === '' && $inner['blockName'] === 'core/heading') {
                $heading = vr_heading_text($inner);
                continue;
            }
            $rest[] = $inner;
        }
        [$body, $note] = vr_split_paragraphs($rest);

        if ($label === '' || $heading === '' || $body === '') {
            $warnings[] = 'incomplete section "' . ($label ?: '?') . '"';
        }

        $sections[] = compact('label', 'heading', 'body', 'note');
    }

    if (count($sections) !== 3) {
        $warnings[] = count($sections) . ' sections (expected 3)';
    }

    return ['lead' => $lead, 'lead_note' => $leadNote, 'sections' => $sections, 'warnings' => $warnings];
}

$projects = get_posts([
    'post_type'        => 'project',
    'post_status'      => 'any',
    'posts_per_page'   => -1,
    'orderby'          => 'ID',
    'order'            => 'ASC',
    'suppress_filters' => true,
]);

if (!$projects) {
    echo "Error: no projects found.\n";
    exit(1);
}

$rows     = [];
$written  = 0;
$skipped  = 0;
$warnings = 0;

foreach ($projects as $post) {
    // Posts created after the move to fields (e.g. split-wicked.php's 2018
    // post) have no legacy content to parse — never let a FORCE run blank them.
    if (trim($post->post_content) === '') {
        $rows[] = [$post->ID, $post->post_name, html_entity_decode(get_the_title($post), ENT_QUOTES), $post->post_status,
            '', '', '', '', '', '', '', '', '', '', '', 'skip (no legacy content)', ''];
        $skipped++;
        continue;
    }

    $values = [];
    $notes  = [];

    foreach (VR_LABEL_FIELDS as $label => $field) {
        [$value, $warning] = vr_normalize($field, vr_spec_value($post->post_content, $label));
        $values[$field] = $value;
        if ($warning) {
            $notes[] = strtolower($label) . ': ' . $warning;
        }
    }

    $story = vr_extract_story($post->post_content);
    $values['project_lead']      = $story['lead'];
    $values['project_lead_note'] = $story['lead_note'];
    $sections                    = $story['sections'];
    foreach ($story['warnings'] as $warning) {
        $notes[] = $warning;
    }

    // Cross-checks (report only).
    $existingYear = (string) get_post_meta($post->ID, 'project_year', true);
    if ($existingYear !== '' && $values['project_year'] !== '' && $existingYear !== $values['project_year']) {
        $notes[] = "year differs from current project_year meta ({$existingYear})";
    }
    foreach (VR_LEGACY_META as $legacyKey => $field) {
        $legacy = trim((string) get_post_meta($post->ID, $legacyKey, true));
        if ($legacy !== '' && $legacy !== $values[$field]) {
            $notes[] = "legacy '{$legacyKey}' meta disagrees (\"{$legacy}\") — content used";
        }
    }

    $isCaseStudy = array_key_exists($post->post_name, VR_CASE_STUDIES);
    $values['project_case_study'] = $isCaseStudy ? '1' : '0';
    foreach ($isCaseStudy ? VR_CASE_STUDIES[$post->post_name] : [] as $field => $value) {
        $values[$field] = $value;
    }

    $alreadyMigrated = (string) get_post_meta($post->ID, 'project_client', true) !== '';
    $action = $alreadyMigrated && !$force ? 'skip (already migrated)' : ($dryRun ? 'would write' : 'written');

    if ($notes) {
        $warnings++;
    }

    if (!$dryRun && (!$alreadyMigrated || $force)) {
        foreach ($values as $field => $value) {
            update_post_meta($post->ID, $field, $value);
            update_post_meta($post->ID, '_' . $field, VR_FIELD_KEYS[$field]);
        }

        // Repeater: clear any previous rows (FORCE re-runs), then write rows + count.
        $previous = (int) get_post_meta($post->ID, 'project_sections', true);
        for ($i = 0; $i < $previous; $i++) {
            foreach (array_keys(VR_SECTION_KEYS) as $sub) {
                delete_post_meta($post->ID, "project_sections_{$i}_{$sub}");
                delete_post_meta($post->ID, "_project_sections_{$i}_{$sub}");
            }
        }
        foreach ($sections as $i => $section) {
            foreach (VR_SECTION_KEYS as $sub => $fieldKey) {
                update_post_meta($post->ID, "project_sections_{$i}_{$sub}", $section[$sub]);
                update_post_meta($post->ID, "_project_sections_{$i}_{$sub}", $fieldKey);
            }
        }
        update_post_meta($post->ID, 'project_sections', count($sections));
        update_post_meta($post->ID, '_project_sections', VR_FIELD_KEYS['project_sections']);

        $written++;
    } elseif ($alreadyMigrated && !$force) {
        $skipped++;
    }

    $rows[] = [
        $post->ID,
        $post->post_name,
        html_entity_decode(get_the_title($post), ENT_QUOTES),
        $post->post_status,
        $values['project_client'],
        $values['project_year'],
        $values['project_technologies'],
        $values['project_live_url'],
        $values['project_case_study'] === '1' ? 'yes' : '',
        $values['project_role'] ?? '',
        wp_trim_words(wp_strip_all_tags($values['project_lead']), 12),
        $values['project_lead_note'] !== '' ? 'yes' : '',
        implode(' / ', array_column($sections, 'label')),
        implode(' | ', array_map(static fn($s) => $s['heading'], $sections)),
        implode('/', array_map(static fn($s) => $s['note'] !== '' ? 'note' : '-', $sections)),
        $action,
        implode(' | ', $notes),
    ];
}

$csv = fopen($csvPath, 'w');
if ($csv === false) {
    echo "Error: can't write report to {$csvPath}\n";
    exit(1);
}
fputcsv($csv, ['id', 'slug', 'title', 'status', 'client', 'year', 'technologies', 'live_url', 'case_study', 'role', 'lead (start)', 'lead_note', 'sections', 'section headings', 'section notes', 'action', 'warnings']);
foreach ($rows as $row) {
    fputcsv($csv, $row);
}
fclose($csv);

printf(
    "%s%d projects: %d written, %d skipped, %d with warnings. Report: %s\n",
    $dryRun ? '[dry-run] ' : '',
    count($projects),
    $written,
    $skipped,
    $warnings,
    $csvPath
);

if (!$dryRun && $written) {
    wp_cache_flush();
}

exit(0);
