<?php

namespace ChildTheme\Providers\TwigService;

use ChildTheme\Services\IconService;
use ParentTheme\Providers\TwigService\TwigServiceProvider as BaseTwigServiceProvider;
use Twig\TwigFunction;

/**
 * Registers custom Twig functions and filters.
 *
 * Extends the parent theme's TwigServiceProvider to add site-specific functions.
 */
class TwigServiceProvider extends BaseTwigServiceProvider
{
    /**
     * Add custom functions to Twig.
     */
    public function addTwigFunctions(\Twig\Environment $twig): \Twig\Environment
    {
        // Call parent to register any base functions
        $twig = parent::addTwigFunctions($twig);

        // Add the icon function specific to this theme
        $twig->addFunction(new TwigFunction('icon', function (string $name): IconService {
            return new IconService($name);
        }));

        return $twig;
    }
}
