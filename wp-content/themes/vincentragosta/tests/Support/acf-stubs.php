<?php

/**
 * ACF function stubs for the test environment.
 *
 * In WorDBless, ACF isn't loaded, so get_field/update_field don't exist.
 * These stubs delegate to WordPress post meta functions which WorDBless supports.
 *
 * Loaded once from bootstrap.php — no redeclaration risk.
 */

if (!function_exists('get_field')) {
    function get_field(string $key, $postId = 0) {
        if (!is_numeric($postId)) {
            return '';
        }
        return get_post_meta((int) $postId, $key, true);
    }
}

if (!function_exists('update_field')) {
    function update_field(string $key, $value, $postId = 0) {
        if (!is_numeric($postId)) {
            return false;
        }
        return update_post_meta((int) $postId, $key, $value);
    }
}
