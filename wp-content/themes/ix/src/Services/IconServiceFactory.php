<?php

declare(strict_types=1);

namespace IX\Services;

/**
 * Factory for creating IconService instances.
 *
 * Provides an injectable service for creating IconService objects,
 * replacing direct `new IconService()` calls throughout the codebase.
 * This enables proper dependency injection and easier testing.
 *
 * The SVG base path is injected via the constructor and bound in the
 * DI container definitions. This decouples IconService from any specific
 * provider's directory structure.
 */
class IconServiceFactory
{
    /**
     * @param string $svgDir Relative path within the theme to the SVG directory (e.g., '/src/Providers/Theme/assets/images/svg/').
     */
    public function __construct(
        private readonly string $svgDir = '/src/Providers/Theme/assets/images/svg/',
    ) {
    }

    /**
     * Create an IconService instance for the given icon name.
     */
    public function create(string $name): IconService
    {
        return new IconService($name, $this->svgDir);
    }

    /**
     * Get all available icons of a given type.
     *
     * @param string $type 'icon' (svg/icons/), 'svg' (svg/ root), or 'all' (both). 'sprite' is accepted as an alias for 'icon'.
     * @param string $subdir Optional subdirectory within the type's directory (e.g., 'social' for icons)
     * @return array<int, array{name: string, label: string, type: string, filename: string}>
     */
    public function all(string $type = 'all', string $subdir = ''): array
    {
        return IconService::all($this->svgDir, $type, $subdir);
    }

    /**
     * Get icon options formatted for block editor dropdowns.
     *
     * @param string $type 'icon', 'svg', or 'all'. 'sprite' is accepted as an alias for 'icon'.
     * @param string $emptyLabel Label for the empty/no-selection option
     * @param string $subdir Optional subdirectory within the type's directory
     * @return array<int, array{label: string, value: string}>
     */
    public function options(string $type = 'all', string $emptyLabel = '— No Icon —', string $subdir = ''): array
    {
        return IconService::options($this->svgDir, $type, $emptyLabel, $subdir);
    }

    /**
     * Get a map of icon names to their rendered content.
     *
     * @param string $type 'icon', 'svg', or 'all'. 'sprite' is accepted as an alias for 'icon'.
     * @param string $subdir Optional subdirectory within the type's directory
     * @return array<string, string> Associative array of name => rendered SVG content
     */
    public function contentMap(string $type = 'all', string $subdir = ''): array
    {
        return IconService::contentMap($this->svgDir, $type, $subdir);
    }
}
