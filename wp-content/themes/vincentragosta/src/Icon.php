<?php

/**
 * Icon class for retrieving and rendering SVG icons from the theme.
 *
 * Provides a fluent interface for working with SVG icons stored in the theme's
 * assets/images/svg-sprite/ and assets/images/svg/ directories.
 *
 * Usage:
 *   echo new Icon('arrow');
 *   echo Icon::get('arrow')->withClass('icon-lg');
 *   echo Icon::get('squiggle')->withAttributes(['aria-hidden' => 'true']);
 */
class Icon
{
    private string $name;
    private ?string $resolvedPath = null;
    private ?string $type = null;
    private array $attributes = [];

    private const SPRITE_DIR = '/assets/images/svg-sprite/';
    private const SVG_DIR = '/assets/images/svg/';

    public function __construct(string $name)
    {
        $this->name = $this->sanitizeName($name);
        $this->resolve();
    }

    /**
     * Static factory method for fluent usage.
     */
    public static function get(string $name): self
    {
        return new self($name);
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
     * Get the resolved type ('sprite' or 'svg').
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
     * @param string $type 'sprite', 'svg', or 'all'
     * @return array Array of icon data with 'name', 'label', and 'type' keys
     */
    public static function all(string $type = 'all'): array
    {
        $icons = [];
        $themeDir = get_template_directory();

        if ($type === 'sprite' || $type === 'all') {
            $icons = array_merge($icons, self::scanDirectory($themeDir . self::SPRITE_DIR, 'sprite'));
        }

        if ($type === 'svg' || $type === 'all') {
            $icons = array_merge($icons, self::scanDirectory($themeDir . self::SVG_DIR, 'svg'));
        }

        return $icons;
    }

    /**
     * Get icon options formatted for block editor dropdowns.
     *
     * @param string $type 'sprite', 'svg', or 'all'
     * @param string $emptyLabel Label for the empty/no-selection option
     * @return array Array of ['label' => string, 'value' => string]
     */
    public static function options(string $type = 'all', string $emptyLabel = '— No Icon —'): array
    {
        $options = [['label' => $emptyLabel, 'value' => '']];

        foreach (self::all($type) as $icon) {
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
     * @param string $type 'sprite', 'svg', or 'all'
     * @return array Associative array of name => rendered SVG content
     */
    public static function contentMap(string $type = 'all'): array
    {
        $map = [];

        foreach (self::all($type) as $icon) {
            $map[$icon['name']] = (string) new self($icon['name']);
        }

        return $map;
    }

    /**
     * Sanitize the icon name to prevent directory traversal.
     */
    private function sanitizeName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/\.svg$/i', '', $name);
        return $name;
    }

    /**
     * Resolve the icon path by checking directories in order.
     */
    private function resolve(): void
    {
        $themeDir = get_template_directory();

        // Check svg-sprite directory first (exact match)
        $spritePath = $themeDir . self::SPRITE_DIR . $this->name . '.svg';
        if ($this->isValidSvgFile($spritePath)) {
            $this->resolvedPath = $spritePath;
            $this->type = 'sprite';
            return;
        }

        // Check svg-sprite with icon- prefix
        $spriteIconPath = $themeDir . self::SPRITE_DIR . 'icon-' . $this->name . '.svg';
        if ($this->isValidSvgFile($spriteIconPath)) {
            $this->resolvedPath = $spriteIconPath;
            $this->type = 'sprite';
            return;
        }

        // Fall back to svg directory
        $svgPath = $themeDir . self::SVG_DIR . $this->name . '.svg';
        if ($this->isValidSvgFile($svgPath)) {
            $this->resolvedPath = $svgPath;
            $this->type = 'svg';
            return;
        }
    }

    /**
     * Validate that a path points to a readable SVG file.
     */
    private function isValidSvgFile(string $path): bool
    {
        return file_exists($path)
            && is_readable($path)
            && pathinfo($path, PATHINFO_EXTENSION) === 'svg';
    }

    /**
     * Sanitize SVG content to remove potentially harmful elements.
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
     * Apply custom attributes to the SVG element.
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

        // For sprite content (no root <svg> tag), wrap it
        if ($this->type === 'sprite' && stripos($content, '<svg') === false) {
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
     * Scan a directory for SVG files and return icon metadata.
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
            $label = $name;
            $label = preg_replace('/^icon-/', '', $label);
            $label = str_replace(['-', '_'], ' ', $label);
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
