<?php

declare(strict_types=1);

namespace ChildTheme;

use ChildTheme\Providers\Blog\BlogProvider;
use ChildTheme\Providers\Project\ProjectProvider;
use ChildTheme\Providers\Theme\ThemeProvider;
use IX\Theme as BaseTheme;

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
        BlogProvider::class,
    ];
}
