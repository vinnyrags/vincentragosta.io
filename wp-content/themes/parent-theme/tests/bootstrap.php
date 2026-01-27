<?php
/**
 * PHPUnit bootstrap file.
 *
 * Sets up the WordPress test environment using WorDBless.
 */

use WorDBless\Load;

// Load Composer autoloader.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define ABSPATH to point to wordpress-no-content before loading WorDBless.
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/vendor/roots/wordpress-no-content/');
}

// Create wp-content directory structure for WorDBless.
$wp_content_dir = ABSPATH . 'wp-content';
if (!file_exists($wp_content_dir)) {
    mkdir($wp_content_dir, 0755, true);
}
if (!file_exists($wp_content_dir . '/themes')) {
    mkdir($wp_content_dir . '/themes', 0755, true);
}

// Copy WorDBless database mock.
copy(
    dirname(__DIR__) . '/vendor/automattic/wordbless/src/dbless-wpdb.php',
    $wp_content_dir . '/db.php'
);

// Symlink parent theme for WordPress to find it.
$theme_name = basename(dirname(__DIR__));
$theme_src = dirname(__DIR__);
$theme_dest = $wp_content_dir . '/themes/' . $theme_name;
if (is_dir($theme_src) && !file_exists($theme_dest)) {
    symlink($theme_src, $theme_dest);
}

// Load WordPress via WorDBless.
Load::load();

// Switch to our theme.
switch_theme($theme_name);
