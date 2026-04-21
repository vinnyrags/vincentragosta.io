<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Blog;

use ChildTheme\Providers\Blog\Hooks\NousSignalFeed;
use ChildTheme\Providers\Blog\Hooks\TagTermLinkRewrite;
use IX\Providers\Blog\BlogPost;
use IX\Providers\Blog\BlogProvider as BaseBlogProvider;

/**
 * Blog Provider.
 *
 * Extends the parent BlogProvider with site-specific functionality.
 * Adds the Nous Signal accent color override on the configured blog page
 * and single post pages.
 */
class BlogProvider extends BaseBlogProvider
{
    /**
     * Always-active hooks.
     */
    protected array $hooks = [
        NousSignalFeed::class,
        TagTermLinkRewrite::class,
    ];

    /**
     * Register the blog provider with site-specific additions.
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueNousSignalScript']);
        add_filter('body_class', [$this, 'addNousSignalBodyClass']);
        add_action('admin_menu', [$this, 'renamePostsMenu']);
        add_action('init', [$this, 'unregisterCategories']);
        add_filter('nav_menu_css_class', [$this, 'addNousSignalNavClass'], 10, 2);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAccentOverride']);

        parent::register();

        $this->acfManager->registerSavePath();
    }

    /**
     * Add the nous-signal-page body class on the configured blog page
     * and single post pages.
     *
     * @param string[] $classes
     * @return string[]
     */
    public function addNousSignalBodyClass(array $classes): array
    {
        if ($this->isNousSignalPage()) {
            $classes[] = 'nous-signal-page';
        }

        return $classes;
    }

    /**
     * Rename the Posts sidebar menu label to Nous Signal.
     */
    public function renamePostsMenu(): void
    {
        global $menu;

        foreach ($menu as &$item) {
            if (($item[2] ?? '') === 'edit.php') {
                $item[0] = 'Nous Signal';
                break;
            }
        }
    }

    /**
     * Override accent-1 to nous-red in the block editor for posts.
     */
    public function enqueueEditorAccentOverride(): void
    {
        $screen = get_current_screen();

        if (!$screen || $screen->post_type !== BlogPost::POST_TYPE) {
            return;
        }

        wp_add_inline_style(
            'wp-edit-blocks',
            'body { --wp--preset--color--accent-1: var(--wp--preset--color--nous-red, #ff3333); --wp--custom--color--accent-1-dark: #cc2929; }'
        );
    }

    /**
     * Unregister the category taxonomy from the post type.
     */
    public function unregisterCategories(): void
    {
        unregister_taxonomy_for_object_type('category', BlogPost::POST_TYPE);
    }

    /**
     * Add a CSS class to the nav menu item that links to the configured blog page.
     *
     * @param string[] $classes
     * @param \WP_Post $menuItem
     * @return string[]
     */
    public function addNousSignalNavClass(array $classes, $menuItem): array
    {
        if (!function_exists('get_field')) {
            return $classes;
        }

        $blogPageId = get_field('blog_page', 'option');

        if ($blogPageId && (int) $menuItem->object_id === (int) $blogPageId) {
            $classes[] = 'nous-signal-nav-item';
        }

        return $classes;
    }

    /**
     * Whether the current page is a Nous Signal page.
     */
    private function isNousSignalPage(): bool
    {
        if (is_singular(BlogPost::POST_TYPE)) {
            return true;
        }

        if (!function_exists('get_field')) {
            return false;
        }

        $blogPageId = get_field('blog_page', 'option');

        return $blogPageId && is_page((int) $blogPageId);
    }

    /**
     * Enqueue the Nous Signal frontend script.
     */
    public function enqueueNousSignalScript(): void
    {
        $path = get_stylesheet_directory() . '/dist/js/blog/nous-signal.js';

        if (!file_exists($path)) {
            return;
        }

        wp_enqueue_script(
            'vincentragosta-nous-signal',
            get_stylesheet_directory_uri() . '/dist/js/blog/nous-signal.js',
            [],
            filemtime($path),
            true
        );
    }

    /**
     * Enqueue styles on single post pages.
     */
    public function enqueueSingleAssets(): void
    {
        if (!is_singular(BlogPost::POST_TYPE)) {
            return;
        }

        $this->enqueueStyle('vincentragosta-blog-single', 'blog.css');
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueStyle('vincentragosta-blog-block', 'blog.css');
    }
}
