<?php
/**
 * PHPUnit bootstrap file.
 *
 * Sets up the WordPress test environment using WorDBless.
 * Test WordPress files are stored in tests/.wp-test/ to keep them
 * associated with the test suite.
 */

use WorDBless\Load;

// Load Composer autoloaders.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load parent theme autoloader for IX namespace.
$parent_autoloader = dirname(dirname(__DIR__)) . '/ix/vendor/autoload.php';
if (file_exists($parent_autoloader)) {
    require_once $parent_autoloader;
}

// Load Mythus autoloader for framework classes.
$mythus_autoloader = dirname(dirname(dirname(__DIR__))) . '/mu-plugins/mythus/vendor/autoload.php';
if (file_exists($mythus_autoloader)) {
    require_once $mythus_autoloader;
}

// Set up test WordPress directory inside tests/.wp-test/
$wp_test_dir = __DIR__ . '/.wp-test';
if (!file_exists($wp_test_dir)) {
    mkdir($wp_test_dir, 0755, true);
}

// Define ABSPATH before loading WorDBless to override its default.
// Must be defined before WorDBless\Load::load() is called.
define('ABSPATH', $wp_test_dir . '/');

// Create wp-content directory structure for WorDBless.
$wp_content_dir = ABSPATH . 'wp-content';
if (!file_exists($wp_content_dir)) {
    mkdir($wp_content_dir, 0755, true);
}
if (!file_exists($wp_content_dir . '/themes')) {
    mkdir($wp_content_dir . '/themes', 0755, true);
}

// Copy WordPress core files from wordpress-no-content.
$wp_source = dirname(__DIR__) . '/vendor/roots/wordpress-no-content';
if (is_dir($wp_source)) {
    // Copy wp-settings.php if not present
    if (!file_exists(ABSPATH . 'wp-settings.php')) {
        copy($wp_source . '/wp-settings.php', ABSPATH . 'wp-settings.php');
    }
    // Copy wp-includes if not present
    if (!file_exists(ABSPATH . 'wp-includes') && is_dir($wp_source . '/wp-includes')) {
        symlink($wp_source . '/wp-includes', ABSPATH . 'wp-includes');
    }
    // Copy wp-admin if not present
    if (!file_exists(ABSPATH . 'wp-admin') && is_dir($wp_source . '/wp-admin')) {
        symlink($wp_source . '/wp-admin', ABSPATH . 'wp-admin');
    }
}

// Copy WorDBless database mock.
copy(
    dirname(__DIR__) . '/vendor/automattic/wordbless/src/dbless-wpdb.php',
    $wp_content_dir . '/db.php'
);

// Symlink child theme for WordPress to find it.
$theme_name = basename(dirname(__DIR__));
$theme_src = dirname(__DIR__);
$theme_dest = $wp_content_dir . '/themes/' . $theme_name;
if (is_dir($theme_src) && !file_exists($theme_dest)) {
    symlink($theme_src, $theme_dest);
}

// Symlink parent theme as well.
$parent_theme_name = 'ix';
$parent_theme_src = dirname(dirname(__DIR__)) . '/' . $parent_theme_name;
$parent_theme_dest = $wp_content_dir . '/themes/' . $parent_theme_name;
if (is_dir($parent_theme_src) && !file_exists($parent_theme_dest)) {
    symlink($parent_theme_src, $parent_theme_dest);
}

// Load WordPress via WorDBless.
Load::load();

// Switch to our theme.
switch_theme($theme_name);

// Load ACF function stubs (get_field, update_field) for tests that need them.
require_once __DIR__ . '/Support/acf-stubs.php';
