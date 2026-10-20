<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop;

use ChildTheme\Providers\Shop\Hooks\CardAttachmentUniqueFilename;
use ChildTheme\Providers\Shop\Hooks\CardGraphQL;
use ChildTheme\Providers\Shop\Hooks\CardImageSize;
use ChildTheme\Providers\Shop\Hooks\PngSubsizesAsJpeg;
use ChildTheme\Providers\Shop\Hooks\ShopRedirect;
use ChildTheme\Providers\Shop\Hooks\ShopSettingsMenuLink;
use IX\Providers\Provider;

/**
 * Shop Provider.
 *
 * Registers the product/card post types, card taxonomies, ACF field
 * groups and the card GraphQL extensions that itzenzo.tv (Next.js)
 * reads. It is a headless content backend only — no checkout path.
 */
class ShopProvider extends Provider
{
    /**
     * Always-active hooks.
     */
    protected array $hooks = [
        CardImageSize::class,
        CardGraphQL::class,
        CardAttachmentUniqueFilename::class,
        PngSubsizesAsJpeg::class,
        ShopSettingsMenuLink::class,
        ShopRedirect::class,
    ];

    /**
     * Register the shop provider.
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);

        // Lift WPGraphQL's default 100-node cap for card connections — the
        // /cards catalog expects the full published set in one payload so
        // the toolbar's client-side search/filter covers everything.
        add_filter('graphql_connection_max_query_amount', static function ($amount, $source, $args, $context, $info) {
            if (isset($info->fieldName) && $info->fieldName === 'cards') {
                return 3000;
            }
            return $amount;
        }, 10, 5);

        parent::register();

        $this->acfManager->registerSavePath();
    }

    /**
     * Register the product and card post types, plus card taxonomies.
     */
    public function registerPostType(): void
    {
        $this->registerPostTypeFromConfig('post-type.json');
        $this->registerPostTypeFromConfig('card-post-type.json');
        $this->registerCardTaxonomies();
    }

    /**
     * Register the card_game and card_set taxonomies for the card CPT.
     */
    private function registerCardTaxonomies(): void
    {
        register_taxonomy('card_game', ['card'], [
            'labels' => [
                'name'          => __('Card Games', 'vincentragosta'),
                'singular_name' => __('Card Game', 'vincentragosta'),
                'menu_name'     => __('Games', 'vincentragosta'),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => false,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'show_in_rest'       => true,
            'show_in_graphql'    => true,
            'graphql_single_name' => 'cardGame',
            'graphql_plural_name' => 'cardGames',
            'rewrite'            => ['slug' => 'card-game'],
        ]);

        register_taxonomy('card_set', ['card'], [
            'labels' => [
                'name'          => __('Card Sets', 'vincentragosta'),
                'singular_name' => __('Card Set', 'vincentragosta'),
                'menu_name'     => __('Sets', 'vincentragosta'),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'show_in_rest'       => true,
            'show_in_graphql'    => true,
            'graphql_single_name' => 'cardSet',
            'graphql_plural_name' => 'cardSets',
            'rewrite'            => ['slug' => 'card-set'],
        ]);
    }

    /**
     * Get the frontend URL for the shop.
     *
     * Returns the ITZENZO_FRONTEND_URL constant if defined,
     * otherwise falls back to the WordPress shop page URL.
     */
    public static function frontendUrl(): string
    {
        if (defined('ITZENZO_FRONTEND_URL')) {
            return rtrim(ITZENZO_FRONTEND_URL, '/');
        }

        return home_url('/shop');
    }
}
