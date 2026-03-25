<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Blog\Hooks;

use Mythus\Contracts\Hook;

/**
 * Rewrites post_tag term links to point to the configured blog page
 * with a tag query parameter for client-side filtering.
 */
class TagTermLinkRewrite implements Hook
{
    public function register(): void
    {
        add_filter('term_link', [$this, 'rewriteTagLink'], 10, 3);
    }

    /**
     * Rewrite post_tag term links to {blog_page}?tag={slug}.
     *
     * @param string   $termlink The original term link URL.
     * @param \WP_Term $term     The term object.
     * @param string   $taxonomy The taxonomy slug.
     */
    public function rewriteTagLink(string $termlink, \WP_Term $term, string $taxonomy): string
    {
        if ($taxonomy !== 'post_tag') {
            return $termlink;
        }

        if (!function_exists('get_field')) {
            return $termlink;
        }

        $blogPageId = get_field('blog_page', 'option');

        if (!$blogPageId) {
            return $termlink;
        }

        $permalink = get_permalink((int) $blogPageId);

        if (!$permalink) {
            return $termlink;
        }

        return $permalink . '?tag=' . $term->slug;
    }
}
