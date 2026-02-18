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
     * Get the client name.
     */
    public function client(): string
    {
        return (string) $this->getField('client');
    }

    /**
     * Get the project year or date range.
     */
    public function year(): string
    {
        return (string) $this->getField('year');
    }

    /**
     * Get the raw comma-separated technologies string.
     */
    public function technologies(): string
    {
        return (string) $this->getField('technologies');
    }

    /**
     * Get technologies as a trimmed array.
     *
     * @return string[]
     */
    public function technologyList(): array
    {
        $tech = $this->technologies();

        if ($tech === '') {
            return [];
        }

        return array_map('trim', explode(',', $tech));
    }

    /**
     * Get the external project URL.
     */
    public function externalUrl(): string
    {
        return (string) $this->getField('external_url');
    }

    /**
     * Get the project background.
     */
    public function background(): string
    {
        return (string) $this->getField('background');
    }

    /**
     * Get the project implementation details.
     */
    public function implementation(): string
    {
        return (string) $this->getField('implementation');
    }

    /**
     * Get the project results.
     */
    public function results(): string
    {
        return (string) $this->getField('results');
    }

    /**
     * Whether any project detail field is populated.
     */
    public function hasProjectDetails(): bool
    {
        return $this->client() !== ''
            || $this->year() !== ''
            || $this->technologies() !== ''
            || $this->externalUrl() !== '';
    }

    /**
     * Whether any case study section is populated.
     */
    public function hasCaseStudy(): bool
    {
        return $this->background() !== ''
            || $this->implementation() !== ''
            || $this->results() !== '';
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

        // Exclude the current post
        return array_values(array_filter(
            $related,
            fn (ProjectPost $project) => $project->ID !== $this->ID,
        ));
    }
}
