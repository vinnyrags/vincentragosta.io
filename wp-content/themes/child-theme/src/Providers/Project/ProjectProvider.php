<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ParentTheme\Providers\Provider;

/**
 * Project Provider.
 *
 * Self-contained provider for all project-related functionality.
 * Includes the projects block, post type, and configuration.
 */
class ProjectProvider extends Provider
{
    /**
     * Blocks to register.
     */
    protected array $blocks = [
        'projects',
    ];

    /**
     * Register the project post type and delegate to the parent for blocks and features.
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueSingleAssets']);

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

        $this->enqueueStyle('child-theme-project-single', 'project.css');
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueStyle('child-theme-projects-block', 'projects.css');
    }

    /**
     * Register the project post type.
     */
    public function registerPostType(): void
    {
        $this->registerPostTypeFromConfig('post-type.json');
    }
}
