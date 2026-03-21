<?php

declare(strict_types=1);

namespace IX\Providers\Blog;

use IX\Repositories\Repository;
use WP_Query;

/**
 * Blog repository.
 *
 * Provides query methods for the built-in post type.
 * Child themes can extend with site-specific queries.
 */
class BlogRepository extends Repository
{
    protected string $model = BlogPost::class;

    /**
     * Get paginated posts with metadata.
     *
     * @return array{posts: BlogPost[], total_pages: int, current_page: int}
     */
    public function paginated(int $page = 1, int $perPage = 10): array
    {
        $args = $this->buildArgs([
            'posts_per_page' => $perPage,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $args = $this->maybeExcludeCurrentPost($args);

        $wpQuery = new WP_Query($args);
        $posts = \Timber\Timber::get_posts($wpQuery);

        $postsArray = $posts instanceof \Timber\PostQuery
            ? $posts->to_array()
            : (is_array($posts) ? $posts : []);

        return [
            'posts' => $postsArray,
            'total_pages' => (int) $wpQuery->max_num_pages,
            'current_page' => $page,
        ];
    }

    /**
     * Get posts by category.
     *
     * @return BlogPost[]
     */
    public function byCategory(string $category, int $limit = -1): array
    {
        return $this->whereTerm('category', $category, $limit);
    }
}
