<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Blog;

use ParentTheme\Providers\Blog\Hooks\EnablePosts;
use ParentTheme\Providers\Provider;

/**
 * Blog Provider.
 *
 * Opt-in provider for blog functionality using the built-in post type.
 * Re-enables posts (counteracts DisablePosts from ThemeProvider) and
 * registers the blog block for displaying post grids.
 */
class BlogProvider extends Provider
{
    /**
     * Always-active hooks.
     */
    protected array $hooks = [
        EnablePosts::class,
    ];

    /**
     * Blocks to register.
     */
    protected array $blocks = [
        'blog',
    ];

    /**
     * Register the blog provider.
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueSingleAssets']);
        add_action('pre_get_posts', [$this, 'supportStaticPagePagination']);
        add_filter('redirect_canonical', [$this, 'preventPaginationRedirect'], 10, 2);

        parent::register();
    }

    /**
     * Allow /page/N/ on static pages that contain the blog block.
     *
     * WordPress ignores the 'paged' query var on static pages by default,
     * which causes paginated URLs to 404. This sets the var explicitly.
     */
    public function supportStaticPagePagination(\WP_Query $query): void
    {
        if (!$query->is_main_query() || is_admin()) {
            return;
        }

        $paged = $query->get('paged');

        if (empty($paged) && $query->is_page()) {
            $page = (int) $query->get('page');
            if ($page > 1) {
                $query->set('paged', $page);
            }
        }
    }

    /**
     * Prevent WordPress from redirecting /page/2/ back to the base URL on static pages.
     */
    public function preventPaginationRedirect(string $redirectUrl, string $requestedUrl): string
    {
        if (is_page() && get_query_var('paged') > 1) {
            return $requestedUrl;
        }

        return $redirectUrl;
    }

    /**
     * Enqueue styles on single post pages.
     */
    public function enqueueSingleAssets(): void
    {
        if (!is_singular(BlogPost::POST_TYPE)) {
            return;
        }

        $this->enqueueParentDistStyle('parent-theme-blog-single', 'css/blog.css');
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueParentDistStyle('parent-theme-blog-block', 'css/blog.css');
    }

    /**
     * Enqueue block editor assets.
     */
    public function enqueueBlockEditorAssets(): void
    {
        $this->enqueueParentDistScript('parent-theme-blog-editor', 'js/blog.js', [
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-i18n',
            'wp-server-side-render',
        ]);
    }

    /**
     * Enqueue a stylesheet from the parent theme's dist/ directory.
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

    /**
     * Enqueue a script from the parent theme's dist/ directory.
     */
    protected function enqueueParentDistScript(string $handle, string $path, array $deps = [], bool $inFooter = true): void
    {
        $fullPath = get_template_directory() . '/dist/' . $path;

        if (file_exists($fullPath)) {
            wp_enqueue_script(
                $handle,
                get_template_directory_uri() . '/dist/' . $path,
                $deps,
                filemtime($fullPath),
                $inFooter
            );
        }
    }
}
