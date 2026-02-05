<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Support\Block;

/**
 * Manages block registration and editor script enqueueing for service providers.
 *
 * Blocks are expected in a 'blocks' subdirectory relative to the provider class file.
 * Each block must contain a block.json file.
 */
class BlockManager
{
    public function __construct(
        private readonly string $blocksPath,
        private readonly string $blocksUri,
        private readonly string $distPath,
        private readonly string $distUri,
        /** @var string[] */
        private readonly array $blocks = [],
    ) {}

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
     */
    public function getBlocksPath(): string
    {
        return $this->blocksPath;
    }

    /**
     * Get the URI for the blocks directory.
     */
    public function getBlocksUri(): string
    {
        return $this->blocksUri;
    }

    /**
     * Register all blocks.
     *
     * Called on the 'init' action hook.
     */
    public function registerBlocks(): void
    {
        foreach ($this->blocks as $block) {
            $blockDir = $this->blocksPath . '/' . $block;

            if (file_exists($blockDir . '/block.json')) {
                register_block_type($blockDir);
            }
        }
    }

    /**
     * Enqueue a block editor script from dist/js/.
     *
     * Block editor scripts are output directly to dist/js/ (not in a subdirectory)
     * and include standard WordPress block editor dependencies.
     */
    public function enqueueEditorScript(string $handle, string $filename, array $deps = []): void
    {
        $path = $this->distPath . '/js/' . $filename;
        $uri = $this->distUri . '/js/' . $filename;

        if (!file_exists($path)) {
            return;
        }

        $defaultDeps = [
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-i18n',
            'wp-data',
        ];

        $allDeps = array_unique([...$defaultDeps, ...$deps]);

        wp_enqueue_script($handle, $uri, $allDeps, filemtime($path), true);
    }

    /**
     * Initialize block registration and asset hooks.
     *
     * @param object $provider The provider instance whose enqueueBlockAssets/enqueueBlockEditorAssets methods will be hooked.
     */
    public function initializeHooks(object $provider): void
    {
        if (empty($this->blocks)) {
            return;
        }

        add_action('init', [$this, 'registerBlocks']);
        add_action('enqueue_block_assets', [$provider, 'enqueueBlockAssets']);
        add_action('enqueue_block_editor_assets', [$provider, 'enqueueBlockEditorAssets']);
    }
}
