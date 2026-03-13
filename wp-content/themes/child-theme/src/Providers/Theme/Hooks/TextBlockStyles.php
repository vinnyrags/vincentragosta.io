<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use ParentTheme\Providers\Hooks\BlockStyles;

/**
 * Registers custom block styles for text blocks (core/paragraph, core/list, core/code, core/table).
 *
 * Also removes the bundled core block theme stylesheet and dequeues
 * specific core block styles that conflict with theme styles.
 */
class TextBlockStyles extends BlockStyles
{
    public function register(): void
    {
        parent::register();
        add_action('after_setup_theme', [$this, 'removeBundledBlockStyles'], 99);
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

    /**
     * Remove the bundled core block theme stylesheet (theme.min.css).
     */
    public function removeBundledBlockStyles(): void
    {
        remove_theme_support('wp-block-styles');
    }

    protected function styles(): array
    {
        $mutedStyle = [
            'name'  => 'muted',
            'label' => __('Muted', 'child-theme'),
        ];

        return [
            'core/paragraph' => [$mutedStyle],
            'core/list' => [
                $mutedStyle,
                ['name' => 'tags', 'label' => __('Tags', 'child-theme')],
            ],
            'core/code' => [$mutedStyle],
            'core/table' => [$mutedStyle],
            'core/button' => [$mutedStyle],
            'core/heading' => [
                ['name' => 'subheading', 'label' => __('Subheading', 'child-theme')],
            ],
        ];
    }
}
