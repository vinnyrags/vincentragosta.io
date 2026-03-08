<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project\Hooks;

use ChildTheme\Providers\Project\ProjectPost;
use ParentTheme\Providers\Contracts\Hook;

/**
 * Extracts the project year from block content and saves it as post meta.
 *
 * Parses the YEAR label followed by a has-30-font-size paragraph to find
 * the year value. Handles multi-year strings like "2018 & 2025" by taking
 * the maximum year.
 */
class ProjectYearExtractor implements Hook
{
    public const META_KEY = 'project_year';

    public function register(): void
    {
        add_action('save_post_project', [$this, 'onSave'], 10, 2);
        add_action('admin_init', [$this, 'maybeBackfill']);
    }

    /**
     * Extract and save project year on post save.
     */
    public function onSave(int $postId, \WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        $year = self::extractYear($post->post_content);

        if ($year !== null) {
            update_post_meta($postId, self::META_KEY, $year);
        }
    }

    /**
     * Extract the project year from block content.
     *
     * Looks for the pattern: <strong>YEAR</strong> followed by
     * <p class="has-30-font-size">VALUE</p>. Extracts all 4-digit years
     * from the value and returns the maximum (handles "2018 & 2025" → "2025").
     */
    public static function extractYear(string $content): ?string
    {
        // Match YEAR label followed by the value paragraph
        $pattern = '/<strong>YEAR<\/strong>.*?<p[^>]*class="[^"]*has-30-font-size[^"]*"[^>]*>(.*?)<\/p>/si';

        if (!preg_match($pattern, $content, $matches)) {
            return null;
        }

        $value = html_entity_decode(strip_tags($matches[1]));

        // Extract all 4-digit years
        if (!preg_match_all('/\b(\d{4})\b/', $value, $yearMatches)) {
            return null;
        }

        $years = array_map('intval', $yearMatches[1]);

        return (string) max($years);
    }

    /**
     * One-time backfill for existing projects missing project_year meta.
     */
    public function maybeBackfill(): void
    {
        if (get_transient('project_year_backfilled')) {
            return;
        }

        $posts = get_posts([
            'post_type'      => ProjectPost::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => self::META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        foreach ($posts as $post) {
            $year = self::extractYear($post->post_content);
            if ($year !== null) {
                update_post_meta($post->ID, self::META_KEY, $year);
            }
        }

        set_transient('project_year_backfilled', true, DAY_IN_SECONDS);
    }
}
