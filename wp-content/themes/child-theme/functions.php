<?php
/**
 * Vincent Ragosta Theme
 */

use ChildTheme\Theme;

// Load parent theme's autoloader first (for ParentTheme namespace).
$parent_autoloader = get_template_directory() . '/vendor/autoload.php';
if (file_exists($parent_autoloader)) {
    require_once $parent_autoloader;
}

// Load child theme's autoloader.
require_once __DIR__ . '/vendor/autoload.php';

(new Theme())->bootstrap();
