<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Blog\Hooks;

use ParentTheme\Providers\Contracts\Hook;
use ParentTheme\Providers\Theme\Features\DisablePosts;

/**
 * Re-enables the built-in post type.
 *
 * Counteracts DisablePosts (from ThemeProvider) by removing its hooks
 * before they fire. This allows posts to remain disabled by default
 * while the BlogProvider explicitly opts back in.
 */
class EnablePosts implements Hook
{
    public function register(): void
    {
        $this->removeDisablePostsCallbacks();
    }

    /**
     * Remove all hooks registered by DisablePosts.
     *
     * Since providers register sequentially (ThemeProvider before BlogProvider),
     * the DisablePosts callbacks are already in the global $wp_filter by the
     * time this runs. We remove them before any WordPress hooks fire.
     */
    private function removeDisablePostsCallbacks(): void
    {
        global $wp_filter;

        $targets = [
            'admin_menu' => 'removeAdminMenu',
            'admin_bar_menu' => 'removeFromAdminBar',
            'admin_init' => 'redirectAdminPage',
        ];

        foreach ($targets as $tag => $method) {
            if (!isset($wp_filter[$tag])) {
                continue;
            }

            foreach ($wp_filter[$tag]->callbacks as $priority => &$callbacks) {
                foreach ($callbacks as $key => $callback) {
                    if (
                        is_array($callback['function'])
                        && $callback['function'][0] instanceof DisablePosts
                        && $callback['function'][1] === $method
                    ) {
                        unset($callbacks[$key]);
                    }
                }
            }
        }
    }
}
