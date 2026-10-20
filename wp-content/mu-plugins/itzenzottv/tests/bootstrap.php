<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads WordPress through WorDBless (no database). Test WordPress files live
 * in tests/.wp-test/. No theme is involved: the plugin must not depend on one.
 */

use WorDBless\Load;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Mythus (the Provider base, contracts) and PHP-DI come from Mythus's own vendor.
$mythus_autoloader = dirname(__DIR__, 2) . '/mythus/vendor/autoload.php';
if (file_exists($mythus_autoloader)) {
    require_once $mythus_autoloader;
}

$wp_test_dir = __DIR__ . '/.wp-test';
if (!file_exists($wp_test_dir)) {
    mkdir($wp_test_dir, 0755, true);
}

// Must be defined before WorDBless\Load::load() is called.
define('ABSPATH', $wp_test_dir . '/');

$wp_content_dir = ABSPATH . 'wp-content';
if (!file_exists($wp_content_dir)) {
    mkdir($wp_content_dir, 0755, true);
}

$wp_source = dirname(__DIR__) . '/vendor/roots/wordpress-no-content';
if (is_dir($wp_source)) {
    if (!file_exists(ABSPATH . 'wp-settings.php')) {
        copy($wp_source . '/wp-settings.php', ABSPATH . 'wp-settings.php');
    }
    if (!file_exists(ABSPATH . 'wp-includes') && is_dir($wp_source . '/wp-includes')) {
        symlink($wp_source . '/wp-includes', ABSPATH . 'wp-includes');
    }
    if (!file_exists(ABSPATH . 'wp-admin') && is_dir($wp_source . '/wp-admin')) {
        symlink($wp_source . '/wp-admin', ABSPATH . 'wp-admin');
    }
}

copy(
    dirname(__DIR__) . '/vendor/automattic/wordbless/src/dbless-wpdb.php',
    $wp_content_dir . '/db.php'
);

Load::load();
