<?php

namespace ParentTheme\Traits;

use ReflectionClass;

/**
 * Trait for service providers that register blocks.
 *
 * Provides default implementations for the HasBlocks interface.
 * Blocks are expected to be in a 'blocks' subdirectory relative to the provider class file.
 *
 * Each provider is responsible for:
 * - Registering its blocks via registerBlocks()
 * - Enqueueing block assets via enqueueBlockAssets() and enqueueBlockEditorAssets()
 */
trait HasBlocksTrait
{
    /**
     * Blocks to register.
     *
     * Override this in your provider to specify which blocks to register.
     *
     * @var string[]
     */
    protected array $blocks = [];

    /**
     * Get the blocks to register.
     *
     * @return string[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Get the base path for blocks.
     *
     * By default, looks for a 'blocks' directory relative to the provider class file.
     *
     * @return string
     */
    public function getBlocksPath(): string
    {
        $reflection = new ReflectionClass($this);
        $providerDir = dirname($reflection->getFileName());

        return $providerDir . '/blocks';
    }

    /**
     * Get the URI for the blocks directory.
     *
     * @return string
     */
    public function getBlocksUri(): string
    {
        $blocksPath = $this->getBlocksPath();
        $themePath = get_stylesheet_directory();
        $themeUri = get_stylesheet_directory_uri();

        // Convert absolute path to URI
        $relativePath = str_replace($themePath, '', $blocksPath);

        return $themeUri . $relativePath;
    }

    /**
     * Register all blocks.
     *
     * Called on the 'init' action hook.
     */
    public function registerBlocks(): void
    {
        $blocksPath = $this->getBlocksPath();

        foreach ($this->getBlocks() as $block) {
            $blockDir = $blocksPath . '/' . $block;

            if (file_exists($blockDir . '/block.json')) {
                register_block_type($blockDir);
            }
        }
    }

    /**
     * Enqueue block assets for both frontend and editor.
     *
     * Override this method to enqueue styles/scripts for your blocks.
     * Called on 'enqueue_block_assets' hook.
     */
    public function enqueueBlockAssets(): void
    {
        // Override in provider to enqueue block assets
    }

    /**
     * Enqueue block editor assets only.
     *
     * Override this method to enqueue editor-only scripts for your blocks.
     * Called on 'enqueue_block_editor_assets' hook.
     */
    public function enqueueBlockEditorAssets(): void
    {
        // Override in provider to enqueue editor assets
    }

    /**
     * Enqueue a block editor script from dist/js/.
     *
     * Block editor scripts are output directly to dist/js/ (not in a subdirectory)
     * and include standard WordPress block editor dependencies.
     *
     * @param string $handle   Unique handle for the script.
     * @param string $filename Filename relative to dist/js/.
     * @param array  $deps     Optional. Additional dependencies to merge with defaults.
     */
    protected function enqueueEditorScript(string $handle, string $filename, array $deps = []): void
    {
        $distPath = get_stylesheet_directory() . '/dist';
        $distUri = get_stylesheet_directory_uri() . '/dist';
        $path = $distPath . '/js/' . $filename;
        $uri = $distUri . '/js/' . $filename;

        if (!file_exists($path)) {
            return;
        }

        // Standard WordPress block editor dependencies
        $defaultDeps = [
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-i18n',
            'wp-data',
        ];

        $allDeps = array_unique(array_merge($defaultDeps, $deps));

        wp_enqueue_script($handle, $uri, $allDeps, filemtime($path), true);
    }

    /**
     * Initialize block registration and asset hooks.
     *
     * Called automatically in ServiceProvider::register() when blocks are defined.
     */
    protected function initializeBlocks(): void
    {
        if (empty($this->blocks)) {
            return;
        }

        add_action('init', [$this, 'registerBlocks']);
        add_action('enqueue_block_assets', [$this, 'enqueueBlockAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
    }
}
