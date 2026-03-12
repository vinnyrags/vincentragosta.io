<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Support\Block;

/**
 * Manages block registration and editor script enqueueing for service providers.
 *
 * Searches one or more directories for blocks, supporting parent/child theme
 * inheritance. Search paths are derived automatically by the Provider from
 * the class hierarchy — child paths come first so child blocks can override
 * parent blocks of the same name.
 */
class BlockManager
{
    /**
     * @param string[] $searchPaths Directories to search for blocks (child-first order).
     * @param string   $distPath    Absolute path to the active theme's dist/ directory.
     * @param string   $distUri     URI to the active theme's dist/ directory.
     * @param string[] $blocks      Block slugs to register.
     */
    public function __construct(
        private readonly array $searchPaths,
        private readonly string $distPath,
        private readonly string $distUri,
        private readonly array $blocks = [],
    ) {}

    /**
     * Get the block slugs to register.
     *
     * @return string[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Register all blocks.
     *
     * Searches each path in order for each block's block.json.
     * Called on the 'init' action hook.
     */
    public function registerBlocks(): void
    {
        foreach ($this->blocks as $block) {
            foreach ($this->searchPaths as $searchPath) {
                $blockDir = $searchPath . '/' . $block;

                if (file_exists($blockDir . '/block.json')) {
                    register_block_type($blockDir);
                    break;
                }
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
