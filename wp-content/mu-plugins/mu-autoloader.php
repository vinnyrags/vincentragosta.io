<?php
/**
 * Plugin Name: MU Plugin Autoloader
 * Description: Loads the root Composer autoloader and all mu-plugin subdirectories.
 */

declare(strict_types=1);

// Load the root Composer autoloader — provides namespaces for all
// Composer-managed mu-plugins and their dependencies.
$rootAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';

if (file_exists($rootAutoload)) {
    require_once $rootAutoload;
}

// Subdirectory mu-plugins to load, in order.
// Each entry is the path to the plugin's main file relative to mu-plugins/.
$plugins = [
    'mythus/mythus.php',
];

foreach ($plugins as $plugin) {
    $path = __DIR__ . '/' . $plugin;

    if (!file_exists($path)) {
        wp_die(
            sprintf(
                'Required mu-plugin <code>%s</code> is not installed. Run <code>composer install</code> from the project root.',
                $plugin
            ),
            'Missing MU Plugin',
            ['response' => 500, 'back_link' => false]
        );
    }

    require_once $path;
}
