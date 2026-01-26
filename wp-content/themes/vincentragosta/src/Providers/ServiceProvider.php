<?php

namespace ChildTheme\Providers;

use ChildTheme\Contracts\HasAssets;
use ChildTheme\Contracts\Registrable;
use ChildTheme\Traits\HasAssets as HasAssetsTrait;

/**
 * Base service provider class.
 *
 * All service providers should extend this class and implement the register method.
 * Provides asset enqueueing capabilities via the HasAssets trait.
 */
abstract class ServiceProvider implements Registrable, HasAssets
{
    use HasAssetsTrait;

    /**
     * Feature classes to register.
     *
     * @var array<class-string<Registrable>>
     */
    protected array $features = [];

    /**
     * Register the service provider.
     *
     * Child classes should override this method and call parent::register()
     * to ensure features are registered.
     */
    public function register(): void
    {
        $this->registerFeatures();
    }

    /**
     * Register all feature classes.
     */
    protected function registerFeatures(): void
    {
        foreach ($this->features as $feature) {
            (new $feature())->register();
        }
    }
}
