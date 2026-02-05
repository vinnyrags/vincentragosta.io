<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ChildTheme\Providers\Provider;

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

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);

        parent::register();
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueStyle('child-theme-projects-block', 'projects.css');
    }

    /**
     * Enqueue block editor assets.
     */
    public function enqueueBlockEditorAssets(): void
    {
        $this->enqueueEditorScript('child-theme-projects-block-editor', 'projects.js');
        $this->enqueueStyle('child-theme-projects-block-editor', 'projects-editor.css');
    }

    /**
     * Register the project post type.
     */
    public function registerPostType(): void
    {
        $config = $this->loadConfig('post-type.json');

        if (!$config || !isset($config['post_type'], $config['args'])) {
            return;
        }

        $args = $config['args'];

        if (isset($args['labels'])) {
            $args['labels'] = $this->translateLabels($args['labels']);
        }

        register_post_type($config['post_type'], $args);
    }
}
