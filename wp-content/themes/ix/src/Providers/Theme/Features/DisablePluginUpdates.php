<?php

declare(strict_types=1);

namespace IX\Providers\Theme\Features;

use Mythus\Contracts\Feature;

/**
 * Disables plugin update notifications in the admin UI.
 *
 * Plugins are managed via Composer, so update notifications
 * in the WordPress admin are noise rather than signal.
 */
class DisablePluginUpdates implements Feature
{
    public function register(): void
    {
        remove_action('load-update-core.php', 'wp_update_plugins');
        add_filter('pre_site_transient_update_plugins', '__return_null');
    }
}
