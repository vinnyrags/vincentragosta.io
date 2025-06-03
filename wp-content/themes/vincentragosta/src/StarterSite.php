<?php

use Timber\Site;
use Timber\Timber; // <-- Add this use statement

/**
 * Class StarterSite
 */
class StarterSite extends Site
{
    public function __construct()
    {
        add_action('after_setup_theme', array($this, 'theme_supports'));
        add_action('wp_enqueue_scripts', function () {
            // Enqueue Fira Code (for code blocks)
            wp_enqueue_style(
                'fira-code-font',
                'https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600;700&display=swap',
                array(),
                null
            );
        });

        add_action('wp_head', function () { ?>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><?php
        });

        add_action('enqueue_block_editor_assets', array($this, 'enqueue_custom_editor_scripts'));

        parent::__construct();
    }

    public function theme_supports()
    {
        // Add default posts and comments RSS feed links to head.
        add_theme_support('automatic-feed-links');

        /*
         * Let WordPress manage the document title.
         * By adding theme support, we declare that this theme does not use a
         * hard-coded <title> tag in the document head, and expect WordPress to
         * provide it for us.
         */
        add_theme_support('title-tag');

        /*
         * Enable support for Post Thumbnails on posts and pages.
         *
         * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
         */
        add_theme_support('post-thumbnails');

        /*
         * Switch default core markup for search form, comment form, and comments
         * to output valid HTML5.
         */
        add_theme_support(
            'html5',
            array(
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
            )
        );

        /*
         * Enable support for Post Formats.
         *
         * See: https://codex.wordpress.org/Post_Formats
         */
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
        add_theme_support('block-templates'); // Keep this if you use block templates/FSE features
        add_theme_support('align-wide'); // Support wide and full alignments
    }

    /**
     * Enqueues custom formats for the RichText editor.
     */
    public function enqueue_custom_editor_scripts()
    {
        $script_asset_path = get_template_directory() . '/assets/src/build/js/main.asset.php';
        if (file_exists($script_asset_path)) {
            $script_asset = require($script_asset_path);
            wp_enqueue_script(
                'vincentragosta-js', // Unique handle for your script
                get_template_directory_uri() . '/assets/src/build/js/main.js',
                $script_asset['dependencies'],
                $script_asset['version'],
                true // Load in footer
            );

            // Enable translations for your script
            wp_set_script_translations(
                'vincentragosta-js', // Script handle
                'vincentragosta', // Text domain
                get_template_directory() . '/languages' // Path to your .po/.mo files if you have them
            );
        } else {
            // Log an error if the asset file is missing, helpful for debugging
            error_log('Custom formats asset file not found: ' . $script_asset_path);
        }
    }
}