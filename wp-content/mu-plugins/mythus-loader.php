<?php
/**
 * Plugin Name: Mythus Loader
 * Description: Loads the Mythus platform framework from its subdirectory.
 */

declare(strict_types=1);

$mythusAutoload = __DIR__ . '/mythus/vendor/autoload.php';

if (!file_exists($mythusAutoload)) {
    wp_die(
        'Mythus framework is not installed. Run <code>composer install</code> in <code>wp-content/mu-plugins/mythus/</code>.',
        'Mythus Missing',
        ['response' => 500, 'back_link' => false]
    );
}

require_once $mythusAutoload;
require_once __DIR__ . '/mythus/mythus.php';
