<?php

namespace ChildTheme\Providers;

use ChildTheme\Services\IconService;
use Twig\TwigFunction;

/**
 * Registers custom Twig functions and filters.
 */
class TwigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        add_filter('timber/twig', [$this, 'addTwigFunctions']);
    }

    /**
     * Add custom functions to Twig.
     */
    public function addTwigFunctions(\Twig\Environment $twig): \Twig\Environment
    {
        $twig->addFunction(new TwigFunction('icon', function (string $name): IconService {
            return new IconService($name);
        }));

        return $twig;
    }
}
