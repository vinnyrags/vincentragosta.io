<?php

declare(strict_types=1);

namespace ItzenzoTTV;

use ItzenzoTTV\Hooks\CardAttachmentUniqueFilename;
use ItzenzoTTV\Hooks\CardGraphQL;
use ItzenzoTTV\Hooks\CardImageSize;
use ItzenzoTTV\Hooks\PngSubsizesAsJpeg;
use ItzenzoTTV\Hooks\ShopRedirect;
use ItzenzoTTV\Hooks\ShopSettingsMenuLink;
use Mythus\Provider;

/**
 * Shop Provider.
 *
 * Registers the product/card post types, card taxonomies, ACF field
 * groups and the card GraphQL extensions that itzenzo.tv (Next.js)
 * reads. It is a headless content backend only — no checkout path.
 *
 * Lives in an mu-plugin rather than the theme so the data model and the
 * GraphQL surface itzenzo.tv depends on survive a theme change. Extends
 * Mythus directly: nothing here renders through Timber.
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
     * Assets resolve against the plugin, not the active theme. The provider
     * ships none today; this keeps it independent of get_stylesheet_directory().
     */
    protected function getDistPath(): string
    {
        return dirname(__DIR__) . '/dist';
    }

    protected function getDistUri(): string
    {
        return plugins_url('dist', __DIR__);
    }

    protected function resolveTextDomain(string $providerDir): string
    {
        return 'itzenzottv';
    }

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
                'name'          => __('Card Games', 'itzenzottv'),
                'singular_name' => __('Card Game', 'itzenzottv'),
                'menu_name'     => __('Games', 'itzenzottv'),
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
                'name'          => __('Card Sets', 'itzenzottv'),
                'singular_name' => __('Card Set', 'itzenzottv'),
                'menu_name'     => __('Sets', 'itzenzottv'),
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
}
