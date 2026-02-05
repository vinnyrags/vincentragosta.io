<?php

namespace ParentTheme\Providers\Twig;

use ParentTheme\Providers\ServiceProvider;
use ParentTheme\Services\IconService;
use Twig\TwigFunction;

/**
 * Base Twig service provider for registering custom functions and filters.
 *
 * Child themes should extend this class to add their own Twig functions.
 */
class TwigProvider extends ServiceProvider
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
        $twig->addFunction(new TwigFunction('icon', function (string $name): IconService {
            return new IconService($name);
        }));

        return $twig;
    }
}
