<?php

namespace ParentTheme\Traits;

/**
 * Provides asset enqueueing functionality.
 *
 * This trait implements the HasAssets contract with default behavior for
 * locating and enqueueing styles and scripts from the dist directory.
 *
 * Styles are loaded from:  dist/css/{filename}
 * Scripts are loaded from: dist/js/{provider-name}/{filename}
 */
trait HasAssets
{
    /**
     * Get the compiled dist directory path.
     */
    protected function getDistPath(): string
    {
        return get_stylesheet_directory() . '/dist';
    }

    /**
     * Get the compiled dist directory URI.
     */
    protected function getDistUri(): string
    {
        return get_stylesheet_directory_uri() . '/dist';
    }

    /**
     * Get the provider's kebab-case name for asset paths.
     *
     * Converts class name like "BlockServiceProvider" to "block-service".
     */
    protected function getProviderSlug(): string
    {
        $reflection = new \ReflectionClass($this);
        $className = $reflection->getShortName();

        // Remove common suffixes
        $name = preg_replace('/Provider$/', '', $className);

        // Convert PascalCase to kebab-case
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
    }

    /**
     * Check if a style file exists in the dist directory.
     */
    protected function hasStyle(string $filename): bool
    {
        return file_exists($this->getDistPath() . '/css/' . $filename);
    }

    /**
     * Check if a script file exists in the dist directory.
     */
    protected function hasScript(string $filename): bool
    {
        $slug = $this->getProviderSlug();
        return file_exists($this->getDistPath() . '/js/' . $slug . '/' . $filename);
    }

    /**
     * Get the full path to a style file in dist/css.
     */
    protected function getStylePath(string $filename): ?string
    {
        $path = $this->getDistPath() . '/css/' . $filename;
        return file_exists($path) ? $path : null;
    }

    /**
     * Get the full URI to a style file in dist/css.
     */
    protected function getStyleUri(string $filename): ?string
    {
        return $this->getDistUri() . '/css/' . $filename;
    }

    /**
     * Get the full path to a script file in dist/js/{provider-name}/.
     */
    protected function getScriptPath(string $filename): ?string
    {
        $slug = $this->getProviderSlug();
        $path = $this->getDistPath() . '/js/' . $slug . '/' . $filename;
        return file_exists($path) ? $path : null;
    }

    /**
     * Get the full URI to a script file in dist/js/{provider-name}/.
     */
    protected function getScriptUri(string $filename): ?string
    {
        $slug = $this->getProviderSlug();
        return $this->getDistUri() . '/js/' . $slug . '/' . $filename;
    }

    /**
     * Enqueue a stylesheet from the dist/css directory.
     *
     * @param string $handle   Unique handle for the stylesheet.
     * @param string $filename Filename relative to dist/css.
     * @param array  $deps     Optional. Dependencies array.
     */
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void
    {
        $path = $this->getStylePath($filename);
        $uri = $this->getStyleUri($filename);

        if ($path && $uri) {
            wp_enqueue_style($handle, $uri, $deps, filemtime($path));
        }
    }

    /**
     * Enqueue a script from the dist/js/{provider-name}/ directory.
     *
     * @param string $handle   Unique handle for the script.
     * @param string $filename Filename relative to dist/js/{provider-name}/.
     * @param array  $deps     Optional. Dependencies array.
     * @param bool   $inFooter Optional. Whether to enqueue in footer. Default true.
     */
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void
    {
        $path = $this->getScriptPath($filename);
        $uri = $this->getScriptUri($filename);

        if ($path && $uri) {
            wp_enqueue_script($handle, $uri, $deps, filemtime($path), $inFooter);
        }
    }
}
