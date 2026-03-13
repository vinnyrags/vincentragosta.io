<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Project;

use ParentTheme\Providers\Provider;

/**
 * Project Provider.
 *
 * Opt-in provider for project portfolio functionality.
 * Not registered by default — child themes add it (or a subclass)
 * to their $providers array.
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
    }

    /**
     * Register the project post type.
     */
    public function registerPostType(): void
    {
        $this->registerPostTypeFromConfig('post-type.json');
    }

    /**
     * Enqueue styles on single project pages.
     */
    public function enqueueSingleAssets(): void
    {
        if (!is_singular(ProjectPost::POST_TYPE)) {
            return;
        }

        $this->enqueueParentDistStyle('parent-theme-project-single', 'css/project.css');
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueParentDistStyle('parent-theme-projects-block', 'css/projects.css');
    }

    /**
     * Enqueue a stylesheet from the parent theme's dist/ directory.
     *
     * Uses get_template_directory() so assets resolve from the parent theme
     * even when a child theme is active.
     */
    protected function enqueueParentDistStyle(string $handle, string $path, array $deps = []): void
    {
        $fullPath = get_template_directory() . '/dist/' . $path;

        if (file_exists($fullPath)) {
            wp_enqueue_style(
                $handle,
                get_template_directory_uri() . '/dist/' . $path,
                $deps,
                filemtime($fullPath)
            );
        }
    }
}
