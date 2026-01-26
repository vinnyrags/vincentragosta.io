<?php

namespace ChildTheme\Providers\ThemeService;

use ParentTheme\Providers\ThemeService\ThemeServiceProvider as BaseThemeServiceProvider;

/**
 * Handles core theme setup and configuration.
 *
 * Extends the parent theme's ThemeService Provider to add site-specific functionality.
 */
class ThemeServiceProvider extends BaseThemeServiceProvider
{
    public function register(): void
    {
        // Add site-specific hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('show_admin_bar', '__return_false');

        // Call parent to register theme supports and features
        parent::register();
    }

    /**
     * Enqueue frontend assets for this theme.
     */
    public function enqueueAssets(): void
    {
        $this->enqueueStyle('child-theme-theme-service', 'theme-service.css');
    }
}
