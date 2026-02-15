<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use ParentTheme\Providers\Contracts\Hook;

/**
 * Registers custom block styles for core/cover.
 */
class CoverBlockStyles implements Hook
{
    public function register(): void
    {
        add_action('init', [$this, 'registerStyles']);
    }

    /**
     * Register block styles for core/cover.
     */
    public function registerStyles(): void
    {
        register_block_style('core/cover', [
            'name'  => 'animated',
            'label' => __('Animated Background', 'child-theme'),
        ]);
    }
}
