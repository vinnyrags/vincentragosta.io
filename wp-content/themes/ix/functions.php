<?php
/**
 * IX functions and definitions.
 *
 * This parent theme provides base classes and infrastructure.
 * Child themes should handle initialization and provider registration.
 *
 * @package IX
 */

declare(strict_types=1);

// Theme version constant.
define('IX_VERSION', '1.0.0');

// Load Composer autoloader if it exists in parent theme.
$parent_autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($parent_autoloader)) {
    require_once $parent_autoloader;
}
