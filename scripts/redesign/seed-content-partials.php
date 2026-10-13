<?php
/**
 * Seed the 2026-redesign header/footer content partials.
 *
 * Creates (or updates, with FORCE=1) the IX ContentPartial posts that render
 * the site chrome, plus the wp_navigation menu the header references:
 *
 *   - wp_navigation  "Primary Navigation"   (content/partials/navigation.html)
 *   - content-partial "Header"              (header.html)          default header
 *   - content-partial "Footer"              (footer.html)          default footer
 *   - content-partial "Footer — Compact"    (footer-compact.html)  no CTA panel
 *
 * and points the Contact page at the compact footer (its contact form already
 * does the CTA panel's job).
 *
 * Safe to run BEFORE the redesign code is deployed: if the ContentPartial
 * post type / taxonomy aren't registered yet (old theme), it registers them
 * for this process only and writes raw meta, so the posts are in place the
 * moment the new code goes live. The old theme ignores them.
 *
 * Idempotent: existing posts (matched by slug) are left alone unless FORCE=1,
 * so CMS edits made after seeding are never clobbered by a re-run.
 * DRY_RUN=1 prints what would happen and writes nothing.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   ddev wp eval-file scripts/redesign/seed-content-partials.php
 *   DRY_RUN=1 ddev wp eval-file scripts/redesign/seed-content-partials.php
 *   ddev exec "FORCE=1 wp eval-file scripts/redesign/seed-content-partials.php"
 *
 * Remote:
 *   $(call remote-wp-eval-with-env,STAGING,redesign/seed-content-partials.php,DRY_RUN=1)
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/seed-content-partials.php\n";
    exit(1);
}

const VR_PARTIAL_POST_TYPE = 'content-partial';
const VR_PARTIAL_TAXONOMY  = 'partial-type';
const VR_CONTENT_DIR       = __DIR__ . '/content/partials/';

$force  = !empty(getenv('FORCE'));
$dryRun = !empty(getenv('DRY_RUN'));
$errors = 0;

/**
 * Print a line, prefixed in dry-run mode.
 */
$say = static function (string $line) use ($dryRun): void {
    echo ($dryRun ? '[dry-run] ' : '') . $line . "\n";
};

/**
 * Read a content file or abort.
 */
$read = static function (string $file): string {
    $path = VR_CONTENT_DIR . $file;
    if (!is_readable($path)) {
        echo "Error: missing content file {$path}\n";
        exit(1);
    }
    return trim((string) file_get_contents($path)) . "\n";
};

// Pre-deploy safety: register the CPT + taxonomy for this process if the
// running theme doesn't (ContentPartial is off in the pre-redesign theme).
if (!post_type_exists(VR_PARTIAL_POST_TYPE)) {
    register_post_type(VR_PARTIAL_POST_TYPE, ['public' => false, 'show_in_rest' => true]);
    $say('Note: content-partial not registered by the active theme — registered for this run.');
}
if (!taxonomy_exists(VR_PARTIAL_TAXONOMY)) {
    register_taxonomy(VR_PARTIAL_TAXONOMY, VR_PARTIAL_POST_TYPE, ['public' => false, 'hierarchical' => true]);
}

/**
 * Find a post by slug + type (any status), or null.
 */
$find = static function (string $slug, string $type): ?WP_Post {
    $posts = get_posts([
        'name'             => $slug,
        'post_type'        => $type,
        'post_status'      => 'any',
        'posts_per_page'   => 1,
        'suppress_filters' => true,
    ]);
    return $posts[0] ?? null;
};

/**
 * Create the post, or overwrite its content when FORCE=1. Returns
 * [post ID (0 in dry-run when it doesn't exist yet), whether it was written].
 *
 * @return array{0: int, 1: bool}
 */
