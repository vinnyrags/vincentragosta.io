<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use ParentTheme\Providers\Contracts\Hook;

/**
 * Registers custom block styles for container blocks (core/group, core/column).
 */
class ContainerBlockStyles implements Hook
{
    public function register(): void
    {
        add_action('init', [$this, 'registerStyles']);
    }

    /**
     * Register block styles for container blocks.
     */
    public function registerStyles(): void
    {
        $darkStyle = [
            'name'  => 'dark',
            'label' => __('Dark', 'child-theme'),
        ];

        register_block_style('core/group', $darkStyle);
        register_block_style('core/column', $darkStyle);

        register_block_style('core/group', [
            'name'  => 'numbered-list',
            'label' => __('Numbered List', 'child-theme'),
        ]);
    }
}
