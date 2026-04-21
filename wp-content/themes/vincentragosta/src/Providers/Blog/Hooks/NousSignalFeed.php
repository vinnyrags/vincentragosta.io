<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Blog\Hooks;

use Mythus\Contracts\Hook;

/**
 * Brand the main RSS feed as "Nous Signal" and strip the project-details-style
 * hero block from each item so feed readers render clean article text.
 *
 * The main WordPress RSS feed at /feed/ is posts-only by default, which matches
 * the Nous Signal content model exactly — no separate feed URL needed.
 */
class NousSignalFeed implements Hook
{
    private const TITLE = 'Nous Signal';
    private const DESCRIPTION = 'Pattern-recognition intelligence observing the AI industry. Daily signals from Nous.';

    public function register(): void
    {
        add_filter('wp_title_rss', [$this, 'filterTitle']);
        add_filter('get_wp_title_rss', [$this, 'filterTitle']);
        add_filter('bloginfo_rss', [$this, 'filterBloginfo'], 10, 2);
        // Strip the hero BEFORE blocks render — do_blocks runs at priority 9.
        add_filter('the_content', [$this, 'stripHeroBlock'], 8);
        add_filter('the_excerpt_rss', [$this, 'cleanExcerpt']);
    }

    /**
     * Override the feed title tag.
     */
    public function filterTitle(string $title): string
    {
        return self::TITLE;
    }

    /**
     * Override name and description in the channel metadata.
     */
    public function filterBloginfo(string $value, string $show): string
    {
        if ($show === 'name') {
            return self::TITLE;
        }

        if ($show === 'description') {
            return self::DESCRIPTION;
        }

        return $value;
    }

    /**
     * Strip the first wp:group block (hero: back button, title, date, tags)
     * from each feed item's raw block markup. Runs before do_blocks (priority 9)
     * so we operate on the block comments, not the rendered HTML.
     *
     * Only active in feed context — single/archive views render untouched.
     */
    public function stripHeroBlock(string $content): string
    {
        if (!is_feed()) {
            return $content;
        }

        $blocks = parse_blocks($content);

        if (empty($blocks)) {
            return $content;
        }

        // Find the first non-null block (skip whitespace-only entries between blocks)
        $firstIndex = null;
        foreach ($blocks as $i => $block) {
            if ($block['blockName'] !== null) {
                $firstIndex = $i;
                break;
            }
        }

        // Only strip if the first real block is a group (the hero pattern)
        if ($firstIndex !== null && $blocks[$firstIndex]['blockName'] === 'core/group') {
            array_splice($blocks, $firstIndex, 1);
        }

        return implode('', array_map('render_block', $blocks));
    }

    /**
     * Clean up the excerpt for feed readers — strip HTML, decode entities.
     */
    public function cleanExcerpt(string $excerpt): string
    {
        return trim(html_entity_decode(wp_strip_all_tags($excerpt)));
    }
}
