<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Hooks\BlockStyles;

/**
 * Registers custom block styles for container blocks (core/group, core/column).
 */
class ContainerBlockStyles extends BlockStyles
{
    protected function styles(): array
    {
        $darkStyle = [
            'name'  => 'dark',
            'label' => __('Dark', 'vincentragosta'),
        ];

        return [
            'core/group' => [
                $darkStyle,
                ['name' => 'numbered-list', 'label' => __('Numbered List', 'vincentragosta')],
                ['name' => 'nous-accent', 'label' => __('Nous Accent', 'vincentragosta')],
            ],
            'core/column' => [
                $darkStyle,
            ],
        ];
    }
}
