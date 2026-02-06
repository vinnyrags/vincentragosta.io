<?php

declare(strict_types=1);

namespace ParentTheme\Services;

/**
 * Factory for creating IconService instances.
 *
 * Provides an injectable service for creating IconService objects,
 * replacing direct `new IconService()` calls throughout the codebase.
 * This enables proper dependency injection and easier testing.
 */
class IconServiceFactory
{
    /**
     * Create an IconService instance for the given icon name.
     */
    public function create(string $name): IconService
    {
        return new IconService($name);
    }

    /**
     * Get all available icons of a given type.
     *
     * @param string $type 'sprite', 'svg', or 'all'
     * @param string $subdir Optional subdirectory within the type's directory
     * @return array<int, array{name: string, label: string, type: string, filename: string}>
     */
    public function all(string $type = 'all', string $subdir = ''): array
    {
        return IconService::all($type, $subdir);
    }

    /**
     * Get icon options formatted for block editor dropdowns.
     *
     * @param string $type 'sprite', 'svg', or 'all'
     * @param string $emptyLabel Label for the empty/no-selection option
     * @param string $subdir Optional subdirectory within the type's directory
     * @return array<int, array{label: string, value: string}>
     */
    public function options(string $type = 'all', string $emptyLabel = '— No Icon —', string $subdir = ''): array
    {
        return IconService::options($type, $emptyLabel, $subdir);
    }

    /**
     * Get a map of icon names to their rendered content.
     *
     * @param string $type 'sprite', 'svg', or 'all'
     * @param string $subdir Optional subdirectory within the type's directory
     * @return array<string, string> Associative array of name => rendered SVG content
     */
    public function contentMap(string $type = 'all', string $subdir = ''): array
    {
        return IconService::contentMap($type, $subdir);
    }
}
