<?php

namespace ChildTheme;

use Timber\Site;
use Timber\Timber;

/**
 * Main theme class.
 *
 * Bootstraps the theme by registering service providers.
 */
class Theme extends Site
{
    /**
     * Service providers to register.
     */
    protected array $providers = [
        Providers\ThemeServiceProvider::class,
        Providers\AssetServiceProvider::class,
        Providers\BlockServiceProvider::class,
        Providers\PostTypeServiceProvider::class,
        Providers\TwigServiceProvider::class,
    ];

    public function __construct()
    {
        Timber::$dirname = ['templates', 'views', 'blocks'];

        $this->registerProviders();

        parent::__construct();
    }

    /**
     * Register all service providers.
     */
    protected function registerProviders(): void
    {
        foreach ($this->providers as $providerClass) {
            (new $providerClass())->register();
        }
    }
}
