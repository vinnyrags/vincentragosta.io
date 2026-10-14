<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ChildTheme\Providers\Project\Hooks\CategoryTermLinkRewrite;
use ChildTheme\Providers\Project\Hooks\ProjectSearchIndex;
use ChildTheme\Providers\Project\Hooks\ProjectSectionDefaults;
use IX\Providers\Project\ProjectProvider as BaseProjectProvider;

/**
 * Project Provider.
 *
 * Extends the parent ProjectProvider with site-specific functionality:
 * hooks (section defaults, search indexing, category links) and child-themed block/single assets.
 */
class ProjectProvider extends BaseProjectProvider
{
    /**
     * Always-active hooks.
     */
    protected array $hooks = [
        CategoryTermLinkRewrite::class,
        ProjectSectionDefaults::class,
        ProjectSearchIndex::class,
    ];

    /**
     * Register the project provider with site-specific additions.
     */
    public function register(): void
    {
        parent::register();

        $this->acfManager->registerSavePath();
    }

    /**
     * Enqueue styles on single project pages.
     */
    public function enqueueSingleAssets(): void
    {
        if (!is_singular(ProjectPost::POST_TYPE)) {
            return;
        }

        $this->enqueueStyle('vincentragosta-project-single', 'project.css');
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueStyle('vincentragosta-projects-block', 'projects.css');
    }
}
