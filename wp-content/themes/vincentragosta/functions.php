<?php
/**
 * Timber starter-theme
 * https://github.com/timber/starter-theme
 */

// Load Composer dependencies.
require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/src/StarterSite.php';

Timber\Timber::init();

// Sets the directories (inside your theme) to find .twig files.
Timber::$dirname = ['templates', 'views', 'blocks'];

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

    if (pathinfo($svg_path, PATHINFO_EXTENSION) === 'svg' && file_exists($svg_path) && is_readable($svg_path)) {
        $svg_content = file_get_contents($svg_path);
        if ($svg_content === false || !is_string($svg_content)) {
            error_log('get_theme_svg: Failed to read file content or content is not string for: ' . $svg_path);
            return '';
        }
        $sanitized_content = trim($svg_content);
        $sanitized_content = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $sanitized_content);
        $sanitized_content = preg_replace('/\s(on\w+)=("|\').*?\2/is', '', $sanitized_content);
        $sanitized_content = preg_replace('/^\s*<\?xml.*?\?>\s*/s', '', $sanitized_content);
        $sanitized_content = preg_replace('/^<!DOCTYPE.+?>/is', '', $sanitized_content);

        if (stripos($sanitized_content, '<svg') === false) {
            error_log("get_theme_svg: Sanitization removed the main <svg> tag for: " . $filename . " from path " . $svg_path);
            return '';
        }
        return $sanitized_content ?: '';
    }
    return '';
}

add_filter('timber/twig', function ($twig) {
    $twig->addFunction(new Twig\TwigFunction('get_theme_svg', 'get_theme_svg'));
    return $twig;
});

function vincentragosta_register_native_blocks()
{
    $blocks = ['hero'];
    foreach ($blocks as $block_name) {
        $block_directory = get_template_directory() . '/blocks/' . $block_name;
        if (file_exists($block_directory . '/block.json')) {
            register_block_type($block_directory);
        } else {
            error_log("Block configuration file not found for block: " . $block_name . " at " . $block_directory . "/block.json");
        }
    }
}
add_action('init', 'vincentragosta_register_native_blocks');

