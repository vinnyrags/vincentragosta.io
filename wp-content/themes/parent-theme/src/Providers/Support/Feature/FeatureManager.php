<?php

namespace ParentTheme\Providers\Support\Feature;

use DI\Container;
use ParentTheme\Providers\Contracts\Registrable;

/**
 * Manages feature registration for service providers.
 *
 * Accepts a normalized feature map of [class-string => bool] and handles
 * filtering and registration. Features set to false are excluded.
 */
class FeatureManager
{
    public function __construct(
        /** @var array<class-string, bool> */
        private readonly array $features,
        private readonly Container $container,
    ) {}

    /**
     * Normalize a mixed features array into [class => bool].
     *
     * Indexed entries (ClassName::class) become [ClassName => true].
     * Associative entries (ClassName::class => false) stay as-is.
     *
     * @param array $features Mixed array of features.
     * @return array<class-string, bool>
     */
    public static function normalize(array $features): array
    {
        $normalized = [];

        foreach ($features as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = true;
            } else {
                $normalized[$key] = (bool) $value;
            }
        }

        return $normalized;
    }

    /**
     * Check if a specific feature is enabled.
     */
    public function isEnabled(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    /**
     * Get all enabled feature class names.
     *
     * @return class-string[]
     */
    public function getEnabled(): array
    {
        return array_keys(array_filter($this->features));
    }

    /**
     * Get all disabled feature class names.
     *
     * @return class-string[]
     */
    public function getDisabled(): array
    {
        return array_keys(array_filter($this->features, fn(bool $enabled) => !$enabled));
    }

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
