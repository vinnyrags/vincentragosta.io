<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ChildTheme\Providers\Project\Hooks\ProjectYearExtractor;
use ChildTheme\Theme;
use ParentTheme\Models\Post;

/**
 * Project post model.
 *
 * Extends the base Post model with project-specific functionality.
 */
class ProjectPost extends Post
{
    public const POST_TYPE = 'project';

    /**
     * Get the year used for sorting.
     *
     * Returns project_year meta if available, falls back to publish year.
     */
    public function sortYear(): string
    {
        $meta = $this->getMeta(ProjectYearExtractor::META_KEY);

        return !empty($meta) ? (string) $meta : $this->date('Y');
    }

    /**
     * Get the project's categories.
     *
     * @return \Timber\Term[]
     */
    public function categories(): array
    {
        return $this->terms(['taxonomy' => 'category']);
    }

    /**
     * Get the first category name.
     */
    public function categoryName(): ?string
    {
        $categories = $this->categories();
        return $categories[0]->name ?? null;
    }

    /**
     * Get the first category slug.
     */
    public function categorySlug(): ?string
    {
        $categories = $this->categories();
        return $categories[0]->slug ?? null;
    }

    /**
     * Get all category slugs as a space-separated string.
     */
    public function categorySlugs(): string
    {
        return implode(' ', array_map(
            fn ($term) => $term->slug,
            $this->categories(),
        ));
    }

    /**
     * Get related projects weighted by year proximity.
     *
     * @return ProjectPost[]
     */
    public function relatedProjects(int $limit = 3): array
    {
        $categories = $this->categories();

        if (empty($categories)) {
            return [];
        }

        $repository = Theme::container()->get(ProjectRepository::class);

        return $repository->relatedRandom(
            $categories[0]->slug,
            $limit,
        );
    }
}