$upsert = static function (string $slug, string $type, string $title, string $content) use ($find, $force, $dryRun, $say, &$errors): array {
    $existing = $find($slug, $type);

    if ($existing && !$force) {
        $say("✓ {$type} \"{$title}\" exists (#{$existing->ID}) — left as is (FORCE=1 to overwrite)");
        return [$existing->ID, false];
    }

    $verb = $existing ? 'Overwrote' : 'Created';
    if ($dryRun) {
        $say("{$verb} {$type} \"{$title}\"" . ($existing ? " (#{$existing->ID})" : ''));
        return [$existing->ID ?? 0, true];
    }

    $postarr = [
        'post_type'    => $type,
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_name'    => $slug,
        // wp_insert_post unslashes; slash so block-comment JSON survives intact.
        'post_content' => wp_slash($content),
    ];
    if ($existing) {
        $postarr['ID'] = $existing->ID;
    }

    $id = wp_insert_post($postarr, true);
    if (is_wp_error($id)) {
        echo "Error: {$type} \"{$title}\": " . $id->get_error_message() . "\n";
        $errors++;
        return [0, false];
    }

    $say("✓ {$verb} {$type} \"{$title}\" (#{$id})");
    return [(int) $id, true];
};

/**
 * Write an ACF value as raw meta (value + field-key reference) so it works
 * whether or not the field group is loaded in this process.
 */
$setField = static function (int $postId, string $name, string $fieldKey, string $value) use ($dryRun): void {
    if ($dryRun || $postId === 0) {
        return;
    }
    update_post_meta($postId, $name, $value);
    update_post_meta($postId, '_' . $name, $fieldKey);
};

// 1. Navigation menu referenced by the header partial.
[$navId] = $upsert('primary-navigation', 'wp_navigation', 'Primary Navigation', $read('navigation.html'));

// 2. Partials.
$partials = [
    ['header',         'Header',           'header.html',         'header', true],
    ['footer',         'Footer',           'footer.html',         'footer', true],
    ['footer-compact', 'Footer — Compact', 'footer-compact.html', 'footer', false],
];

$ids = [];
$wroteCompact = false;
foreach ($partials as [$slug, $title, $file, $term, $isDefault]) {
    $content = str_replace('{{NAV_REF}}', (string) $navId, $read($file));
    [$id, $wrote] = $upsert($slug, VR_PARTIAL_POST_TYPE, $title, $content);
    $ids[$slug] = $id;
    if ($slug === 'footer-compact') {
        $wroteCompact = $wrote;
    }

    // Flags and terms are only (re)applied when this run wrote the post, so a
    // re-run never undoes an editor's later choice of default.
    if (!$wrote) {
        continue;
    }

    if (!$dryRun && $id) {
        if (!term_exists($term, VR_PARTIAL_TAXONOMY)) {
            wp_insert_term(ucfirst($term), VR_PARTIAL_TAXONOMY, ['slug' => $term]);
        }
        wp_set_object_terms($id, $term, VR_PARTIAL_TAXONOMY);
    }

    if ($isDefault) {
        $setField($id, 'is_default', 'field_content_partial_is_default', '1');
        $say("  ↳ default {$term} partial");
    }
}

// 3. Contact page → compact footer (only alongside a freshly written compact partial).
$contact = get_page_by_path('contact');
if (!$wroteCompact) {
    $say('✓ Contact page override left as is');
} elseif (!$contact) {
    echo "Error: no page at /contact/ — skipped the compact-footer override.\n";
    $errors++;
} else {
    $setField($contact->ID, 'footer_mode', 'field_partial_overrides_footer_mode', 'custom');
    $setField($contact->ID, 'footer_partial', 'field_partial_overrides_footer_partial', (string) $ids['footer-compact']);
    $say("✓ Contact page (#{$contact->ID}) footer → Footer — Compact");
}

if (!$dryRun) {
    wp_cache_flush();
}

echo $errors ? "Finished with {$errors} error(s).\n" : "Done.\n";
exit($errors ? 1 : 0);
