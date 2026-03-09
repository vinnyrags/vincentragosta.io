<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project\Hooks;

use ChildTheme\Providers\Project\ProjectPost;
use ParentTheme\Providers\Contracts\Hook;

/**
 * Rewrites category term links to point to the projects archive
 * with a category query parameter for client-side filtering.
 */
class CategoryTermLinkRewrite implements Hook
{
    public function register(): void
    {
        add_filter('term_link', [$this, 'rewriteCategoryLink'], 10, 3);
    }

    /**
     * Rewrite category term links to /projects/?category={slug}.
     *
     * Only rewrites links for categories that are assigned to the project post type.
     *
     * @param string $termlink The original term link URL.
     * @param \WP_Term $term The term object.
     * @param string $taxonomy The taxonomy slug.
     */
    public function rewriteCategoryLink(string $termlink, \WP_Term $term, string $taxonomy): string
    {
        if ($taxonomy !== 'category') {
            return $termlink;
        }

        if (!$this->isProjectCategory($term)) {
            return $termlink;
        }

        $projectsPage = get_page_by_path('projects');

        if (!$projectsPage) {
            return $termlink;
        }

        return get_permalink($projectsPage) . '?category=' . $term->slug;
    }

    /**
     * Check if a category term is used by any project posts.
     */
    private function isProjectCategory(\WP_Term $term): bool
    {
        // On singular project pages, we know the category belongs to a project.
        if (is_singular(ProjectPost::POST_TYPE)) {
            return true;
        }

        // Check if term is associated with the project post type.
        $query = new \WP_Query([
            'post_type'      => ProjectPost::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'category',
                    'terms'    => $term->term_id,
                ],
            ],
        ]);

        return $query->have_posts();
    }
}
