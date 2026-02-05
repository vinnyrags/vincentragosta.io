<?php

namespace ParentTheme\Providers\Support\Feature;

use ParentTheme\Providers\Contracts\Registrable;
use ParentTheme\Providers\Support\AbstractRegistry;

/**
 * Manages feature registration for service providers.
 *
 * Accepts a normalized feature map of [class-string => bool] and handles
 * filtering and registration. Features set to false are excluded.
 */
class FeatureManager extends AbstractRegistry
{
    /**
     * Instantiate and register all enabled features.
     */
    public function registerAll(): void
    {
        foreach ($this->getEnabled() as $feature) {
            /** @var Registrable $instance */
            $instance = $this->container->get($feature);
            $instance->register();
        }
    }
}
