<?php

namespace ChildTheme\Providers\PostTypeService;

use ParentTheme\Providers\PostTypeService\PostTypeServiceProvider as BasePostTypeServiceProvider;

/**
 * Registers custom post types from JSON configuration files.
 *
 * Extends the parent theme's PostTypeServiceProvider.
 * Add child theme specific post type registrations here.
 */
class PostTypeServiceProvider extends BasePostTypeServiceProvider
{
    // Child theme can add additional post type logic here if needed
}
