<?php

namespace ParentTheme\Contracts;

/**
 * Interface for classes that can be registered with WordPress.
 */
interface Registrable
{
    /**
     * Register hooks, filters, and other WordPress integrations.
     */
    public function register(): void;
}
