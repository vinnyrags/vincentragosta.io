<?php

namespace ChildTheme\Providers\ProjectService;

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
     * Get featured projects.
     *
     * @return ProjectPost[]
     */
    public function featured(int $limit = 5): array
    {
        return $this->whereMetaEquals('_featured', '1', $limit);
    }

    /**
     * Get projects by category.
     *
     * @return ProjectPost[]
     */
    public function inCategory(string|int $category, int $limit = -1): array
    {
        return $this->whereTerm('category', $category, $limit);
    }
}
