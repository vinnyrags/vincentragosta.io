<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Theme;

use DI\Container;
use ParentTheme\Models\Image;
use ParentTheme\Models\Post;
use ParentTheme\Providers\Provider;
use ParentTheme\Providers\Theme\Features\DisableBlocks;
use ParentTheme\Providers\Theme\Features\DisableComments;
use ParentTheme\Providers\Theme\Features\DisableDefaultPatterns;
use ParentTheme\Providers\Theme\Features\DisablePosts;
use ParentTheme\Providers\Theme\Features\EnableSvgUploads;
use ParentTheme\Providers\Theme\Features\ScrollReveal;
use ParentTheme\Providers\Theme\Features\WpFormsBlockDetection;
use ParentTheme\Providers\Theme\Features\WpFormsFloatingLabels;
use ParentTheme\Providers\Theme\Hooks\AccordionIconEnhancer;
use ParentTheme\Providers\Theme\Hooks\ButtonIconEnhancer;
use ParentTheme\Providers\Theme\Hooks\FeaturedImageFocalPoint;
use ParentTheme\Providers\Theme\Hooks\TermsQuerySupports;
use ParentTheme\Services\IconServiceFactory;
use Timber\Attachment;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * Handles core theme setup, configuration, and asset enqueueing.
 *
 * Registers standard WordPress theme supports and enqueues all
 * frontend and editor assets. Can be extended by child themes
 * for additional functionality.
 */
class ThemeProvider extends Provider
{
    /**
     * Theme handle prefix for asset registration.
     *
     * @var string
     */
    protected string $handlePrefix = 'theme';

    /**
     * Hooks to register (always-active structural behavior).
     *
     * @var array<class-string>
     */
    protected array $hooks = [
        AccordionIconEnhancer::class,
        ButtonIconEnhancer::class,
        FeaturedImageFocalPoint::class,
        TermsQuerySupports::class,
    ];

    /**
     * Blocks to register.
     *
     * @var string[]
     */
    protected array $blocks = [
        'testimonials',
    ];

    /**
     * Features to register with this provider.
     *
     * @var array<class-string>
     */
    protected array $features = [
        DisableBlocks::class,
        DisableComments::class,
        DisableDefaultPatterns::class,
        DisablePosts::class,
        EnableSvgUploads::class,
        ScrollReveal::class => false,
        WpFormsBlockDetection::class => false,
        WpFormsFloatingLabels::class => false,
    ];

