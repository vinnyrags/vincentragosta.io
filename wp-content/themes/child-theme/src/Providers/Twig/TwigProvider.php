<?php

namespace ChildTheme\Providers\Twig;

use ParentTheme\Providers\Twig\TwigProvider as BaseTwigProvider;

/**
 * Registers custom Twig functions and filters.
 *
 * Extends the parent theme's TwigProvider.
 * Add child theme specific Twig functions here.
 */
class TwigProvider extends BaseTwigProvider
{
    // Child theme can add additional Twig functions by overriding addTwigFunctions()
}
