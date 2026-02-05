<?php

namespace ChildTheme\Providers;

use ParentTheme\Providers\Provider as BaseProvider;

/**
 * Base provider for the child theme.
 *
 * Extends the parent theme's Provider to inherit asset capabilities.
 */
abstract class Provider extends BaseProvider
{
    // Child theme can add additional functionality here if needed
}
