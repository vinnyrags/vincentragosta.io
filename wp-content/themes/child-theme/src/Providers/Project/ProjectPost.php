<?php

namespace ChildTheme\Providers\Project;

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
}
