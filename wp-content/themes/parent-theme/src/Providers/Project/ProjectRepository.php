<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Project;

use ParentTheme\Repositories\Repository;

/**
 * Project repository.
 *
 * Provides generic query methods for the Project post type.
 * Child themes can extend with site-specific queries.
 */
class ProjectRepository extends Repository
{
    protected string $model = ProjectPost::class;

    /**
     * Get featured projects.
     *
     * @param int $limit Maximum number of posts to return.
     * @return ProjectPost[]
     */
    public function featured(int $limit = 5): array
    {
        return $this->whereMetaEquals('_featured', '1', $limit);
    }

    /**
     * Get projects by category.
     *
     * @param string|int $category Category slug or term ID.
     * @param int $limit Maximum number of posts to return (-1 for unlimited).
     * @return ProjectPost[]
     */
    public function inCategory(string|int $category, int $limit = -1): array
    {
        return $this->whereTerm('category', $category, $limit);
    }

    /**
     * Get random related projects from the same category.
     *
     * @param string $categorySlug Category to match.
     * @param int $limit Maximum number of results.
     * @return ProjectPost[]
     */
    public function relatedRandom(string $categorySlug, int $limit = 3): array
    {
        $projects = $this->inCategory($categorySlug);
        shuffle($projects);

        return array_slice($projects, 0, $limit);
    }
}
