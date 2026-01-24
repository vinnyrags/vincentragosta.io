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
     * Register the service provider.
     *
     * This method should add all necessary hooks, filters, and actions.
     */
    abstract public function register(): void;
}
