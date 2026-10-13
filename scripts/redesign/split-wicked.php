<?php
/**
 * Split "Wicked The Musical" into its two separate builds.
 *
 * The single Wicked project covered two engagements — the 2018 ground-up
 * rebuild for the 15th anniversary and the 2025 redesign for the film era.
 * This makes them two projects, using the existing write-up divided by era:
 *
 *   - wicked-the-musical       (existing post, URL kept) → "Wicked The Musical (2025)"
 *       year 2025, live URL, current key art (sideloaded from the live site's OG image)
 *   - wicked-the-musical-2018  (new post)                → "Wicked The Musical (2018)"
 *       year 2018, no live URL (that build is retired), classic key art (existing attachment)
 *
 * Run AFTER migrate-project-fields.php (it overwrites the existing post's
 * story fields with the 2025 half). Idempotent: the new post is created once;
 * fields are (re)written on every run, so edits to these two posts should be
 * made here until launch, or the script retired. DRY_RUN=1 prints the plan.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   DRY_RUN=1 ddev wp eval-file scripts/redesign/split-wicked.php
 *   ddev wp eval-file scripts/redesign/split-wicked.php
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/split-wicked.php\n";
    exit(1);
}

$dryRun = !empty(getenv('DRY_RUN'));

const VR_WICKED_SLUG      = 'wicked-the-musical';
const VR_WICKED_2018_SLUG = 'wicked-the-musical-2018';
const VR_WICKED_CLASSIC_ART = 'show_wide_wicked';                 // existing attachment (post_name)
const VR_WICKED_FILM_ART_URL = 'https://d1rx0dtgjk9kr3.cloudfront.net/wp-content/uploads/2026/05/Wicked_OG_Image.jpg';
const VR_WICKED_FILM_ART_NAME = 'wicked-2025-key-art';

const VR_FIELD_KEYS = [
    'project_lead'         => 'field_project_lead',
    'project_lead_note'    => 'field_project_lead_note',
    'project_sections'     => 'field_project_sections',
    'project_client'       => 'field_project_client',
    'project_year'         => 'field_project_year',
    'project_live_url'     => 'field_project_live_url',
    'project_technologies' => 'field_project_technologies',
    'project_case_study'   => 'field_project_case_study',
];

const VR_SECTION_KEYS = [
    'label'   => 'field_project_section_label',
    'heading' => 'field_project_section_heading',
    'body'    => 'field_project_section_body',
    'note'    => 'field_project_section_note',
];

$shared = [
    'project_client'       => 'Situation Interactive',
    'project_technologies' => 'WordPress, PHP, JavaScript, TranslatePress',
    'project_lead_note'    => 'Drove the full development effort at Situation Interactive.',
    'project_case_study'   => '0',
];

$calendar = '<p>The ticket calendar here is the most complex version of a component I have built many times across this portfolio. Where a show like The Outsiders or Maybe Happy Ending needs a calendar for a single venue with one or two ticket tiers, Wicked\'s calendar aggregates performance schedules, pricing tiers, and purchase links across Broadway, multiple national touring companies, and international productions into a single unified interface — with timezone-aware date rendering, dynamic availability states, and deep links to different third-party ticketing providers per market.</p>';

$posts = [
    VR_WICKED_2018_SLUG => [
        'title'   => 'Wicked The Musical (2018)',
        'excerpt' => 'The biggest show in my portfolio, built from the ground up in 2018 for Wicked\'s 15th anniversary. Custom ticket calendar spanning Broadway, touring, and international productions in six languages.',
        'fields'  => $shared + [
            'project_year'     => '2018',
            'project_live_url' => '',
            'project_lead'     => '<p>The biggest show in my portfolio, built from the ground up in 2018 for Wicked\'s 15th anniversary. Custom ticket calendar spanning Broadway, touring, and international productions in six languages.</p>',
        ],
        'sections' => [
            [
                'label'   => 'Background',
                'heading' => 'Broadway\'s biggest franchise needed a digital platform that matched its scale.',
                'body'    => '<p>Every other project in my portfolio operates at a fraction of Wicked\'s scale. Seen by nearly 65 million people worldwide, grossing over $5 billion across Broadway, touring, and international productions in 16 countries and six languages, Wicked is not just a show — it is a global entertainment franchise. By 2018, the show was approaching its 15th anniversary — marked by an NBC primetime special featuring Idina Menzel, Kristin Chenoweth, and Ariana Grande — and the existing website no longer reflected the scale of the brand. Situation Interactive brought me on to rebuild the site from the ground up.</p>',
                'note'    => 'A platform that could serve a global audience buying tickets across Broadway, national tours, and international productions simultaneously.',
            ],
            [
                'label'   => 'Implementation',
                'heading' => 'A custom ticket calendar and multilingual architecture built for a global theatrical brand.',
                'body'    => $calendar . "\n" . '<p>TranslatePress powered the multilingual layer, enabling front-end translation management for international audiences without requiring separate site instances or duplicated content structures.</p>',
                'note'    => 'Built on WordPress with custom theme architecture — structured content managed through dedicated field groups so the marketing team could update show schedules, cast announcements, and promotional campaigns without developer involvement.',
            ],
            [
                'label'   => 'Results',
                'heading' => 'Seven years of continuous operation, zero platform migrations.',
                'body'    => '<p>The 2018 build ran continuously for seven years without structural changes — supporting cast rotations, touring company updates, and marketing campaigns entirely through the CMS. When the film drove a surge in ticket demand in late 2024, the site handled the traffic without incident.</p>',
                'note'    => '',
            ],
        ],
        'art' => 'classic',
    ],
    VR_WICKED_SLUG => [
        'title'   => 'Wicked The Musical (2025)',
        'excerpt' => 'The only show I have built twice. In 2025, a complete redesign unified the stage brand with a $700M+ film franchise. Custom ticket calendar spanning Broadway, touring, and international productions in six languages.',
        'fields'  => $shared + [
            'project_year'     => '2025',
            'project_live_url' => 'https://wickedthemusical.com',
            'project_lead'     => '<p>The only show I have built twice. In 2025, a complete redesign unified the stage brand with a $700M+ film franchise. Custom ticket calendar spanning Broadway, touring, and international productions in six languages.</p>',
        ],
        'sections' => [
            [
                'label'   => 'Background',
                'heading' => 'A film franchise sent Broadway demand to record levels — and the site needed to catch up.',
                'body'    => '<p>Seven years after I rebuilt the site for Wicked\'s 15th anniversary, Universal\'s two-part film adaptation grossed over $700 million at the global box office, creating a cultural moment that sent Broadway ticket demand to record-shattering levels. The site needed a second complete overhaul to unify the stage and screen brands under one digital roof.</p>',
                'note'    => 'Two builds, two different eras of the same franchise — each requiring a platform that could serve a global audience buying tickets across Broadway, national tours, and international productions simultaneously.',
            ],
            [
                'label'   => 'Implementation',
                'heading' => 'Interactive backgrounds and the ticket calendar, rebuilt for the film era.',
                'body'    => $calendar . "\n" . '<p>The 2025 redesign introduced interactive animated backgrounds — canvas-driven visual elements that respond to scroll position and viewport size — bringing the immersive visual language of the film franchise into the browsing experience. The film\'s release created the most intense traffic surge any of my platforms has handled: Wicked posted the first-ever $5 million single-week gross in Broadway history.</p>',
                'note'    => 'Both builds ran on WordPress with custom theme architecture — structured content managed through dedicated field groups so the marketing team could update show schedules, cast announcements, and promotional campaigns without developer involvement.',
            ],
            [
                'label'   => 'Results',
                'heading' => 'Stage and screen under one roof, without disrupting a decade of workflows.',
                'body'    => '<p>The 2025 redesign shipped in coordination with the film franchise\'s visual rebrand, unifying stage and screen identities while preserving the operational workflows the marketing team had relied on for nearly a decade. Wicked is the anchor of my portfolio in a way no other project is — not just the biggest brand, but the longest client relationship and the clearest proof that the platforms I build are designed to last.</p>',
                'note'    => 'Two builds, seven years of continuous operation, a $700M film launch absorbed without incident. The ticket calendar alone serves more markets and languages than most developers\' entire portfolio.',
            ],
        ],
        'art' => 'film',
    ],
];

/**
 * Find an attachment by post_name regardless of its parent (get_page_by_path
 * would require the parent's slug in the path for attached media).
 */
