<?php

namespace ChildTheme\Providers;

/**
 * Base service provider class.
 *
 * All service providers should extend this class and implement the register method.
 */
abstract class ServiceProvider
{
    /**
     * Register the service provider.
     *
     * This method should add all necessary hooks, filters, and actions.
     */
    abstract public function register(): void;
}
