<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;

/**
 * Registers the Accent Highlight rich text format in the block editor.
 *
 * Adds a toolbar button that wraps selected text in a <mark> with the
 * .accent-highlight class, styled via var(--wp--preset--color--accent-1).
 */
class AccentHighlight implements Hook
{
    public function register(): void
    {
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorScript']);
    }

    /**
     * Enqueue the accent highlight editor script.
     */
    public function enqueueEditorScript(): void
    {
        $path = get_stylesheet_directory() . '/dist/js/theme/accent-highlight.js';

        if (!file_exists($path)) {
            return;
        }

        wp_enqueue_script(
            'vincentragosta-accent-highlight',
            get_stylesheet_directory_uri() . '/dist/js/theme/accent-highlight.js',
            ['wp-rich-text', 'wp-block-editor', 'wp-element'],
            filemtime($path),
            true
        );
    }
}
