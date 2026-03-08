<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ChildTheme\Providers\Project\Hooks\ProjectYearExtractor;
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
     * Always-active hooks.
     */
    protected array $hooks = [
        ProjectYearExtractor::class,
    ];

    /**
     * Register the project post type and delegate to the parent for blocks and features.
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueSingleAssets']);
        add_filter('default_content', [$this, 'setDefaultContent'], 10, 2);

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

    /**
     * Pre-fill the editor with default block content for new projects.
     *
     * @param string $content Default post content.
     * @param \WP_Post $post The post being created.
     * @return string
     */
    public function setDefaultContent(string $content, \WP_Post $post): string
    {
        if ($post->post_type !== ProjectPost::POST_TYPE) {
            return $content;
        }

        $this->setup();
        $filepath = $this->configPath . '/default-content.html';

        if (!file_exists($filepath)) {
            return $content;
        }

        $template = file_get_contents($filepath);

        return $template !== false ? $template : $content;
    }
}
