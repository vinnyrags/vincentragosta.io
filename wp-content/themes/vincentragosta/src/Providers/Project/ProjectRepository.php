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
     * Index order: newest year first, then title — a deterministic tie-break,
     * so every year-ordered query (index, case studies, next project) agrees.
     */
    private const YEAR_ORDER = [
        'meta_key' => ProjectPost::FIELD_YEAR,
        'orderby'  => ['meta_value_num' => 'DESC', 'title' => 'ASC'],
    ];

    /**
     * Get all projects ordered by project year (descending).
     *
     * @return ProjectPost[]
     */
    public function allByYear(): array
    {
        return $this->query([
            'posts_per_page' => -1,
            ...self::YEAR_ORDER,
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
            ...self::YEAR_ORDER,
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
            ...self::YEAR_ORDER,
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
     *
     * Queries IDs directly: query() excludes the current post on singular
     * pages, which would leave $current out of the list it must be found in.
     */
    public function nextAfter(ProjectPost $current): ?ProjectPost
    {
        $ids = get_posts([
            ...$this->buildArgs([
                'posts_per_page' => -1,
                ...self::YEAR_ORDER,
            ]),
            'fields'           => 'ids',
            'suppress_filters' => false,
        ]);

        $nextId = self::nextIdInList(array_map('intval', $ids), (int) $current->ID);

        return $nextId !== null ? $this->find($nextId) : null;
    }

    /**
     * The ID after $currentId in $ids, wrapping to the first. Null when
     * $currentId isn't in the list or is the only entry.
     *
     * @param int[] $ids
     */
    public static function nextIdInList(array $ids, int $currentId): ?int
    {
        $ids   = array_values($ids);
        $index = array_search($currentId, $ids, true);

        if ($index === false || count($ids) < 2) {
            return null;
        }

        return $ids[($index + 1) % count($ids)];
    }
}
