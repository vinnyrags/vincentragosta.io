<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use ParentTheme\Providers\Contracts\Hook;

/**
 * Registers custom block styles for text blocks (core/paragraph, core/list, core/code, core/table).
 */
class TextBlockStyles implements Hook
{
    public function register(): void
    {
        add_action('init', [$this, 'registerStyles']);
        add_action('after_setup_theme', [$this, 'removeBundledBlockStyles'], 99);
        add_action('wp_enqueue_scripts', [$this, 'dequeueCoreBlockStyles']);
        add_action('enqueue_block_assets', [$this, 'dequeueCoreBlockStyles']);
    }

    /**
     * Dequeue core block theme stylesheets that conflict with theme styles.
     *
     * The bundled wp-block-library-theme (theme.min.css) is prevented by
     * removing wp-block-styles theme support. Individual per-block theme
     * styles still load automatically — we dequeue only the table's.
     */
    public function dequeueCoreBlockStyles(): void
    {
        wp_dequeue_style('wp-block-table-theme');
    }

    /**
     * Remove the bundled core block theme stylesheet (theme.min.css).
     *
     * Individual per-block theme styles (separator, quote, etc.) still
     * load automatically when those blocks appear on a page.
     */
    public function removeBundledBlockStyles(): void
    {
        remove_theme_support('wp-block-styles');
    }

    /**
     * Register block styles for text blocks.
     */
    public function registerStyles(): void
    {
        $mutedStyle = [
            'name'  => 'muted',
            'label' => __('Muted', 'child-theme'),
        ];

        register_block_style('core/paragraph', $mutedStyle);
        register_block_style('core/list', $mutedStyle);
        register_block_style('core/code', $mutedStyle);
        register_block_style('core/table', $mutedStyle);
        register_block_style('core/button', $mutedStyle);

        register_block_style('core/list', [
            'name'  => 'tags',
            'label' => __('Tags', 'child-theme'),
        ]);

        register_block_style('core/heading', [
            'name'  => 'subheading',
            'label' => __('Subheading', 'child-theme'),
        ]);
    }
}
