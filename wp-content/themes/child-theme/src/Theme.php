<?php

namespace ChildTheme;

use ChildTheme\Providers\AssetServiceProvider;
use ChildTheme\Providers\BlockService\BlockServiceProvider;
use ChildTheme\Providers\PostTypeServiceProvider;
use ChildTheme\Providers\ThemeService\ThemeServiceProvider;
use ChildTheme\Providers\TwigServiceProvider;
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
        ThemeServiceProvider::class,
        AssetServiceProvider::class,
        BlockServiceProvider::class,
        PostTypeServiceProvider::class,
        TwigServiceProvider::class,
    ];
}
