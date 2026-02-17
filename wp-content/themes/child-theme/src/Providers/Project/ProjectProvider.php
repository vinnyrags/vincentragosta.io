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
        add_action('pre_get_posts', [$this, 'loadAllArchiveProjects']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueArchiveAssets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueSingleAssets']);

        parent::register();

        $this->acfManager->registerSavePath();
    }

    /**
     * Load all projects on the archive page (no pagination).
     */
    public function loadAllArchiveProjects(\WP_Query $query): void
    {
        if (!is_admin() && $query->is_main_query() && $query->is_post_type_archive(ProjectPost::POST_TYPE)) {
            $query->set('posts_per_page', 100);
        }
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
     * Enqueue block styles and scroll-reveal assets on the project archive page.
     */
    public function enqueueArchiveAssets(): void
    {
        if (!is_post_type_archive(ProjectPost::POST_TYPE)) {
            return;
        }

        $this->enqueueStyle('child-theme-projects-block', 'projects.css');
        $this->enqueueStyle('child-theme-project-archive', 'project.css');
        $this->enqueueScript('child-theme-project-archive', 'archive.js');
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
