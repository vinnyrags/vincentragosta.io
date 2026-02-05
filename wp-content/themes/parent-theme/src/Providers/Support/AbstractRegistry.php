<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Support;

use DI\Container;

/**
 * Abstract base for registries that manage a [class-string => bool] map.
 *
 * Provides normalize, isEnabled, getEnabled, and getDisabled.
 * Subclasses implement registerAll() to define how enabled items are resolved
 * and registered.
 */
abstract class AbstractRegistry
{
    public function __construct(
        /** @var array<class-string, bool> */
        private readonly array $items,
        protected readonly Container $container,
    ) {}

    /**
     * Normalize a mixed array into [class => bool].
     *
     * Indexed entries (ClassName::class) become [ClassName => true].
     * Associative entries (ClassName::class => false) stay as-is.
     *
     * @param array $items Mixed array of class-strings.
     * @return array<class-string, bool>
     */
    public static function normalize(array $items): array
    {
        $normalized = [];

        foreach ($items as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = true;
            } else {
                $normalized[$key] = (bool) $value;
            }
        }

        return $normalized;
    }

    /**
     * Check if a specific item is enabled.
     */
    public function isEnabled(string $item): bool
    {
        return $this->items[$item] ?? false;
    }

    /**
     * Get all enabled class names.
     *
     * @return class-string[]
     */
    public function getEnabled(): array
    {
        return array_keys(array_filter($this->items));
    }

    /**
     * Get all disabled class names.
     *
     * @return class-string[]
     */
    public function getDisabled(): array
    {
        return array_keys(array_filter($this->items, fn(bool $enabled) => !$enabled));
    }

    /**
     * Resolve and register all enabled items.
     */
    abstract public function registerAll(): void;
}
