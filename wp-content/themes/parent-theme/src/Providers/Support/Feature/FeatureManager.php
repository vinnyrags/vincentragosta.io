<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Support\Feature;

use ParentTheme\Providers\Contracts\Feature;
use ParentTheme\Providers\Support\AbstractRegistry;
use Throwable;

/**
 * Manages feature registration for service providers.
 *
 * Accepts a normalized feature map of [class-string => bool] and handles
 * filtering and registration. Features set to false are excluded.
 * Only classes implementing the Feature interface are registered;
 * plain Registrable classes placed in $features are skipped with a warning.
 */
class FeatureManager extends AbstractRegistry
{
    /**
     * Instantiate and register all enabled features.
     *
     * Catches exceptions per-feature so a single broken feature doesn't
     * prevent subsequent features from registering. Validates that each
     * resolved instance implements Feature — plain Registrable classes
     * should use $hooks instead.
     */
    public function registerAll(): void
    {
        foreach ($this->getEnabled() as $feature) {
            try {
                $instance = $this->container->get($feature);

                if (!$instance instanceof Feature) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log(sprintf(
                        'FeatureManager: %s does not implement Feature. '
                        . 'Move it to $hooks for always-active behavior, '
                        . 'or implement Feature for toggleable features.',
                        $feature
                    ));
                    continue;
                }

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
