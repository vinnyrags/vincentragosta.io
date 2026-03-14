<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ChildTheme\Providers\Project\Hooks\ProjectYearExtractor;
use ParentTheme\Providers\Project\ProjectPost as BaseProjectPost;

/**
 * Project post model.
 *
 * Extends the parent ProjectPost with site-specific functionality.
 */
class ProjectPost extends BaseProjectPost
{
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
}