    /**
     * Create the theme provider with its dependencies.
     *
     * @param Container $container DI container for feature resolution.
     * @param IconServiceFactory $iconFactory Factory for creating IconService instances.
     */
    public function __construct(
        Container $container,
        protected readonly IconServiceFactory $iconFactory,
    ) {
        parent::__construct($container);
    }

    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'addThemeSupports']);
        add_filter('timber/post/classmap', [$this, 'registerClassMap']);

        // Core asset enqueueing
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
        add_action('enqueue_block_assets', [$this, 'enqueueBlockAssets']);

        // Button icon editor assets and data localization
        add_action('enqueue_block_editor_assets', [$this, 'enqueueButtonEditorAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'localizeButtonIconData'], 99);

        parent::register();
    }

    /**
     * Register the Timber post class map.
     *
     * Maps core post types to custom model classes. Child themes can
     * override this method to add their own mappings.
     *
     * @param array<string, class-string> $classMap Existing class map.
     * @return array<string, class-string|callable> Updated class map.
     */
    public function registerClassMap(array $classMap): array
    {
        $classMap['post'] = Post::class;
        $classMap['page'] = Post::class;
        $classMap['attachment'] = function ($post) {
            $mimeType = $post->post_mime_type ?? '';

            if (str_starts_with($mimeType, 'image/')) {
                return Image::class;
            }

            return Attachment::class;
        };

        return $classMap;
    }

    /**
     * Register theme supports.
     *
     * Child themes can override this method to add or remove supports.
     */
    public function addThemeSupports(): void
    {
        add_theme_support('automatic-feed-links');
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('menus');
        add_theme_support('html5', [
            'gallery',
            'caption',
            'style',
            'script',
        ]);
        add_theme_support('editor-styles');
        add_theme_support('wp-block-styles');
        add_theme_support('layout');
        add_theme_support('custom-spacing');
        add_theme_support('align-wide');

        add_editor_style('style.css');
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueueFrontendAssets(): void
    {
        // Parent theme's theme.css uses get_template_directory() (parent path),
        // while AssetManager uses get_stylesheet_directory() (active theme path).
        // Keep this as a direct call to preserve the correct base path.
        $parent_style_path = get_template_directory() . '/dist/css/theme.css';
        if (file_exists($parent_style_path)) {
            wp_enqueue_style(
                $this->handlePrefix . '-style',
                get_template_directory_uri() . '/dist/css/theme.css',
                [],
                filemtime($parent_style_path)
            );
        }

        $this->enqueueScript($this->handlePrefix . '-frontend-js', 'frontend.js');
    }

    /**
     * Enqueue block editor scripts.
     */
    public function enqueueEditorAssets(): void
    {
        $this->enqueueManifestScript($this->handlePrefix . '-blocks-js', 'blocks/index.js');
        $this->enqueueParentEditorScript('parent-theme-testimonials-block-editor', 'js/testimonials.js');
    }

    /**
     * Enqueue block styles for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueDistStyle($this->handlePrefix . '-blocks-style', 'blocks/style-index.css');

        // Testimonials block CSS lives in parent theme dist/
        $this->enqueueParentDistStyle('parent-theme-testimonials-block', 'css/testimonials.css');

        if (is_admin()) {
            $this->enqueueDistStyle(
                $this->handlePrefix . '-blocks-editor-style',
                'blocks/index.css',
                ['wp-edit-blocks', $this->handlePrefix . '-blocks-style']
            );

            $this->enqueueParentDistStyle('parent-theme-testimonials-block-editor', 'css/testimonials-editor.css');

            // Load parent compiled CSS in the editor so shared styles (form
            // resets, layout, etc.) apply. Uses get_template_directory()
            // directly because AssetManager resolves from the child theme.
            $parentDistCss = get_template_directory() . '/dist/css/theme.css';
            if (file_exists($parentDistCss)) {
                wp_enqueue_style(
                    $this->handlePrefix . '-editor-parent-style',
                    get_template_directory_uri() . '/dist/css/theme.css',
                    [],
                    filemtime($parentDistCss)
                );
            }
        }
    }

    /**
     * Add core Twig functions.
     */
    public function addTwigFunctions(Environment $twig): Environment
    {
        $twig = parent::addTwigFunctions($twig);

        $factory = $this->iconFactory;
        $twig->addFunction(new TwigFunction('icon', function (string $name) use ($factory) {
            return $factory->create($name);
        }));

        return $twig;
    }

    /**
     * Enqueue a stylesheet from the parent theme's dist/ directory.
     *
     * Uses get_template_directory() so assets resolve from the parent theme
     * even when a child theme is active (AssetManager resolves from child).
     */
    protected function enqueueParentDistStyle(string $handle, string $path, array $deps = []): void
    {
        $fullPath = get_template_directory() . '/dist/' . $path;

        if (file_exists($fullPath)) {
            wp_enqueue_style(
                $handle,
                get_template_directory_uri() . '/dist/' . $path,
                $deps,
                filemtime($fullPath)
            );
        }
    }

    /**
     * Enqueue a block editor script from the parent theme's dist/ directory.
     *
     * Includes standard WordPress block editor dependencies.
     */
    protected function enqueueParentEditorScript(string $handle, string $path, array $deps = []): void
    {
        $fullPath = get_template_directory() . '/dist/' . $path;

        if (!file_exists($fullPath)) {
            return;
        }

        $defaultDeps = [
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-i18n',
            'wp-data',
        ];

        $allDeps = array_unique([...$defaultDeps, ...$deps]);

        wp_enqueue_script($handle, get_template_directory_uri() . '/dist/' . $path, $allDeps, filemtime($fullPath), true);
    }

    /**
     * Enqueue button icon picker editor assets.
     */
    public function enqueueButtonEditorAssets(): void
    {
        $this->enqueueParentEditorScript('parent-theme-button-icon-js', 'js/theme/button.js');
    }

    /**
     * Localize icon data for the button icon picker.
     *
     * Uses wp_add_inline_script with wp_json_encode for reliable data serialization,
     * which handles special characters better than wp_localize_script.
     * Skips localization if the button script hasn't been registered.
     */
    public function localizeButtonIconData(): void
    {
        $handle = 'parent-theme-button-icon-js';
        if (!wp_script_is($handle, 'registered') && !wp_script_is($handle, 'enqueued')) {
            return;
        }

        $data = [
            'iconOptions' => $this->iconFactory->options('icon', __('— No Icon —', 'parent-theme')),
            'iconContentMap' => $this->iconFactory->contentMap('icon'),
        ];

        wp_add_inline_script(
            $handle,
            'var parentThemeButtonIconData = ' . wp_json_encode($data) . ';',
            'before'
        );
    }
}