$attachment = static function (string $name): ?WP_Post {
    $found = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'name'           => $name,
        'posts_per_page' => 1,
    ]);
    return $found[0] ?? null;
};

$say = static function (string $line) use ($dryRun): void {
    echo ($dryRun ? '[dry-run] ' : '') . $line . "\n";
};

$original = get_page_by_path(VR_WICKED_SLUG, OBJECT, 'project');
if (!$original) {
    echo "Error: project '" . VR_WICKED_SLUG . "' not found.\n";
    exit(1);
}
if ((string) get_post_meta($original->ID, 'project_client', true) === '') {
    echo "Error: run migrate-project-fields.php first — '" . VR_WICKED_SLUG . "' has no project fields yet.\n";
    exit(1);
}

// Key art.
$classic = $attachment(VR_WICKED_CLASSIC_ART);
if (!$classic) {
    echo "Error: classic key art attachment '" . VR_WICKED_CLASSIC_ART . "' not found.\n";
    exit(1);
}

$film = $attachment(VR_WICKED_FILM_ART_NAME);
if (!$film && !$dryRun) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url(VR_WICKED_FILM_ART_URL);
    if (is_wp_error($tmp)) {
        echo 'Error: could not download film key art: ' . $tmp->get_error_message() . "\n";
        exit(1);
    }
    $filmId = media_handle_sideload(
        ['name' => VR_WICKED_FILM_ART_NAME . '.jpg', 'tmp_name' => $tmp],
        0,
        'Wicked key art (2025)',
        ['post_name' => VR_WICKED_FILM_ART_NAME]
    );
    if (is_wp_error($filmId)) {
        @unlink($tmp);
        echo 'Error: could not import film key art: ' . $filmId->get_error_message() . "\n";
        exit(1);
    }
    $film = get_post($filmId);
    $say("✓ Imported 2025 key art (#{$filmId})");
} elseif ($film) {
    $say("✓ 2025 key art already in media library (#{$film->ID})");
} else {
    $say('Would import 2025 key art from ' . VR_WICKED_FILM_ART_URL);
}

