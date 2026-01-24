<?php
/**
 * Vincent Ragosta Theme
 */

use ChildTheme\Theme;

require_once __DIR__ . '/vendor/autoload.php';

Timber\Timber::init();

new Theme();
