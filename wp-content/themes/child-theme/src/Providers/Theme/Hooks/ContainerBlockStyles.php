<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use ParentTheme\Providers\Hooks\BlockStyles;

/**
 * Registers custom block styles for container blocks (core/group, core/column).
 */
class ContainerBlockStyles extends BlockStyles
{
    protected function styles(): array
    {
        $darkStyle = [
            'name'  => 'dark',
            'label' => __('Dark', 'child-theme'),
        ];

        return [
            'core/group' => [
                $darkStyle,
                ['name' => 'numbered-list', 'label' => __('Numbered List', 'child-theme')],
                ['name' => 'nous-accent', 'label' => __('Nous Accent', 'child-theme')],
            ],
            'core/column' => [
                $darkStyle,
            ],
        ];
    }
}
