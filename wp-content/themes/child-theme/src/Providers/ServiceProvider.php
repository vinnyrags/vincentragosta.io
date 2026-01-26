<?php

namespace ChildTheme\Providers;

use ParentTheme\Providers\ServiceProvider as BaseServiceProvider;

/**
 * Base service provider for the child theme.
 *
 * Extends the parent theme's ServiceProvider to inherit asset capabilities.
 */
abstract class ServiceProvider extends BaseServiceProvider
{
    // Child theme can add additional functionality here if needed
}