function vincentragosta_localize_block_editor_data() {

    // Hero Block Specific Localization
    $hero_block_asset_path = get_template_directory() . '/blocks/hero/build/index.asset.php';
    if ( file_exists( $hero_block_asset_path ) ) {
        $hero_script_handle = 'vincentragosta-hero-editor-script';
        if (wp_script_is($hero_script_handle, 'registered') || wp_script_is($hero_script_handle, 'enqueued')) {
            $svg_dir_hero = get_template_directory() . '/assets/images/';
            $svg_options_hero = [['label' => __('Select SVG for Hero', 'vincentragosta'), 'value' => '']];
            $svg_content_map_hero = [];
            if (is_dir($svg_dir_hero)) {
                $svg_files_hero = glob($svg_dir_hero . '*.svg');
                if ($svg_files_hero) {
                    foreach ($svg_files_hero as $file_path) {
                        $filename = basename($file_path);
                        $label = ucwords(str_replace(['-', '_', '.svg'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
                        $svg_options_hero[] = ['label' => $label, 'value' => $filename];
                        $svg_content_map_hero[$filename] = get_theme_svg($filename);
                    }
                }
            }
            $hero_localized_data = ['svgOptions' => $svg_options_hero, 'svgContent' => $svg_content_map_hero];
            wp_localize_script($hero_script_handle, 'vincentragostaHeroBlockData', $hero_localized_data);
        } else {
            error_log('Hero block script (' . $hero_script_handle . ') not registered/enqueued for localization.');
        }
    } else {
        error_log('Hero block asset file for localization not found: ' . $hero_block_asset_path);
    }

    // Button Icon Enhancement Data Localization
    $main_editor_script_handle = 'vincentragosta-js'; // As enqueued by StarterSite.php
    if (wp_script_is($main_editor_script_handle, 'registered') || wp_script_is($main_editor_script_handle, 'enqueued')) {
        $sprite_svg_dir = get_template_directory() . '/assets/images/svg-sprite/';
        // Ensure "No Icon" is the first option
        $button_icon_options = [['label' => __('— No Icon —', 'vincentragosta'), 'value' => '']];
        $button_icon_content_map = [];

        if (is_dir($sprite_svg_dir)) {
            $sprite_svg_files = glob($sprite_svg_dir . '*.svg');
            if ($sprite_svg_files) {
                foreach ($sprite_svg_files as $file_path) {
                    $filename = basename($file_path);
                    $label = ucwords(str_replace(['icon-','-', '_', '.svg'], ['',' ', ' ', ''], pathinfo($filename, PATHINFO_FILENAME)));
                    $button_icon_options[] = ['label' => $label, 'value' => $filename];
                    $button_icon_content_map[$filename] = get_theme_svg($filename, true); // Use true for sprite
                }
            } else {
                error_log('No SVG files found in svg-sprite directory: ' . $sprite_svg_dir);
            }
        } else {
            error_log('SVG sprite directory not found: ' . $sprite_svg_dir);
        }
        $button_icon_localized_data = [
            'iconOptions'   => $button_icon_options,
            'iconContentMap'=> $button_icon_content_map,
        ];
        wp_localize_script($main_editor_script_handle, 'vincentragostaButtonIconData', $button_icon_localized_data);
    } else {
        error_log('Main editor script ('.$main_editor_script_handle.') not registered/enqueued. Localization for button icons skipped.');
    }
}
add_action( 'enqueue_block_editor_assets', 'vincentragosta_localize_block_editor_data', 99 );

function vincentragosta_sanitize_any_svg_string($svg_string) {
    if (empty(trim($svg_string))) {
        return '';
    }
    $allowed_svg_tags = array(
        'svg' => array('width' => true, 'height' => true, 'viewbox' => true, 'xmlns' => true, 'fill' => true, 'class' => true, 'style' => true, 'aria-hidden' => true, 'role' => true, 'focusable' => true, 'preserveaspectratio' => true),
        'path' => array('d' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'class' => true, 'style' => true, 'transform' => true, 'fill-rule' => true, 'clip-rule' => true),
        'circle' => array('cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
        'rect' => array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
        'ellipse' => array('cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
        'line' => array('x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
        'polygon' => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
        'polyline' => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
        'g' => array('fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true, 'id' => true),
        'defs' => array(), 'symbol' => array('id' => true, 'viewbox' => true, 'preserveaspectratio' => true, 'overflow' => true),
        'use' => array('xlink:href' => true, 'href' => true, 'class' => true, 'transform' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true),
        'style' => array('type' => true), 'title' => array(), 'desc' => array(),
    );
    $sanitized_svg = wp_kses($svg_string, $allowed_svg_tags);
    if (empty(trim($sanitized_svg)) || stripos($sanitized_svg, '<svg') === false) {
        return '';
    }
    return $sanitized_svg;
}

function vincentragosta_render_button_with_icon_frontend($block_content, $block) {
    if (
        isset($block['blockName']) && $block['blockName'] === 'core/button' &&
        !empty($block['attrs']['selectedIcon']) // Only check selectedIcon; hasIcon is removed
    ) {
        $icon_filename = $block['attrs']['selectedIcon'];
        $svg_content = get_theme_svg($icon_filename, true); // Use true for svg-sprite directory

        if (empty($svg_content)) {
            return $block_content;
        }

        $icon_position = isset($block['attrs']['iconPosition']) ? $block['attrs']['iconPosition'] : 'left';

        $class_to_add = ' has-icon icon-pos-' . esc_attr($icon_position);
        if (strpos($block_content, 'class="') !== false) {
            $block_content = preg_replace(
                '/(<div\s+[^>]*class=")([^"]*wp-block-button[^"]*)/i',
                '$1$2' . $class_to_add . '"',
                $block_content,
                1
            );
        } else {
            $block_content = preg_replace(
                '/(<div\s+[^>]*wp-block-button)/i',
                '$1 class="' . trim($class_to_add) . '"',
                $block_content,
                1
            );
        }

        $pattern = '/(<(a|button)\s+[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>)(.*?)(<\/\2>)/is';
        $block_content = preg_replace_callback(
            $pattern,
            function ($matches) use ($svg_content, $icon_position) {
                $opening_tag = $matches[1];
                $link_text   = $matches[3];
                $closing_tag = $matches[4];
                $icon_html = '<span class="wp-block-button__icon" aria-hidden="true">' . $svg_content . '</span>';
                if ($icon_position === 'right') {
                    return $opening_tag . $link_text . $icon_html . $closing_tag;
                } else {
                    return $opening_tag . $icon_html . $link_text . $closing_tag;
                }
            },
            $block_content,
            1
        );
    }
    return $block_content;
}
add_filter('render_block_core/button', 'vincentragosta_render_button_with_icon_frontend', 10, 2);