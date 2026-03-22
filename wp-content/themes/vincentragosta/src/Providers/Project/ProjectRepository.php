<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ChildTheme\Providers\Project\Hooks\ProjectYearExtractor;
use IX\Providers\Project\ProjectRepository as BaseProjectRepository;

/**
 * Project repository.
 *
 * Extends the parent repository with site-specific query methods.
 */
class ProjectRepository extends BaseProjectRepository
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
}
