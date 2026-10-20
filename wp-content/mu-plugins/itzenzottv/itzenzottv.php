<?php
/**
 * Plugin Name: itzenzoTTV
 * Description: Headless content backend for itzenzo.tv — card/product post types, card taxonomies, ACF field groups and WPGraphQL extensions.
 * Author: Vincent Ragosta
 * License: MIT
 * Requires PHP: 8.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Classes autoload through the root Composer autoloader (ItzenzoTTV\ → src/),
// which mu-autoloader.php loads. Registered on plugins_loaded so ACF and
// WPGraphQL are present; everything the provider binds fires on init or later.
add_action('plugins_loaded', static function (): void {
    (new DI\Container())->get(ItzenzoTTV\ShopProvider::class)->register();
});
