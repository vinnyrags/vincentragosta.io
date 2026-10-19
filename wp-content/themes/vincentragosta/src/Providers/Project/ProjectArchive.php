<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

/**
 * Builds the view model for the projects archive (the projects block in
 * "all" mode): stats, filterable categories, case studies, personal work,
 * year groups.
 *
 * Personal projects get their own section, so they are left out of the case
 * studies and the client count. They stay in the full index.
 *
 * Pure — takes already-queried projects, so it is testable without a database.
 */
class ProjectArchive
{
    /** A category needs at least this many projects to get a filter pill. */
    public const MIN_CATEGORY_SIZE = 2;

    /**
     * @param ProjectPost[] $projects    All projects, newest year first.
     * @param ProjectPost[] $caseStudies Case-study projects, newest year first.
     */
    public function __construct(
        private readonly array $projects,
        private readonly array $caseStudies,
    ) {
    }

    /**
     * @return array{
     *     stats: array<int, array{label: string, value: string, suffix: string, detail: string}>,
     *     categories: array<int, array{slug: string, name: string, count: int}>,
     *     case_studies: ProjectPost[],
     *     personal: ProjectPost[],
     *     groups: array<int, array{year: string, projects: ProjectPost[]}>,
     *     total: int
     * }
     */
    public function toArray(): array
    {
        return [
            'stats'        => $this->stats(),
            'categories'   => $this->categories(),
            'case_studies' => $this->caseStudies(),
            'personal'     => $this->personal(),
            'groups'       => $this->groups(),
            'total'        => count($this->projects),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, suffix: string, detail: string}>
     */
    public function stats(): array
    {
        $years = array_filter(array_map(
            static fn(ProjectPost $p): ?int => $p->year(),
            $this->projects
        ));

        $stats = [
            ['label' => 'Projects', 'value' => (string) count($this->projects), 'suffix' => '', 'detail' => 'Shipped and documented'],
            ['label' => 'Case studies', 'value' => (string) count($this->caseStudies()), 'suffix' => '', 'detail' => 'Featured builds'],
            ['label' => 'Clients', 'value' => (string) $this->clientCount(), 'suffix' => '', 'detail' => 'Agencies and independents'],
        ];

        if ($years) {
            $stats[] = ['label' => 'Since', 'value' => (string) min($years), 'suffix' => '– now', 'detail' => 'Agency scale to independent'];
        }

        return $stats;
    }

    /**
     * Case studies, minus personal projects (they have their own section).
     *
     * @return ProjectPost[]
     */
    public function caseStudies(): array
    {
        return array_values(array_filter(
            $this->caseStudies,
            static fn(ProjectPost $p): bool => !$p->isPersonal()
        ));
    }

    /**
     * Personal projects, curated by menu order (Order in the editor), then in
     * archive order.
     *
     * @return ProjectPost[]
     */
    public function personal(): array
    {
        $personal = array_values(array_filter(
            $this->projects,
            static fn(ProjectPost $p): bool => $p->isPersonal()
        ));

        usort($personal, static fn(ProjectPost $a, ProjectPost $b): int => (int) $a->menu_order <=> (int) $b->menu_order);

        return $personal;
    }

    /**
     * Distinct clients, compared case-insensitively. Personal projects have
     * no client.
     */
    public function clientCount(): int
    {
        $clients = [];

        foreach ($this->projects as $project) {
            if ($project->isPersonal()) {
                continue;
            }

            $client = mb_strtolower($project->client());
            if ($client !== '') {
                $clients[$client] = true;
            }
        }

        return count($clients);
    }

    /**
     * Categories used by at least MIN_CATEGORY_SIZE projects, largest first
     * (ties by name).
     *
     * @return array<int, array{slug: string, name: string, count: int}>
     */
    public function categories(): array
    {
        $categories = [];

        foreach ($this->projects as $project) {
            foreach ($project->categories() as $term) {
                $categories[$term->slug] ??= ['slug' => $term->slug, 'name' => html_entity_decode($term->name, ENT_QUOTES), 'count' => 0];
                $categories[$term->slug]['count']++;
            }
        }

        $categories = array_values(array_filter(
            $categories,
            static fn(array $c): bool => $c['count'] >= self::MIN_CATEGORY_SIZE
        ));

        usort($categories, static fn(array $a, array $b): int => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return $categories;
    }

    /**
     * @return array<int, array{year: string, projects: ProjectPost[]}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (ProjectRepository::groupByYear($this->projects) as $year => $projects) {
            $groups[] = ['year' => (string) $year, 'projects' => $projects];
        }

        return $groups;
    }
}
