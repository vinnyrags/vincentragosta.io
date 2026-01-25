<?php

namespace ChildTheme;

use ChildTheme\Contracts\Registrable;
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
     *
     * @var array<class-string<Registrable>>
     */
    protected array $providers = [
        Providers\ThemeService\Provider::class,
        Providers\AssetServiceProvider::class,
        Providers\BlockService\BlockServiceProvider::class,
        Providers\PostTypeServiceProvider::class,
        Providers\TwigServiceProvider::class,
    ];

    public function __construct()
    {
        Timber::$dirname = ['templates', 'views', 'blocks'];

        $this->registerAll($this->providers);

        parent::__construct();
    }

    /**
     * Register all classes that implement Registrable.
     *
     * @param array<class-string<Registrable>> $classes
     */
    protected function registerAll(array $classes): void
    {
        foreach ($classes as $class) {
            (new $class())->register();
        }
    }
}
