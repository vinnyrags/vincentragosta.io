<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Theme\Features;

use Mythus\Contracts\Feature;

/**
 * Opt-in WPForms floating label styles.
 *
 * Enqueues CSS for label positioning (absolute, transform, :placeholder-shown
 * transitions) and JS that adds placeholder=" " to enable the CSS selectors.
 *
 * Not included in parent's $features by default — child themes opt in
 * by adding WpFormsFloatingLabels::class to their $features array.
 */
class WpFormsFloatingLabels implements Feature
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('enqueue_block_assets', [$this, 'enqueueEditorAssets']);
    }

    public function enqueueAssets(): void
    {
        $cssPath = get_template_directory() . '/dist/css/features/wpforms-float-labels.css';

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'parent-theme-wpforms-float-labels',
                get_template_directory_uri() . '/dist/css/features/wpforms-float-labels.css',
                [],
                filemtime($cssPath)
            );
        }

        $jsPath = get_template_directory() . '/dist/js/theme/wpforms-float-labels.js';

        if (file_exists($jsPath)) {
            wp_enqueue_script(
                'parent-theme-wpforms-float-labels',
                get_template_directory_uri() . '/dist/js/theme/wpforms-float-labels.js',
                [],
                filemtime($jsPath),
                true
            );
        }
    }

    public function enqueueEditorAssets(): void
    {
        if (! is_admin()) {
            return;
        }

        $cssPath = get_template_directory() . '/dist/css/features/wpforms-float-labels.css';

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'parent-theme-wpforms-float-labels-editor',
                get_template_directory_uri() . '/dist/css/features/wpforms-float-labels.css',
                [],
                filemtime($cssPath)
            );
        }
    }
}
