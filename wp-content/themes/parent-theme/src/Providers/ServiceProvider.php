<?php

namespace ParentTheme\Providers;

use ParentTheme\Providers\Contracts\HasAssets;
use ParentTheme\Providers\Contracts\HasBlocks;
use ParentTheme\Providers\Contracts\Registrable;
use ParentTheme\Traits\HasAssets as HasAssetsTrait;
use ParentTheme\Traits\HasBlocksTrait;

/**
 * Base service provider class.
 *
 * All service providers should extend this class and implement the register method.
 * Provides asset enqueueing capabilities via the HasAssets trait.
 * Provides block registration capabilities via the HasBlocks trait.
 *
 * To register blocks, add a $blocks property and place block directories
 * in a 'blocks' subdirectory relative to your provider class.
 */
abstract class ServiceProvider implements Registrable, HasAssets, HasBlocks
{
    use HasAssetsTrait;
    use HasBlocksTrait;

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
     * to ensure features and blocks are registered.
     */
    public function register(): void
    {
        $this->registerFeatures();
        $this->initializeBlocks();
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
