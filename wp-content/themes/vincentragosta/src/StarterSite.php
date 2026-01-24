<?php

use Timber\Site;
use Timber\Timber;
use Twig\TwigFunction;

require_once __DIR__ . '/Icon.php';

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

        add_action('enqueue_block_assets', array($this, 'enqueue_block_styles'));

        // Register custom post types from the /config directory
        add_action('init', array($this, 'register_custom_post_types'));

        add_filter('show_admin_bar', '__return_false');

        // Register custom blocks (like Hero and Projects)
        add_action('init', array($this, 'register_native_blocks'));

        // Localize data for the block editor
        add_action('enqueue_block_editor_assets', array($this, 'localize_block_editor_data'), 99);

        // Add custom functions and filters to Twig
        add_filter('timber/twig', array($this, 'additional_timber_functions'));

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
        add_theme_support('menus');
        add_theme_support('html5', ['comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
        add_theme_support('editor-styles');
        add_theme_support('wp-block-styles');
        add_theme_support('layout');
        add_theme_support('custom-spacing');
        add_theme_support('align-wide');
        add_editor_style('style.css');
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

        wp_enqueue_style(
            'vincentragosta-style',
            get_stylesheet_uri(),
            array(),
            wp_get_theme()->get('Version')
        );

        // Enqueue frontend JavaScript (separate from editor scripts)
        $script_path = get_template_directory() . '/assets/src/build/js/frontend.js';
        if (file_exists($script_path)) {
            wp_enqueue_script(
                'vincentragosta-frontend-js',
                get_template_directory_uri() . '/assets/src/build/js/frontend.js',
                array(),
                wp_get_theme()->get('Version'),
                true
            );
        }
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
        // Enqueue the master block script
        $blocks_script_asset_path = get_template_directory() . '/blocks/build/index.asset.php';
        if (file_exists($blocks_script_asset_path)) {
            $blocks_script_asset = require($blocks_script_asset_path);
            wp_enqueue_script(
                'vincentragosta-blocks-js', // A new handle for our blocks script
                get_template_directory_uri() . '/blocks/build/index.js',
                $blocks_script_asset['dependencies'],
                $blocks_script_asset['version'],
                true
            );
        }

        $script_asset_path = get_template_directory() . '/assets/src/build/js/main.asset.php';
        if (file_exists($script_asset_path)) {
            $script_asset = require($script_asset_path);
            $dependencies = $script_asset['dependencies'];
            if (!in_array('wp-element', $dependencies)) {
                $dependencies[] = 'wp-element';
            }

            wp_enqueue_script(
                'vincentragosta-js',
                get_template_directory_uri() . '/assets/src/build/js/main.js',
                $dependencies,
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
     * Adds custom functions and filters to Twig.
     *
     * @param \Twig\Environment $twig The Twig environment.
     * @return \Twig\Environment
     */
    public function additional_timber_functions($twig)
    {
        $twig->addFunction(new TwigFunction('icon', function (string $name): Icon {
            return new Icon($name);
        }));

        return $twig;
    }

    /**
     * Registers custom post types from JSON files in the /config directory.
     */
    public function register_custom_post_types()
    {
        $config_dir = get_template_directory() . '/config/';
        if (!is_dir($config_dir)) {
            return;
        }
        $json_files = glob($config_dir . '/*.json');
        foreach ($json_files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['post_type']) && isset($data['args'])) {
                register_post_type($data['post_type'], $data['args']);
            }
        }
    }

    public function register_native_blocks()
    {
        $blocks = ['hero', 'projects', 'shutter-cards', 'shutter-card'];
        foreach ($blocks as $block_name) {
            $block_directory = get_template_directory() . '/blocks/' . $block_name;
            if (file_exists($block_directory . '/block.json')) {
                register_block_type($block_directory);
            }
        }
    }

    /**
     * Localizes data for the block editor.
     */
    public function localize_block_editor_data()
    {
        // Hero block SVG data
        $master_block_script_handle = 'vincentragosta-blocks-js';
        if (wp_script_is($master_block_script_handle, 'registered') || wp_script_is($master_block_script_handle, 'enqueued')) {
            wp_localize_script($master_block_script_handle, 'vincentragostaHeroBlockData', [
                'svgOptions' => Icon::options('svg', __('Select SVG for Hero', 'vincentragosta')),
                'svgContent' => Icon::contentMap('svg'),
            ]);
        }

        // Button icon data
        $main_editor_script_handle = 'vincentragosta-js';
        if (wp_script_is($main_editor_script_handle, 'registered') || wp_script_is($main_editor_script_handle, 'enqueued')) {
            wp_localize_script($main_editor_script_handle, 'vincentragostaButtonIconData', [
                'iconOptions' => Icon::options('sprite', __('— No Icon —', 'vincentragosta')),
                'iconContentMap' => Icon::contentMap('sprite'),
            ]);
        }
    }

    /**
     * Filters the rendering of the core/button block to add an icon on the frontend.
     */
    public function render_button_with_icon_frontend($block_content, $block)
    {
        if (!isset($block['blockName']) || $block['blockName'] !== 'core/button' || empty($block['attrs']['selectedIcon'])) {
            return $block_content;
        }

        $icon = new Icon($block['attrs']['selectedIcon']);
        if (!$icon->exists()) {
            return $block_content;
        }

        $svg_content = (string) $icon;
        $icon_position = $block['attrs']['iconPosition'] ?? 'left';
        $class_to_add = ' has-icon icon-pos-' . esc_attr($icon_position);

        // Add class to wrapper div
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

        // Insert icon into button/link
        $pattern = '/(<(a|button)\s+[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>)(.*?)(<\/\2>)/is';
        $block_content = preg_replace_callback(
            $pattern,
            function ($matches) use ($svg_content, $icon_position) {
                $opening_tag = $matches[1];
                $link_text = $matches[3];
                $closing_tag = $matches[4];
                $icon_html = '<span class="wp-block-button__icon" aria-hidden="true">' . $svg_content . '</span>';

                return $icon_position === 'right'
                    ? $opening_tag . $link_text . $icon_html . $closing_tag
                    : $opening_tag . $icon_html . $link_text . $closing_tag;
            },
            $block_content,
            1
        );

        return $block_content;
    }

    public function enqueue_block_styles() {
        // Enqueue the main block styles (for both editor and frontend)
        $blocks_style_path = get_template_directory() . '/blocks/build/style-index.css';
        if (file_exists($blocks_style_path)) {
            wp_enqueue_style(
                'vincentragosta-blocks-style',
                get_template_directory_uri() . '/blocks/build/style-index.css',
                [],
                filemtime($blocks_style_path)
            );
        }

        // Enqueue editor-only styles
        if (is_admin()) {
            $blocks_editor_style_path = get_template_directory() . '/blocks/build/index.css';
            if (file_exists($blocks_editor_style_path)) {
                wp_enqueue_style(
                    'vincentragosta-blocks-editor-style',
                    get_template_directory_uri() . '/blocks/build/index.css',
                    ['wp-edit-blocks', 'vincentragosta-blocks-style'], // Depends on both core and our main styles
                    filemtime($blocks_editor_style_path)
                );
            }
        }
    }
}