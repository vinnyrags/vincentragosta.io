<?php

declare(strict_types=1);

namespace ParentTheme\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Icon class for retrieving and rendering SVG icons from the theme.
 *
 * Provides a fluent interface for working with SVG icons stored in the theme's
 * src/Providers/Theme/assets/images/svg/ directory.
 *
 * Directory layout:
 *   svg/icons/          — general icons (arrow, chevron, etc.)
 *   svg/icons/social/   — social media icons (instagram, linkedin, etc.)
 *   svg/                — standalone SVGs (logos, decorative graphics)
 *
 * Resolution order for icon('name'):
 *   1. svg/icons/{name}.svg          — direct icon match
 *   2. Recursive scan of svg/icons/  — finds name in subdirectories
 *   3. svg/{name}.svg                — root SVG match
 *
 * Usage (via factory — preferred):
 *   $icon = $iconFactory->create('arrow');
 *   echo $icon->withClass('icon-lg');
 *
 * Direct usage:
 *   echo new IconService('arrow', '/src/Providers/Theme/assets/images/svg/');
 */
class IconService
{
    private readonly string $name;
    private ?string $resolvedPath = null;
    private ?string $type = null;
    private array $attributes = [];

    public function __construct(string $name, private readonly string $svgDir)
    {
        $this->name = $this->sanitizeName($name);
        $this->resolve();
    }

    /**
     * Static factory method for fluent usage.
     */
    public static function get(string $name, string $svgDir): self
    {
        return new self($name, $svgDir);
    }

    /**
     * Add a CSS class to the SVG.
     */
    public function withClass(string $class): self
    {
        $existing = $this->attributes['class'] ?? '';
        $this->attributes['class'] = trim($existing . ' ' . $class);
        return $this;
    }

    /**
     * Add custom attributes to the SVG.
     */
    public function withAttributes(array $attrs): self
    {
        $this->attributes = array_merge($this->attributes, $attrs);
        return $this;
    }

    /**
     * Get the resolved type ('icon' or 'svg').
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Check if the icon was found.
     */
    public function exists(): bool
    {
        return $this->resolvedPath !== null;
    }

    /**
     * Render the SVG content.
     */
    public function render(): string
    {
        if (!$this->resolvedPath) {
            return '';
        }

        $content = file_get_contents($this->resolvedPath);
        if ($content === false) {
            return '';
        }

        $content = $this->sanitizeContent($content);

        if (!empty($this->attributes)) {
            $content = $this->applyAttributes($content);
        }

        return $content;
    }

    /**
     * Allow direct echoing of the Icon object.
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Get all available icons of a given type.
     *
     * Searches child theme first, then parent theme. Child icons win on name conflicts,
     * matching WordPress's template hierarchy pattern.
     *
     * @param string $type 'icon' (svg/icons/), 'svg' (svg/ root), or 'all' (both). 'sprite' is accepted as an alias for 'icon'.
     * @param string $subdir Optional subdirectory within the type's directory (e.g., 'social' for icons, 'squiggle' for svg)
     * @return array<int, array{name: string, label: string, type: string, filename: string}>
     */
    public static function all(string $svgDir, string $type = 'all', string $subdir = ''): array
    {
        // Backward compatibility: 'sprite' maps to 'icon'
        if ($type === 'sprite') {
            $type = 'icon';
        }

        $seen = [];
        $icons = [];
        $subdirPath = $subdir ? $subdir . '/' : '';

        foreach (self::themeDirs() as $themeDir) {
            if ($type === 'icon' || $type === 'all') {
                foreach (self::scanDirectory($themeDir . $svgDir . 'icons/' . $subdirPath, 'icon') as $icon) {
                    if (!isset($seen[$icon['name']])) {
                        $seen[$icon['name']] = true;
                        $icons[] = $icon;
                    }
                }
            }

            if ($type === 'svg' || $type === 'all') {
                foreach (self::scanDirectory($themeDir . $svgDir . $subdirPath, 'svg') as $icon) {
                    if (!isset($seen[$icon['name']])) {
                        $seen[$icon['name']] = true;
                        $icons[] = $icon;
                    }
                }
            }
        }

        return $icons;
    }

