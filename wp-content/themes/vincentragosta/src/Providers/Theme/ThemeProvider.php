<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme;

use ChildTheme\Providers\Project\ProjectPost;
use ChildTheme\Providers\Shop\CardPost;
use ChildTheme\Providers\Shop\ProductPost;
use IX\Providers\Blog\BlogPost;
use ChildTheme\Providers\Theme\Hooks\AccentHighlight;
use ChildTheme\Providers\Theme\Hooks\ContainerBlockStyles;
use ChildTheme\Providers\Theme\Hooks\SearchSetup;
use ChildTheme\Providers\Theme\Hooks\CoverBlockStyles;
use ChildTheme\Providers\Theme\Hooks\TextBlockStyles;
use ChildTheme\Providers\Theme\Hooks\SocialIconChoices;
use ChildTheme\Providers\Theme\Hooks\SocialIconOverride;
use DI\Container;
use IX\Providers\Theme\Features\ButtonIconEnhancer;
use IX\Providers\Theme\Features\ContentPartial\ContentPartial;
use IX\Providers\Theme\Features\ScrollReveal;
use IX\Providers\Theme\Features\WpFormsBaseStyles;
use IX\Providers\Theme\Features\WpFormsBlockDetection;
use IX\Providers\Theme\Features\WpFormsFloatingLabels;
use IX\Providers\Theme\ThemeProvider as BaseThemeProvider;
use IX\Services\IconServiceFactory;

/**
 * Handles core theme setup and configuration.
 *
 * Extends the parent theme's Theme Provider to add site-specific functionality.
 * Inherits shutter-cards and content-slider blocks from parent. Child's block
 * directories override parent versions via getBlockSearchPaths().
 */
class ThemeProvider extends BaseThemeProvider
{
    /**
     * Features to register (toggleable capabilities).
     *
     * Inherits parent features automatically. Add parent opt-in features here
     * or opt out of parent features via ClassName::class => false.
     */
    protected array $features = [
        // vincentragosta.io builds its header/footer directly in views/*.twig, so it
        // opts out of the platform's default-on content-partial CPT (IX v1.5.0+).
        ContentPartial::class => false,
        ButtonIconEnhancer::class,
        ScrollReveal::class,
        WpFormsBaseStyles::class,
        WpFormsBlockDetection::class,
        WpFormsFloatingLabels::class,
    ];

