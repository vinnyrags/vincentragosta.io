<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

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
            'meta_key'       => ProjectPost::FIELD_YEAR,
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
            'meta_key'       => ProjectPost::FIELD_YEAR,
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ]);
    }

    /**
     * Get case-study projects ordered by project year (descending).
     *
     * @return ProjectPost[]
     */
    public function caseStudies(): array
    {
        return $this->query([
            'posts_per_page' => -1,
            'meta_key'       => ProjectPost::FIELD_YEAR,
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => ProjectPost::FIELD_CASE_STUDY,
                    'value' => '1',
                ],
            ],
        ]);
    }

    /**
     * Get all projects grouped by year, newest year first.
     *
     * @return array<string, ProjectPost[]>
     */
    public function allGroupedByYear(): array
    {
        return self::groupByYear($this->allByYear());
    }

    /**
     * Group projects by sort year, newest year first. Order within a year is
     * preserved from the input.
     *
     * @param ProjectPost[] $projects
     * @return array<string, ProjectPost[]>
     */
    public static function groupByYear(array $projects): array
    {
        $groups = [];

        foreach ($projects as $project) {
            $groups[$project->sortYear()][] = $project;
        }

        krsort($groups, SORT_NUMERIC);

        return $groups;
    }

    /**
     * The project after $current in index order (year descending), wrapping
     * to the first. Null when there is no other project.
     */
    public function nextAfter(ProjectPost $current): ?ProjectPost
    {
        return self::nextInList($this->allByYear(), (int) $current->ID);
    }

    /**
     * @param ProjectPost[] $projects
     */
    public static function nextInList(array $projects, int $currentId): ?ProjectPost
    {
        $projects = array_values($projects);
        $count    = count($projects);

        foreach ($projects as $i => $project) {
            if ((int) $project->ID === $currentId) {
                return $count > 1 ? $projects[($i + 1) % $count] : null;
            }
        }

        return $projects[0] ?? null;
    }
}