    /**
     * Get icon options formatted for block editor dropdowns.
     *
     * @param string $type 'icon', 'svg', or 'all'. 'sprite' is accepted as an alias for 'icon'.
     * @param string $emptyLabel Label for the empty/no-selection option
     * @param string $subdir Optional subdirectory within the type's directory
     * @return array<int, array{label: string, value: string}>
     */
    public static function options(string $svgDir, string $type = 'all', string $emptyLabel = '— No Icon —', string $subdir = ''): array
    {
        $options = [['label' => $emptyLabel, 'value' => '']];

        foreach (self::all($svgDir, $type, $subdir) as $icon) {
            $options[] = [
                'label' => $icon['label'],
                'value' => $icon['name'],
            ];
        }

        return $options;
    }

    /**
     * Get a map of icon names to their rendered content.
     *
     * @param string $type 'icon', 'svg', or 'all'. 'sprite' is accepted as an alias for 'icon'.
     * @param string $subdir Optional subdirectory within the type's directory
     * @return array Associative array of name => rendered SVG content
     */
    public static function contentMap(string $svgDir, string $type = 'all', string $subdir = ''): array
    {
        $map = [];
        foreach (self::all($svgDir, $type, $subdir) as $icon) {
            // Include subdir in the path for resolution
            $iconPath = $subdir ? $subdir . '/' . $icon['name'] : $icon['name'];
            $map[$icon['name']] = (string) new self($iconPath, $svgDir);
        }
        return $map;
    }

    /**
     * Sanitize the icon name to prevent directory traversal.
     *
     * Strips the .svg extension, normalizes slashes, removes traversal sequences,
     * and reduces absolute paths to their basename. Allows subdirectory paths
     * like 'squiggle/squiggle-1' or 'social/instagram'.
     */
    private function sanitizeName(string $name): string
    {
        // Remove .svg extension
        $name = preg_replace('/\.svg$/i', '', $name);

        // Normalize backslashes to forward slashes
        $name = str_replace('\\', '/', $name);

        // Remove all directory traversal sequences
        while (str_contains($name, '..')) {
            $name = str_replace('..', '', $name);
        }

        // Clean up multiple consecutive slashes
        $name = preg_replace('#/+#', '/', $name);

        // If path starts with /, it's absolute - only keep basename
        if (str_starts_with($name, '/')) {
            $name = basename($name);
        }

        // Trim any remaining leading/trailing slashes
        $name = trim($name, '/');

        return $name;
    }

    /**
     * Resolve the icon path by checking directories in priority order.
     *
     * Searches child theme first, then parent theme (matching WordPress template hierarchy).
     * For each theme directory, checks:
     *   1. svg/icons/{name}.svg — direct icon match (handles 'arrow', 'social/instagram')
     *   2. Recursive scan of svg/icons/ — finds 'instagram' in icons/social/ without knowing the path
     *   3. svg/{name}.svg — root SVGs ('vr-logo', 'squiggle/squiggle-1')
     */
    private function resolve(): void
    {
        foreach (self::themeDirs() as $themeDir) {
            // 1. Direct match in icons directory
            $iconPath = $themeDir . $this->svgDir . 'icons/' . $this->name . '.svg';
            if ($this->isValidSvgFile($iconPath)) {
                $this->resolvedPath = $iconPath;
                $this->type = 'icon';
                return;
            }

            // 2. Recursive scan of icons/ subdirectories
            $iconsDir = $themeDir . $this->svgDir . 'icons/';
            $found = $this->findInSubdirectories($iconsDir, $this->name);
            if ($found !== null) {
                $this->resolvedPath = $found;
                $this->type = 'icon';
                return;
            }

            // 3. Fall back to root svg directory
            $svgPath = $themeDir . $this->svgDir . $this->name . '.svg';
            if ($this->isValidSvgFile($svgPath)) {
                $this->resolvedPath = $svgPath;
                $this->type = 'svg';
                return;
            }
        }
    }

