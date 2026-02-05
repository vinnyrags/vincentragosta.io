<?php

namespace ChildTheme;

use ChildTheme\Providers\Project\ProjectProvider;
use ChildTheme\Providers\Theme\ThemeProvider;
use ChildTheme\Providers\Twig\TwigProvider;
use ParentTheme\Theme as BaseTheme;

/**
 * Main theme class.
 *
 * Bootstraps the theme by registering service providers.
 * Extends the parent theme's base Theme class.
 */
class Theme extends BaseTheme
{
    /**
     * Service providers to register.
     *
     * @var array<class-string>
     */
    protected array $providers = [
        ThemeProvider::class,
        ProjectProvider::class,
        TwigProvider::class,
    ];
}
