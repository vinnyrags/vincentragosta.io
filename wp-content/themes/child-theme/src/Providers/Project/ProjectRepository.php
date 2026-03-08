<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ChildTheme\Providers\Project\Hooks\ProjectYearExtractor;
use ParentTheme\Repositories\Repository;

/**
 * Project repository.
 *
 * Provides query methods specific to the Project post type.
 */
class ProjectRepository extends Repository
{
    protected string $model = ProjectPost::class;

    /**
     * Get all projects ordered by project year (descending).
     *
     * @return ProjectPost[]
     */
    public function allByYear(): array
    {
        return $this->query([
            'posts_per_page' => -1,
            'meta_key'       => ProjectYearExtractor::META_KEY,
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ]);
    }

    /**
     * Get the latest projects ordered by project year (descending).
     *
     * @return ProjectPost[]
     */
    public function latestByYear(int $limit = 6): array
    {
        return $this->query([
            'posts_per_page' => $limit,
            'meta_key'       => ProjectYearExtractor::META_KEY,
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ]);
    }

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
}
