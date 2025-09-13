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
 * @return string SVG content or an empty string if not found or invalid.
 */
function get_theme_svg($filename)
{
    $filename = basename($filename);
    if (empty($filename)) {
        return '';
    }

    $svg_path = get_template_directory() . '/assets/images/svg/' . $filename;

    if (pathinfo($svg_path, PATHINFO_EXTENSION) !== 'svg') {
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

        // This check is the original issue with sprite files
        if (stripos($sanitized_content, '<svg') === false) {
            error_log("get_theme_svg (functions.php): Sanitization removed the main <svg> tag for: " . $filename . " from path " . $svg_path);
            return '';
        }
        return $sanitized_content ?: '';
    } else {
        return '';
    }
}

/**
 * Safely retrieves the content of an SVG file from the theme's SVG sprite directory.
 * This is designed for SVG icon files without a root <svg> tag.
 *
 * @param string $filename The filename of the SVG (e.g., 'icon-arrow.svg').
 * @return string SVG content or an empty string if not found or invalid.
 */
function get_theme_svg_sprite($filename)
{
    $filename = basename($filename);
    if (empty($filename)) {
        return '';
    }

    $svg_path = get_template_directory() . '/assets/images/svg-sprite/' . $filename;

    if (pathinfo($svg_path, PATHINFO_EXTENSION) !== 'svg') {
        return '';
    }

    if (file_exists($svg_path) && is_readable($svg_path)) {
        $svg_content = file_get_contents($svg_path);
        if ($svg_content === false || !is_string($svg_content)) {
            error_log('get_theme_svg_sprite (functions.php): Failed to read file content or content is not string for: ' . $svg_path);
            return '';
        }
        // Basic sanitization without checking for the root <svg> tag
        $sanitized_content = trim($svg_content);
        $sanitized_content = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $sanitized_content);
        $sanitized_content = preg_replace('/\s(on\w+)=("|\').*?\2/is', '', $sanitized_content);

        return $sanitized_content ?: '';
    } else {
        return '';
    }
}