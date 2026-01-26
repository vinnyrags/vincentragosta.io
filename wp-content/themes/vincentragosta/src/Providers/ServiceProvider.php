<?php

namespace ChildTheme\Providers;

use ChildTheme\Contracts\Registrable;

/**
 * Base service provider class.
 *
 * All service providers should extend this class and implement the register method.
 */
abstract class ServiceProvider implements Registrable
{
    /**
     * Feature classes to register.
     *
     * @var array<class-string<Registrable>>
     */
    protected array $features = [];

    /**
     * Get the provider's assets directory path.
     *
     * Override this in child classes to specify a custom assets path.
     * Returns null if the provider has no assets.
     */
    protected function getAssetsPath(): ?string
    {
        $reflection = new \ReflectionClass($this);
        $providerDir = dirname($reflection->getFileName());
        $assetsDir = $providerDir . '/assets';

        return is_dir($assetsDir) ? $assetsDir : null;
    }

    /**
     * Get the provider's assets directory URI.
     */
    protected function getAssetsUri(): ?string
    {
        $assetsPath = $this->getAssetsPath();
        if (!$assetsPath) {
            return null;
        }

        // Convert filesystem path to URI
        $themePath = get_template_directory();
        $themeUri = get_template_directory_uri();
        $relativePath = str_replace($themePath, '', $assetsPath);

        return $themeUri . $relativePath;
    }

    /**
     * Check if a style file exists in the dist directory.
     */
    protected function hasStyle(string $filename): bool
    {
        return file_exists($this->getDistPath() . '/css/' . $filename);
    }

    /**
     * Get the dist directory path.
     */
    protected function getDistPath(): string
    {
        return get_template_directory() . '/dist';
    }

    /**
     * Get the dist directory URI.
     */
    protected function getDistUri(): string
    {
        return get_template_directory_uri() . '/dist';
    }

    /**
     * Check if a script file exists in the provider's assets directory.
     */
    protected function hasScript(string $filename): bool
    {
        $assetsPath = $this->getAssetsPath();
        return $assetsPath && file_exists($assetsPath . '/js/' . $filename);
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
     * Get the full path to a script file.
     */
    protected function getScriptPath(string $filename): ?string
    {
        $assetsPath = $this->getAssetsPath();
        if (!$assetsPath) {
            return null;
        }

        $path = $assetsPath . '/js/' . $filename;
        return file_exists($path) ? $path : null;
    }

    /**
     * Get the full URI to a script file.
     */
    protected function getScriptUri(string $filename): ?string
    {
        $assetsUri = $this->getAssetsUri();
        return $assetsUri ? $assetsUri . '/js/' . $filename : null;
    }

    /**
     * Enqueue a provider style.
     */
    protected function enqueueStyle(string $handle, string $filename, array $deps = []): void
    {
        $path = $this->getStylePath($filename);
        $uri = $this->getStyleUri($filename);

        if ($path && $uri) {
            wp_enqueue_style($handle, $uri, $deps, filemtime($path));
        }
    }

    /**
     * Enqueue a provider script.
     */
    protected function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void
    {
        $path = $this->getScriptPath($filename);
        $uri = $this->getScriptUri($filename);

        if ($path && $uri) {
            wp_enqueue_script($handle, $uri, $deps, filemtime($path), $inFooter);
        }
    }

    /**
     * Register the service provider.
     *
     * Child classes should override this method and call parent::register()
     * to ensure features are registered.
     */
    public function register(): void
    {
        $this->registerFeatures();
    }

    /**
     * Register all feature classes.
     */
    protected function registerFeatures(): void
    {
        foreach ($this->features as $feature) {
            (new $feature())->register();
        }
    }
}
