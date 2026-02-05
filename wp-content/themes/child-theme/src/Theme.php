<?php

namespace ChildTheme;

use ChildTheme\Providers\Project\ProjectProvider;
use ChildTheme\Providers\Theme\ThemeProvider;
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
    ];

    protected function getContainerDefinitions(): array
    {
        return array_merge(parent::getContainerDefinitions(), [
            get_stylesheet_directory() . '/src/config/container.php',
        ]);
    }
}
