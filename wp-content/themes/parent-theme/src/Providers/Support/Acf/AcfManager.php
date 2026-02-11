<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Support\Acf;

/**
 * Manages ACF JSON sync paths for providers.
 *
 * Each provider gets its own AcfManager instance during setup().
 * If the provider has an acf-json/ directory, the manager registers
 * it as an ACF JSON load path. Silent if the directory doesn't exist.
 */
class AcfManager
{
    private readonly string $acfJsonPath;

    public function __construct(string $providerDir)
    {
        $this->acfJsonPath = $providerDir . '/acf-json';
    }

    /**
     * Whether this provider has an acf-json directory.
     */
    public function hasAcfJson(): bool
    {
        return is_dir($this->acfJsonPath);
    }

    /**
     * Get the acf-json directory path.
     */
    public function getAcfJsonPath(): string
    {
        return $this->acfJsonPath;
    }

    /**
     * Register ACF JSON load path filter.
     *
     * No-op if the acf-json directory doesn't exist.
     */
    public function initializeHooks(): void
    {
        if (!$this->hasAcfJson()) {
            return;
        }

        add_filter('acf/settings/load_json', [$this, 'addLoadPath']);
    }

    /**
     * Add this provider's acf-json directory to ACF's load paths.
     *
     * @param string[] $paths Existing ACF JSON load paths.
     * @return string[]
     */
    public function addLoadPath(array $paths): array
    {
        $paths[] = $this->acfJsonPath;

        return $paths;
    }

    /**
     * Set this provider's acf-json directory as the ACF JSON save path.
     *
     * Call this in a provider's register() method to make this provider
     * the target for saving new or updated field groups.
     */
    public function registerSavePath(): void
    {
        if (!$this->hasAcfJson()) {
            return;
        }

        add_filter('acf/settings/save_json', fn () => $this->acfJsonPath);
    }
}
