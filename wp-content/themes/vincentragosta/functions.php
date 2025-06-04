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

/**
 * Safely retrieves the content of an SVG file from the theme's assets directory.
 * Includes basic sanitization.
 *
 * @param string $filename  The filename of the SVG (e.g., 'squiggle.svg' or 'icon-arrow.svg').
 * @param bool   $is_sprite Optional. If true, looks in 'assets/images/svg-sprite/'. Defaults to false (looks in 'assets/images/').
 * @return string SVG content or an empty string if not found or invalid.
 */
function get_theme_svg($filename, $is_sprite = false)
{
    $filename = basename($filename);
    if (empty($filename)) {
        return '';
    }

    $subdirectory = $is_sprite ? 'assets/images/svg-sprite/' : 'assets/images/';
    $svg_path = get_template_directory() . '/' . $subdirectory . $filename;

    if (pathinfo($svg_path, PATHINFO_EXTENSION) !== 'svg') {
        // error_log('get_theme_svg (functions.php): Invalid file extension for: ' . $svg_path);
        return '';
    }

    if (file_exists($svg_path) && is_readable($svg_path)) {
        $svg_content = file_get_contents($svg_path);
        if ($svg_content === false || !is_string($svg_content)) {
            error_log('get_theme_svg (functions.php): Failed to read file content or content is not string for: ' . $svg_path);
            return '';
        }
        // Basic sanitization
        $sanitized_content = trim($svg_content);
        $sanitized_content = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $sanitized_content);
        $sanitized_content = preg_replace('/\s(on\w+)=("|\').*?\2/is', '', $sanitized_content);
        $sanitized_content = preg_replace('/^\s*<\?xml.*?\?>\s*/s', '', $sanitized_content);
        $sanitized_content = preg_replace('/^<!DOCTYPE[^>]*?>/is', '', $sanitized_content);

        if (stripos($sanitized_content, '<svg') === false) {
            error_log("get_theme_svg (functions.php): Sanitization removed the main <svg> tag for: " . $filename . " from path " . $svg_path);
            return '';
        }
        return $sanitized_content ?: '';
    } else {
        // error_log('get_theme_svg (functions.php): SVG file not found or not readable: ' . $svg_path);
        return '';
    }
}

// The Twig filter to make get_theme_svg available in Twig templates is now
// managed within the StarterSite class via the add_global_svg_function_to_twig method.
// This keeps hook management centralized in the class.