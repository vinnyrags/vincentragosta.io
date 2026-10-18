<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Hooks\BlockStyles;

/**
 * Registers custom block styles for text blocks (core/paragraph, core/list, core/code,
 * core/preformatted, core/table, core/button, core/heading).
 *
 * Also dequeues specific core block styles that conflict with theme styles.
 */
class TextBlockStyles extends BlockStyles
{
    public function register(): void
    {
        parent::register();
        add_action('wp_enqueue_scripts', [$this, 'dequeueCoreBlockStyles']);
        add_action('enqueue_block_assets', [$this, 'dequeueCoreBlockStyles']);
    }

    /**
     * Dequeue core block theme stylesheets that conflict with theme styles.
     */
    public function dequeueCoreBlockStyles(): void
    {
        wp_dequeue_style('wp-block-table-theme');
    }

    protected function styles(): array
    {
        $mutedStyle = [
            'name'  => 'muted',
            'label' => __('Muted', 'vincentragosta'),
        ];

        $terminalStyle = [
            'name'  => 'terminal',
            'label' => __('Terminal', 'vincentragosta'),
        ];

        return [
            'core/paragraph' => [
                $mutedStyle,
                ['name' => 'eyebrow', 'label' => __('Eyebrow', 'vincentragosta')],
                ['name' => 'status', 'label' => __('Status', 'vincentragosta')],
            ],
            'core/list' => [
                $mutedStyle,
                ['name' => 'pills', 'label' => __('Pills', 'vincentragosta')],
                ['name' => 'chips', 'label' => __('Chips', 'vincentragosta')],
                ['name' => 'index', 'label' => __('Index', 'vincentragosta')],
                ['name' => 'rows', 'label' => __('Rows', 'vincentragosta')],
                ['name' => 'arrows', 'label' => __('Arrows', 'vincentragosta')],
                ['name' => 'checks', 'label' => __('Checks', 'vincentragosta')],
            ],
            'core/code' => [$mutedStyle, $terminalStyle],
            'core/preformatted' => [$terminalStyle],
            'core/table' => [
                $mutedStyle,
                ['name' => 'spec', 'label' => __('Spec', 'vincentragosta')],
            ],
            'core/button' => [$mutedStyle],
            'core/heading' => [
                ['name' => 'subheading', 'label' => __('Subheading', 'vincentragosta')],
            ],
        ];
    }
}
