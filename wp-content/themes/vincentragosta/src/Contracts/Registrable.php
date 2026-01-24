<?php

namespace ChildTheme\Contracts;

/**
 * Interface for classes that can be registered with WordPress.
 *
 * Any class that hooks into WordPress (adding actions, filters, etc.)
 * should implement this interface.
 */
interface Registrable
{
    /**
     * Register hooks, filters, and actions with WordPress.
     */
    public function register(): void;
}
