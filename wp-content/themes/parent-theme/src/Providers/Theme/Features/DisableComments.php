<?php

namespace ParentTheme\Providers\Theme\Features;

use ParentTheme\Providers\Contracts\Registrable;

/**
 * Disables all comment functionality across the site.
 */
class DisableComments implements Registrable
{
    public function register(): void
    {
        add_action('init', [$this, 'removePostTypeSupport'], 100);
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        add_filter('comments_array', '__return_empty_array', 10, 2);
        add_action('admin_menu', [$this, 'removeAdminMenu']);
        add_action('admin_init', [$this, 'redirectAdminPage']);
        add_action('wp_before_admin_bar_render', [$this, 'removeFromAdminBar']);
    }

    /**
     * Remove comment support from all post types.
     */
    public function removePostTypeSupport(): void
    {
        foreach (get_post_types() as $postType) {
            if (post_type_supports($postType, 'comments')) {
                remove_post_type_support($postType, 'comments');
                remove_post_type_support($postType, 'trackbacks');
            }
        }
    }

    /**
     * Remove comments from admin menu.
     */
    public function removeAdminMenu(): void
    {
        remove_menu_page('edit-comments.php');
    }

    /**
     * Redirect comments admin page to dashboard.
     */
    public function redirectAdminPage(): void
    {
        global $pagenow;

        if ($pagenow === 'edit-comments.php') {
            wp_redirect(admin_url());
            exit;
        }
    }

    /**
     * Remove comments from admin bar.
     */
    public function removeFromAdminBar(): void
    {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('comments');
    }
}
