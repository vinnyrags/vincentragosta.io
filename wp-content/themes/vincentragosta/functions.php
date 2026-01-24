<?php
/**
 * Timber starter-theme
 * https://github.com/timber/starter-theme
 */

// Load Composer dependencies.
require_once __DIR__ . '/vendor/autoload.php';

// Ensure the StarterSite class is loaded.
require_once __DIR__ . '/src/StarterSite.php';

Timber\Timber::init();

new StarterSite();
