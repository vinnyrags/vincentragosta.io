<?php

declare(strict_types=1);

namespace IX\Providers\Theme\Features;

use Mythus\Contracts\Feature;

/**
 * Removes all default and remote block patterns.
 *
 * Disables core WordPress block patterns, theme patterns that ship with
 * WordPress, and remote patterns fetched from the pattern directory.
 * This ensures only explicitly registered patterns are available.
 */
class DisableDefaultPatterns implements Feature
{
    public function register(): void
    {
        remove_theme_support('core-block-patterns');
        add_filter('should_load_remote_block_patterns', '__return_false');
    }
}
