<?php

namespace ParentTheme\Providers\Contracts;

/**
 * Interface for service providers that register blocks.
 *
 * Implement this interface to enable block registration within a service provider.
 * Blocks should be located in a 'blocks' subdirectory relative to the provider.
 */
interface HasBlocks
{
    /**
     * Get the blocks to register.
     *
     * @return string[] Array of block directory names
     */
    public function getBlocks(): array;

    /**
     * Get the base path for blocks.
     *
     * @return string Absolute path to the blocks directory
     */
    public function getBlocksPath(): string;

    /**
     * Get the URI for the blocks directory.
     *
     * @return string URL to the blocks directory
     */
    public function getBlocksUri(): string;

    /**
     * Register all blocks.
     */
    public function registerBlocks(): void;

    /**
     * Enqueue block assets for both frontend and editor.
     */
    public function enqueueBlockAssets(): void;

    /**
     * Enqueue block editor assets only.
     */
    public function enqueueBlockEditorAssets(): void;
}
