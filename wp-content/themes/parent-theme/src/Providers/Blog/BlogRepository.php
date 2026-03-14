<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Blog;

use ParentTheme\Repositories\Repository;

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
     * Get paginated posts.
     *
     * @return BlogPost[]
     */
    public function paginated(int $page = 1, int $perPage = 10): array
    {
        return $this->query([
            'posts_per_page' => $perPage,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
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
