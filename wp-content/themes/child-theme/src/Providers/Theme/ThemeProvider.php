<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme;

use ChildTheme\Providers\Project\ProjectPost;
use ChildTheme\Providers\Theme\Features\AccordionIconEnhancer;
use ChildTheme\Providers\Theme\Features\ButtonIconEnhancer;
use ChildTheme\Providers\Theme\Features\CoverBlockStyles;
use DI\Container;
use ParentTheme\Providers\Theme\ThemeProvider as BaseThemeProvider;
use ParentTheme\Services\IconServiceFactory;

/**
 * Handles core theme setup and configuration.
 *
 * Extends the parent theme's Theme Provider to add site-specific functionality.
 * Includes shutter-cards blocks for interactive card components, button icon
 * enhancements, and cover block styles.
 */
class ThemeProvider extends BaseThemeProvider
{
    /**
     * Features to register.
     *
     * Parent features are inherited automatically via collectFeatures().
     * Add only child-specific features here. Use `ClassName::class => false`
     * to opt out of an inherited parent feature.
     */
    protected array $features = [
        AccordionIconEnhancer::class,
        ButtonIconEnhancer::class,
        CoverBlockStyles::class,
    ];

    /**
     * Blocks to register.
     */
    protected array $blocks = [
        'shutter-cards',
        'shutter-card',
    ];

    /**
     * Create the child theme provider with its dependencies.
     *
     * @param Container $container DI container for feature resolution.
     * @param IconServiceFactory $iconFactory Factory for creating IconService instances.
     */
    public function __construct(
        Container $container,
        IconServiceFactory $iconFactory,
    ) {
        parent::__construct($container, $iconFactory);
    }

    /**
     * Extend parent theme supports with child editor styles and nav menus.
     */
    public function addThemeSupports(): void
    {
        parent::addThemeSupports();

        register_nav_menus([
            'primary' => __('Primary Navigation', 'child-theme'),
            'footer'  => __('Footer Navigation', 'child-theme'),
        ]);

        add_editor_style('dist/css/theme.css');
    }

    /**
     * Register the Timber post class map with project mapping.
     *
     * @param array<string, class-string> $classMap Existing class map.
     * @return array<string, class-string|callable> Updated class map.
     */
    public function registerClassMap(array $classMap): array
    {
        $classMap = parent::registerClassMap($classMap);
        $classMap['project'] = ProjectPost::class;

        return $classMap;
    }

    /**
     * Register child-specific hooks before delegating to the parent.
     *
     * Adds site-specific asset enqueueing, resource hints, and block editor
     * data localization, then calls parent::register() for theme supports,
     * features, and blocks.
     */
    public function register(): void
    {
        // Add site-specific hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('wp_resource_hints', [$this, 'addResourceHints'], 10, 2);

        // Block editor assets and data localization
        add_action('enqueue_block_editor_assets', [$this, 'enqueueButtonEditorAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'localizeEditorData'], 99);

        // Call parent to register theme supports, features, and blocks
        parent::register();
    }

    /**
     * Enqueue frontend assets for this theme.
     */
    public function enqueueAssets(): void
    {
        // Google Fonts
        wp_enqueue_style(
            'fira-code-font',
            'https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600;700&display=swap',
            [],
            null
        );

        $this->enqueueStyle('child-theme-theme', 'theme.css');
    }

    /**
     * Add preconnect hints for Google Fonts using wp_resource_hints filter.
     *
     * @param array<int, string|array<string, string>> $urls URLs to add hints for.
     * @param string $relation_type The relation type (dns-prefetch, preconnect, etc.).
     * @return array<int, string|array<string, string>>
     */
    public function addResourceHints(array $urls, string $relation_type): array
    {
        if ($relation_type === 'preconnect') {
            $urls[] = [
                'href' => 'https://fonts.googleapis.com',
            ];
            $urls[] = [
                'href' => 'https://fonts.gstatic.com',
                'crossorigin' => 'anonymous',
            ];
        }

        return $urls;
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        parent::enqueueBlockAssets();

        $this->enqueueStyle('child-theme-shutter-cards-block', 'shutter-cards.css');
        $this->enqueueStyle('child-theme-shutter-card-block', 'shutter-card.css');

        // Editor-only styles (enqueued here for iframe compatibility)
        if (is_admin()) {
            $this->enqueueStyle('child-theme-shutter-cards-block-editor', 'shutter-cards-editor.css');
        }
    }

    /**
     * Enqueue block editor assets.
     */
    public function enqueueBlockEditorAssets(): void
    {
        $this->enqueueEditorScript('child-theme-shutter-cards-block-editor', 'shutter-cards.js');
        $this->enqueueEditorScript('child-theme-shutter-card-block-editor', 'shutter-card.js');
    }

    /**
     * Enqueue button icon picker editor assets.
     */
    public function enqueueButtonEditorAssets(): void
    {
        $this->enqueueScript('child-theme-block-service-js', 'button.js', [
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-compose',
            'wp-hooks',
        ]);
    }

    /**
     * Localize data for the block editor.
     */
    public function localizeEditorData(): void
    {
        $this->localizeButtonIconData();
    }

    /**
     * Localize icon data for the button icon picker.
     *
     * Uses wp_add_inline_script with wp_json_encode for reliable data serialization,
     * which handles special characters better than wp_localize_script.
     * Skips localization if the button script hasn't been registered.
     */
    private function localizeButtonIconData(): void
    {
        $handle = 'child-theme-block-service-js';
        if (!$this->isScriptActive($handle)) {
            return;
        }

        $data = [
            'iconOptions' => $this->iconFactory->options('sprite', __('— No Icon —', 'child-theme')),
            'iconContentMap' => $this->iconFactory->contentMap('sprite'),
        ];

        wp_add_inline_script(
            $handle,
            'var childThemeButtonIconData = ' . wp_json_encode($data) . ';',
            'before'
        );
    }

    /**
     * Check if a script is registered or enqueued.
     */
    private function isScriptActive(string $handle): bool
    {
        return wp_script_is($handle, 'registered') || wp_script_is($handle, 'enqueued');
    }
}
