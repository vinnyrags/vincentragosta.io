<?php

namespace ParentTheme\Providers\ThemeService\Features;

use ParentTheme\Contracts\Registrable;

/**
 * Disables the default "post" post type.
 */
class DisablePosts implements Registrable
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'removeAdminMenu']);
        add_action('admin_bar_menu', [$this, 'removeFromAdminBar'], 999);
        add_action('admin_init', [$this, 'redirectAdminPage']);
    }

    /**
     * Remove Posts from admin menu.
     */
    public function removeAdminMenu(): void
    {
        remove_menu_page('edit.php');
    }

    /**
     * Remove "New Post" from admin bar.
     */
    public function removeFromAdminBar(\WP_Admin_Bar $adminBar): void
    {
        $adminBar->remove_node('new-post');
    }

    /**
     * Redirect posts admin pages to dashboard.
     */
    public function redirectAdminPage(): void
    {
        global $pagenow;

        if ($pagenow === 'edit.php' && ! isset($_GET['post_type'])) {
            wp_redirect(admin_url());
            exit;
        }

        if ($pagenow === 'post-new.php' && ! isset($_GET['post_type'])) {
            wp_redirect(admin_url());
            exit;
        }
    }
}
