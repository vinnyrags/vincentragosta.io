<?php

namespace ChildTheme\Providers\TwigService;

use ParentTheme\Providers\TwigService\TwigServiceProvider as BaseTwigServiceProvider;

/**
 * Registers custom Twig functions and filters.
 *
 * Extends the parent theme's TwigServiceProvider.
 * Add child theme specific Twig functions here.
 */
class TwigServiceProvider extends BaseTwigServiceProvider
{
    // Child theme can add additional Twig functions by overriding addTwigFunctions()
}