    /**
     * Hooks to register (always-active structural behavior).
     *
     * Hooks are additive — parent and child hooks are merged automatically.
     * No opt-out syntax. For toggleable capabilities, use $features instead.
     */
    protected array $hooks = [
        AccentHighlight::class,
        ContainerBlockStyles::class,
        CoverBlockStyles::class,
        TextBlockStyles::class,
        SearchSetup::class,
        SocialIconChoices::class,
        SocialIconOverride::class,
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
            'primary' => __('Primary Navigation', 'vincentragosta'),
            'footer'  => __('Footer Navigation', 'vincentragosta'),
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
        $classMap[ProjectPost::POST_TYPE] = ProjectPost::class;
        $classMap[BlogPost::POST_TYPE] = BlogPost::class;
        $classMap[ProductPost::POST_TYPE] = ProductPost::class;
        $classMap[CardPost::POST_TYPE] = CardPost::class;

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

        // Timber context for ACF options data
        add_filter('timber/context', [$this, 'addOptionsToContext']);

        // Timber context for the primary nav menu (with its full child tree),
        // rendered as a grouped mega-menu overlay in views/header.twig.
        add_filter('timber/context', [$this, 'addMenusToContext']);

        // Extend IX's ScrollReveal default selectors with vinrag-specific
        // elements (footer contact + page-bar copy, blog pagination), and
        // add the .blog-pagination ancestor exclude so paginated post-list
        // children don't double-animate inside the pagination container.
        add_filter(ScrollReveal::FILTER_SELECTORS, static function (array $selectors): array {
            return array_merge($selectors, [
                '.footer__contact-heading',
                '.footer__contact-body',
                '.footer__bar',
                '.blog-pagination',
            ]);
        });
        add_filter(ScrollReveal::FILTER_EXCLUDE_ANCESTORS, static function (array $excludes): array {
            return array_merge($excludes, ['.blog-pagination']);
        });

        // Re-enable post featured image block (disabled in parent)
        add_filter('theme/disabled_block_types', static function (array $blocks): array {
            return array_values(array_diff($blocks, ['core/post-featured-image', 'core/post-terms', 'core/post-title', 'core/quote', 'core/pullquote']));
        });

        // Register custom pattern category with branded label (before PatternManager auto-derives it)
        add_action('init', static function (): void {
            register_block_pattern_category('vincentragosta', [
                'label' => __('Vincent Ragosta', 'vincentragosta'),
            ]);
        });

        // Call parent to register theme supports, features, and blocks
        parent::register();

        // Register ACF JSON save path for this provider's acf-json directory
        $this->acfManager->registerSavePath();
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

        $this->enqueueStyle('vincentragosta-theme', 'theme.css');
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
     * Add options page data to the Timber context.
     *
     * @param array<string, mixed> $context Timber context.
     * @return array<string, mixed>
     */
    public function addOptionsToContext(array $context): array
    {
        if (!function_exists('get_field')) {
            return $context;
        }

        $contactPage = get_field('contact_page', 'option');

        $context['options'] = [
            'footer_description' => get_field('footer_description', 'option') ?: '',
            'contact_page_url'   => $contactPage ? get_permalink($contactPage) : '',
            'social_icons'       => get_field('social_icons', 'option') ?: [],
        ];

        return $context;
    }

    /**
     * CSS class (set on a menu item's "CSS Classes" field) that flags it to be
     * populated with the latest posts of a given type — e.g. `nav-dynamic-post`
     * or `nav-dynamic-project`. The suffix must be a registered post type.
     */
    private const DYNAMIC_MENU_CLASS_PREFIX = 'nav-dynamic-';

    /** How many recent posts a dynamic menu item lists. */
    private const DYNAMIC_MENU_COUNT = 5;

    /**
     * Add the primary nav menu (with its full child tree) to the Timber
     * context so header.twig can render a grouped mega-menu. Resolved by
     * theme location; returns null when no menu is assigned to 'primary'.
     *
     * Also resolves "dynamic" menu items (flagged with a `nav-dynamic-{type}`
     * CSS class) into a map of menu-item-ID => latest posts, which header.twig
     * appends beneath the item's static children. This keeps feeds like Nous
     * Signal / Projects always current without manual menu upkeep.
     *
     * @param array<string, mixed> $context Timber context.
     * @return array<string, mixed>
     */
    public function addMenusToContext(array $context): array
    {
        if (!class_exists(\Timber\Timber::class)) {
            return $context;
        }

        $menu = \Timber\Timber::get_menu_by('location', 'primary');

        $context['primary_menu']          = $menu;
        $context['dynamic_menu_children'] = $menu ? $this->resolveDynamicMenuChildren($menu) : [];

        return $context;
    }

    /**
     * Build a map of top-level menu-item ID => latest posts, for any item
     * flagged with a `nav-dynamic-{post_type}` CSS class.
     *
     * @param \Timber\Menu $menu The primary menu.
     * @return array<int, array<int, array{title: string, link: string}>>
     */
    private function resolveDynamicMenuChildren(\Timber\Menu $menu): array
    {
        $map = [];

        foreach ($menu->items as $item) {
            $postType = $this->dynamicPostTypeForMenuItem((int) $item->id);

            if ($postType !== null) {
                $map[(int) $item->id] = $this->latestPostsForMenu($postType);
            }
        }

        return $map;
    }

    /**
     * Read a menu item's assigned CSS classes and return the post type it
     * should be populated with, or null when it isn't a dynamic item.
     */
    private function dynamicPostTypeForMenuItem(int $menuItemId): ?string
    {
        $classes = get_post_meta($menuItemId, '_menu_item_classes', true);

        if (!is_array($classes)) {
            return null;
        }

        foreach ($classes as $class) {
            if (!is_string($class) || !str_starts_with($class, self::DYNAMIC_MENU_CLASS_PREFIX)) {
                continue;
            }

            $postType = substr($class, strlen(self::DYNAMIC_MENU_CLASS_PREFIX));

            return ($postType !== '' && post_type_exists($postType)) ? $postType : null;
        }

        return null;
    }

    /**
     * Fetch the latest published posts of a type as lightweight menu-child
     * data (title + permalink).
     *
     * @return array<int, array{title: string, link: string}>
     */
    private function latestPostsForMenu(string $postType): array
    {
        $posts = get_posts([
            'post_type'        => $postType,
            'post_status'      => 'publish',
            'posts_per_page'   => self::DYNAMIC_MENU_COUNT,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ]);

        return array_map(
            static fn(\WP_Post $post): array => [
                'title' => get_the_title($post),
                'link'  => (string) get_permalink($post),
            ],
            $posts
        );
    }

}