    /**
     * Search subdirectories recursively for an SVG file matching the given name.
     *
     * Only scans subdirectories (not the root of $dir itself, since that's
     * already checked by the direct match in resolve()).
     */
    private function findInSubdirectories(string $dir, string $name): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $target = $name . '.svg';

        foreach ($iterator as $file) {
            if ($file->getFilename() === $target && $this->isValidSvgFile($file->getPathname())) {
                // Skip files in the root icons/ dir (already checked in step 1)
                if (dirname($file->getPathname()) === rtrim($dir, '/')) {
                    continue;
                }
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * Validate that a path points to a readable file with an .svg extension.
     */
    private function isValidSvgFile(string $path): bool
    {
        return file_exists($path)
            && is_readable($path)
            && pathinfo($path, PATHINFO_EXTENSION) === 'svg';
    }

    /**
     * Sanitize SVG content to remove potentially harmful elements.
     *
     * Strips script tags, event handler attributes, XML declarations,
     * and DOCTYPE nodes from the raw SVG string.
     */
    private function sanitizeContent(string $content): string
    {
        $content = trim($content);

        // Remove script tags
        $content = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $content);

        // Remove event handlers
        $content = preg_replace('/\s(on\w+)=("|\').*?\2/is', '', $content);

        // Remove XML declaration
        $content = preg_replace('/^\s*<\?xml.*?\?>\s*/s', '', $content);

        // Remove DOCTYPE
        $content = preg_replace('/^<!DOCTYPE[^>]*?>/is', '', $content);

        return trim($content);
    }

    /**
     * Apply custom attributes to the root SVG element.
     *
     * For content without a root <svg> tag, wraps the content in one.
     * For standard SVGs, injects the attributes into the existing <svg> tag.
     */
    private function applyAttributes(string $content): string
    {
        if (empty($this->attributes)) {
            return $content;
        }

        // Build attribute string
        $attrString = '';
        foreach ($this->attributes as $key => $value) {
            $key = esc_attr($key);
            $value = esc_attr($value);
            $attrString .= " {$key}=\"{$value}\"";
        }

        // For content without a root <svg> tag, wrap it
        if (!str_contains(strtolower($content), '<svg')) {
            return '<svg' . $attrString . ' xmlns="http://www.w3.org/2000/svg">' . $content . '</svg>';
        }

        // Insert attributes into existing <svg> tag
        $content = preg_replace(
            '/^(<svg)(\s|>)/i',
            '$1' . $attrString . '$2',
            $content,
            1
        );

        return $content;
    }

    /**
     * Get theme directories to search, child first then parent.
     *
     * When a child theme is active, returns [child_dir, parent_dir].
     * When no child theme is active, returns [theme_dir] (no duplicates).
     *
     * @return string[]
     */
    private static function themeDirs(): array
    {
        $dirs = [get_stylesheet_directory()];
        $parentDir = get_template_directory();
        if ($parentDir !== $dirs[0]) {
            $dirs[] = $parentDir;
        }
        return $dirs;
    }

    /**
     * Scan a directory for SVG files and return icon metadata.
     *
     * @return array<int, array{name: string, label: string, type: string, filename: string}>
     */
    private static function scanDirectory(string $dir, string $type): array
    {
        $icons = [];

        if (!is_dir($dir)) {
            return $icons;
        }

        $files = glob($dir . '*.svg');
        if (!$files) {
            return $icons;
        }

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $name = pathinfo($filename, PATHINFO_FILENAME);

            // Generate a human-readable label
            $label = str_replace(['-', '_'], ' ', $name);
            $label = ucwords($label);

            $icons[] = [
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'filename' => $filename,
            ];
        }

        return $icons;
    }
}