foreach ($posts as $slug => $data) {
    $post = get_page_by_path($slug, OBJECT, 'project');

    if (!$post) {
        if ($dryRun) {
            $say("Would create project \"{$data['title']}\" ({$slug})");
            continue;
        }
        $newId = wp_insert_post([
            'post_type'    => 'project',
            'post_status'  => $original->post_status,
            'post_title'   => $data['title'],
            'post_name'    => $slug,
            'post_excerpt' => $data['excerpt'],
            'post_date'    => $original->post_date,
            'post_author'  => $original->post_author,
        ], true);
        if (is_wp_error($newId)) {
            echo "Error: could not create {$slug}: " . $newId->get_error_message() . "\n";
            exit(1);
        }
        wp_set_post_categories($newId, wp_get_post_categories($original->ID));
        $post = get_post($newId);
        $say("✓ Created project \"{$data['title']}\" (#{$newId})");
    } elseif ($dryRun) {
        $say("Would update project \"{$data['title']}\" (#{$post->ID})");
        continue;
    } else {
        wp_update_post(['ID' => $post->ID, 'post_title' => $data['title'], 'post_excerpt' => $data['excerpt']]);
        $say("✓ Updated project \"{$data['title']}\" (#{$post->ID})");
    }

    foreach ($data['fields'] as $field => $value) {
        update_post_meta($post->ID, $field, $value);
        update_post_meta($post->ID, '_' . $field, VR_FIELD_KEYS[$field]);
    }

    $previous = (int) get_post_meta($post->ID, 'project_sections', true);
    for ($i = 0; $i < $previous; $i++) {
        foreach (array_keys(VR_SECTION_KEYS) as $sub) {
            delete_post_meta($post->ID, "project_sections_{$i}_{$sub}");
            delete_post_meta($post->ID, "_project_sections_{$i}_{$sub}");
        }
    }
    foreach ($data['sections'] as $i => $section) {
        foreach (VR_SECTION_KEYS as $sub => $fieldKey) {
            update_post_meta($post->ID, "project_sections_{$i}_{$sub}", $section[$sub]);
            update_post_meta($post->ID, "_project_sections_{$i}_{$sub}", $fieldKey);
        }
    }
    update_post_meta($post->ID, 'project_sections', count($data['sections']));
    update_post_meta($post->ID, '_project_sections', VR_FIELD_KEYS['project_sections']);

    $artId = $data['art'] === 'film' ? (int) $film->ID : (int) $classic->ID;
    set_post_thumbnail($post->ID, $artId);
    $say("  ↳ fields, 3 sections, key art #{$artId}");
}

if (!$dryRun) {
    wp_cache_flush();
}

echo "Done.\n";
exit(0);
