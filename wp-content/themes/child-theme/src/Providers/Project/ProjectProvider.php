<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ChildTheme\Providers\Project\Hooks\CategoryTermLinkRewrite;
use ChildTheme\Providers\Project\Hooks\ProjectYearExtractor;
use IX\Providers\Project\ProjectProvider as BaseProjectProvider;

/**
 * Project Provider.
 *
 * Extends the parent ProjectProvider with site-specific functionality:
 * hooks, default content, and child-themed block/single assets.
 */
class ProjectProvider extends BaseProjectProvider
{
    /**
     * Always-active hooks.
     */
    protected array $hooks = [
        CategoryTermLinkRewrite::class,
        ProjectYearExtractor::class,
    ];

    /**
     * Register the project provider with site-specific additions.
     */
    public function register(): void
    {
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
