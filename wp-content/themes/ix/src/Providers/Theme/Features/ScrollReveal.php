<?php

declare(strict_types=1);

namespace IX\Providers\Theme\Features;

use Mythus\Contracts\Feature;

/**
 * Opt-in scroll reveal animations.
 *
 * Enqueues CSS that provides fade-up transitions for elements with the
 * `.fade-up` class. JS adds `.is-visible` on intersection.
 *
 * Not included in parent's $features by default — child themes opt in
 * by adding ScrollReveal::class to their $features array.
 */
class ScrollReveal implements Feature
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        $path = get_template_directory() . '/dist/css/features/scroll-reveal.css';

        if (! file_exists($path)) {
            return;
        }

        wp_enqueue_style(
            'ix-scroll-reveal',
            get_template_directory_uri() . '/dist/css/features/scroll-reveal.css',
            [],
            filemtime($path)
        );
    }
}
