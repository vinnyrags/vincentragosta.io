<?php

use Timber\Site;
use Timber\Timber;
use Twig\TwigFunction; // Ensure this use statement is present

/**
 * Class StarterSite
 */
class StarterSite extends Site
{
    public function __construct()
    {
        // Configure Timber directories
        Timber::$dirname = ['templates', 'views', 'blocks'];

        // Core theme supports and initial setup
        add_action('after_setup_theme', array($this, 'theme_supports'));

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_theme_assets'));

        // Add preconnects to wp_head
        add_action('wp_head', array($this, 'add_font_preconnects'));

        // Enqueue custom editor scripts (main.js for formats, button icon enhancement, etc.)
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_custom_editor_scripts'));

        // Register custom blocks (like Hero)
        add_action('init', array($this, 'register_native_blocks'));

        // Localize data for the block editor (SVGs for Hero block, button icons for main.js)
        // Priority 99 to ensure it runs after other scripts might be enqueued/registered.
        add_action('enqueue_block_editor_assets', array($this, 'localize_block_editor_data'), 99);

        // Add the global get_theme_svg function (from functions.php) to Twig
        add_filter('timber/twig', array($this, 'add_global_svg_function_to_twig'));

        // Filter core/button block rendering to add icons on the frontend
        add_filter('render_block_core/button', array($this, 'render_button_with_icon_frontend'), 10, 2);

        parent::__construct();
    }

    /**
     * Setup theme supports.
     */
    public function theme_supports()
    {
        add_theme_support('automatic-feed-links');
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support(
            'html5',
            array(
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
                'style',
                'script',
            )
        );
        add_theme_support(
            'post-formats',
            array(
                'aside',
                'image',
                'video',
                'quote',
                'link',
                'gallery',
                'audio',
            )
        );
        add_theme_support('menus');
        add_theme_support('editor-styles');
        add_editor_style('style.css');
        add_theme_support('block-templates');
        add_theme_support('align-wide');
        add_theme_support('responsive-embeds');
        add_theme_support('custom-line-height');
        add_theme_support('experimental-link-color');
        add_theme_support('custom-units');
    }

    /**
     * Enqueue theme's frontend scripts and styles.
     */
    public function enqueue_theme_assets()
    {
        wp_enqueue_style(
            'fira-code-font',
            'https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600;700&display=swap',
            array(),
            null
        );
    }

    /**
     * Add preconnect links for fonts to wp_head.
     */
    public function add_font_preconnects()
    {
        ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <?php
    }

    /**
     * Enqueues custom formats and other JS enhancements for the RichText editor.
     */
    public function enqueue_custom_editor_scripts()
    {
        $script_asset_path = get_template_directory() . '/assets/src/build/js/main.asset.php';
        if (file_exists($script_asset_path)) {
            $script_asset = require($script_asset_path);
            wp_enqueue_script(
                'vincentragosta-js',
                get_template_directory_uri() . '/assets/src/build/js/main.js',
                $script_asset['dependencies'],
                $script_asset['version'],
                true
            );

            wp_set_script_translations(
                'vincentragosta-js',
                'vincentragosta',
                get_template_directory() . '/languages'
            );
        } else {
            error_log('Custom editor asset file (main.asset.php) not found: ' . $script_asset_path);
        }
    }

    /**
     * Adds the global get_theme_svg function to Twig.
     * @param \Twig\Environment $twig The Twig environment.
     * @return \Twig\Environment
     */
    public function add_global_svg_function_to_twig($twig)
    {
        if (function_exists('get_theme_svg')) {
            $twig->addFunction(new TwigFunction('get_theme_svg', 'get_theme_svg'));
        } else {
            error_log('StarterSite Error: Global function get_theme_svg() not found and cannot be added to Twig.');
        }
        return $twig;
    }

    /**
     * Registers custom Gutenberg blocks defined in the /blocks directory.
     */
    public function register_native_blocks()
    {
        $blocks = ['hero'];
        foreach ($blocks as $block_name) {
            $block_directory = get_template_directory() . '/blocks/' . $block_name;
            if (file_exists($block_directory . '/block.json')) {
                register_block_type($block_directory);
            } else {
                error_log("Block configuration file (block.json) not found for block: " . $block_name . " at " . $block_directory);
            }
        }
    }

    /**
     * Localizes data for the block editor.
     */
    public function localize_block_editor_data()
    {
        // Hero Block Specific Localization
        $hero_block_asset_path = get_template_directory() . '/blocks/hero/build/index.asset.php';
        if ( file_exists( $hero_block_asset_path ) ) {
            $hero_script_handle = 'vincentragosta-hero-editor-script';
            if (wp_script_is($hero_script_handle, 'registered') || wp_script_is($hero_script_handle, 'enqueued')) {
                $svg_dir_hero = get_template_directory() . '/assets/images/svg/';
                $svg_options_hero = [['label' => __('Select SVG for Hero', 'vincentragosta'), 'value' => '']];
                $svg_content_map_hero = [];
                if (is_dir($svg_dir_hero) && function_exists('get_theme_svg')) {
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
        $main_editor_script_handle = 'vincentragosta-js';
        if (wp_script_is($main_editor_script_handle, 'registered') || wp_script_is($main_editor_script_handle, 'enqueued')) {
            $sprite_svg_dir = get_template_directory() . '/assets/images/svg-sprite/';
            $button_icon_options = [['label' => __('— No Icon —', 'vincentragosta'), 'value' => '']];
            $button_icon_content_map = [];

            if (is_dir($sprite_svg_dir) && function_exists('get_theme_svg')) {
                $sprite_svg_files = glob($sprite_svg_dir . '*.svg');
                if ($sprite_svg_files) {
                    foreach ($sprite_svg_files as $file_path) {
                        $filename = basename($file_path);
                        $label = ucwords(str_replace(['icon-','-', '_', '.svg'], ['',' ', ' ', ''], pathinfo($filename, PATHINFO_FILENAME)));
                        $button_icon_options[] = ['label' => $label, 'value' => $filename];
                        $button_icon_content_map[$filename] = get_theme_svg($filename, true);
                    }
                } else {
                    error_log('No SVG files found in svg-sprite directory: ' . $sprite_svg_dir);
                }
            } else {
                error_log('SVG sprite directory not found: ' . $sprite_svg_dir . ' or get_theme_svg function unavailable.');
            }
            $button_icon_localized_data = [
                'iconOptions'    => $button_icon_options,
                'iconContentMap' => $button_icon_content_map,
            ];
            wp_localize_script($main_editor_script_handle, 'vincentragostaButtonIconData', $button_icon_localized_data);
        } else {
            error_log('Main editor script ('.$main_editor_script_handle.') not registered/enqueued. Localization for button icons skipped.');
        }
    }

    /**
     * Filters the rendering of the core/button block to add an icon on the frontend.
     */
    public function render_button_with_icon_frontend($block_content, $block)
    {
        if (
            isset($block['blockName']) && $block['blockName'] === 'core/button' &&
            !empty($block['attrs']['selectedIcon']) &&
            function_exists('get_theme_svg')
        ) {
            $icon_filename = $block['attrs']['selectedIcon'];
            $svg_content = get_theme_svg($icon_filename, true);

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
}