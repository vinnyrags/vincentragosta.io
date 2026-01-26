<?php

namespace ChildTheme\Contracts;

/**
 * Contract for classes that can enqueue assets.
 *
 * Provides a standard interface for enqueueing styles and scripts
 * from a provider's assets directory or the compiled dist directory.
 */
interface HasAssets
{
    /**
     * Enqueue a stylesheet.
     *
     * @param string $handle Unique handle for the stylesheet.
     * @param string $filename Filename relative to the assets directory.
     * @param array  $deps     Optional. Dependencies array.
     */
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void;

    /**
     * Enqueue a script.
     *
     * @param string $handle   Unique handle for the script.
     * @param string $filename Filename relative to the assets directory.
     * @param array  $deps     Optional. Dependencies array.
     * @param bool   $inFooter Optional. Whether to enqueue in footer.
     */
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void;
}
