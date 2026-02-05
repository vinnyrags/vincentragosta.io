<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Support\Feature;

use ParentTheme\Providers\Contracts\Registrable;
use ParentTheme\Providers\Support\AbstractRegistry;
use Throwable;

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
     *
     * Catches exceptions per-feature so a single broken feature doesn't
     * prevent subsequent features from registering.
     */
    public function registerAll(): void
    {
        foreach ($this->getEnabled() as $feature) {
            try {
                /** @var Registrable $instance */
                $instance = $this->container->get($feature);
                $instance->register();
            } catch (Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'FeatureManager: Failed to register feature %s: %s',
                    $feature,
                    $e->getMessage()
                ));
            }
        }
    }
}
