<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Hooks\BlockStyles;

/**
 * Registers custom block styles for container blocks (core/group, core/columns, core/column).
 */
class ContainerBlockStyles extends BlockStyles
{
    protected function styles(): array
    {
        $darkStyle = [
            'name'  => 'dark',
            'label' => __('Dark', 'vincentragosta'),
        ];

        $statStripStyle = [
            'name'  => 'stat-strip',
            'label' => __('Stat Strip', 'vincentragosta'),
        ];

        return [
            'core/group' => [
                $darkStyle,
                ['name' => 'panel', 'label' => __('Panel', 'vincentragosta')],
                $statStripStyle,
                ['name' => 'layers', 'label' => __('Layers', 'vincentragosta')],
                ['name' => 'testimonials', 'label' => __('Testimonials', 'vincentragosta')],
                ['name' => 'numbered-list', 'label' => __('Numbered List', 'vincentragosta')],
                ['name' => 'nous-accent', 'label' => __('Nous Accent', 'vincentragosta')],
            ],
            'core/columns' => [
                $statStripStyle,
                ['name' => 'pipeline', 'label' => __('Pipeline', 'vincentragosta')],
            ],
            'core/column' => [
                $darkStyle,
            ],
        ];
    }
}
