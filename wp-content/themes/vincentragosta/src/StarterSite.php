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
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));

        add_action('wp_enqueue_scripts', function () {
            // Enqueue Playfair Display (for headings)
            wp_enqueue_style(
                'playfair-display-font',
                'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap',
                array(),
                null
            );

            // Enqueue Libre Baskerville (for body text)
            wp_enqueue_style(
                'libre-baskerville-font',
                'https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap',
                array(),
                null
            );

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

        add_filter('timber/context', array($this, 'add_to_context'));
        add_filter('timber/twig', array($this, 'add_to_twig'));
        add_filter('timber/twig/environment/options', [$this, 'update_twig_environment_options']);

        parent::__construct();
    }

    /**
     * This is where you can register custom post types.
     */
    public function register_post_types()
    {
        // Add custom post types here
    }

    /**
     * This is where you can register custom taxonomies.
     */
    public function register_taxonomies()
    {
        // Add custom taxonomies here
    }

    /**
     * This is where you add some context
     *
     * @param string $context context['this'] Being the Twig's {{ this }}.
     */
    public function add_to_context($context)
    {
        $context['foo'] = 'bar';
        $context['stuff'] = 'I am a value set in your functions.php file';
        $context['notes'] = 'These values are available everytime you call Timber::context();';
        $context['menu'] = Timber::get_menu();
        $context['site'] = $this;

        return $context;
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
        add_theme_support('block-templates'); // Keep this if you use block templates/FSE features
        add_theme_support('align-wide'); // Support wide and full alignments
    }

    /**
     * his would return 'foo bar!'.
     *
     * @param string $text being 'foo', then returned 'foo bar!'.
     */
    public function myfoo($text)
    {
        $text .= ' bar!';
        return $text;
    }

    /**
     * This is where you can add your own functions to twig.
     *
     * @param Twig\Environment $twig get extension.
     */
    public function add_to_twig($twig)
    {
        /**
         * Required when you want to use Twig’s template_from_string.
         * @link https://twig.symfony.com/doc/3.x/functions/template_from_string.html
         */
        // $twig->addExtension( new Twig\Extension\StringLoaderExtension() );

        $twig->addFilter(new \Twig\TwigFilter('myfoo', [$this, 'myfoo']));

        // Optional: Add block_attributes helper if Timber < 2.0 or for custom attributes
        // $twig->addFunction( new \Timber\Twig_Function( 'block_attributes', function( $block ) {
        //     $attributes = '';
        //     if ( ! empty( $block['anchor'] ) ) {
        //         $attributes .= ' id="' . esc_attr( $block['anchor'] ) . '"';
        //     }
        //     $attributes .= ' class="' . esc_attr( implode( ' ', $block['classes'] ) ) . '"';
        //     if ( ! empty( $block['align'] ) ) {
        //         $attributes .= ' data-align="' . esc_attr( $block['align'] ) . '"';
        //     }
        //     // Add any other attributes you want to pass
        //     return $attributes;
        // } ) );

        return $twig;
    }

    /**
     * Updates Twig environment options.
     *
     * @link https://twig.symfony.com/doc/2.x/api.html#environment-options
     *
     * \@param array $options An array of environment options.
     *
     * @return array
     */
    function update_twig_environment_options($options)
    {
        // $options['autoescape'] = true;

        return $options;
    }
}