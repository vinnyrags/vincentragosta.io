<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

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
     * Get related projects in the same category.
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
        $related = $repository->inCategory($categories[0]->slug, $limit + 1);

        // Exclude the current post and enforce limit
        $filtered = array_values(array_filter(
            $related,
            fn (ProjectPost $project) => $project->ID !== $this->ID,
        ));

        return array_slice($filtered, 0, $limit);
    }
}
