<?php

namespace ParentTheme\Providers\TwigService;

use ParentTheme\Providers\ServiceProvider;

/**
 * Base Twig service provider for registering custom functions and filters.
 *
 * Child themes should extend this class to add their own Twig functions.
 */
class TwigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        add_filter('timber/twig', [$this, 'addTwigFunctions']);
    }

    /**
     * Add custom functions to Twig.
     *
     * Child classes should override this method, call parent::addTwigFunctions(),
     * then add their own functions.
     */
    public function addTwigFunctions(\Twig\Environment $twig): \Twig\Environment
    {
        // Base implementation - child themes can add their own functions
        return $twig;
    }
}
