<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop;

use ChildTheme\Providers\Shop\Endpoints\CancelCheckoutEndpoint;
use ChildTheme\Providers\Shop\Endpoints\CreateCheckoutEndpoint;
use ChildTheme\Providers\Shop\Endpoints\LivestreamToggleEndpoint;
use ChildTheme\Providers\Shop\Endpoints\ShippingLookupEndpoint;
use ChildTheme\Providers\Shop\Endpoints\StockDecrementEndpoint;
use ChildTheme\Providers\Shop\Endpoints\StripeWebhookEndpoint;
use ChildTheme\Providers\Shop\Hooks\StockStatusBadge;
use IX\Providers\Provider;

/**
 * Shop Provider.
 *
 * Registers the product post type, products block, cart assets,
 * and Stripe checkout/webhook REST endpoints.
 */
class ShopProvider extends Provider
{
    /**
     * Always-active hooks.
     */
    protected array $hooks = [
        StockStatusBadge::class,
    ];

    /**
     * Blocks to register.
     */
    protected array $blocks = [
        'products',
    ];

    /**
     * REST API endpoints.
     */
    protected array $routes = [
        CancelCheckoutEndpoint::class,
        CreateCheckoutEndpoint::class,
        LivestreamToggleEndpoint::class,
        ShippingLookupEndpoint::class,
        StockDecrementEndpoint::class,
        StripeWebhookEndpoint::class,
    ];

    /**
     * REST namespace.
     */
    protected string $routeNamespace = 'shop';

    /**
     * Register the shop provider.
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueCartAssets']);
        add_action('vincentragosta_header_actions', [$this, 'renderHeaderCartButton']);

        parent::register();

        $this->acfManager->registerSavePath();
    }

    /**
     * Register the product post type.
     */
    public function registerPostType(): void
    {
        $this->registerPostTypeFromConfig('post-type.json');
    }

    /**
     * Enqueue cart assets on shop pages only.
     */
    public function enqueueCartAssets(): void
    {
        if (!$this->isShopContext()) {
            return;
        }

        $path = get_stylesheet_directory() . '/dist/js/shop/cart.js';

        if (!file_exists($path)) {
            return;
        }

        wp_enqueue_script(
            'vincentragosta-shop-cart',
            get_stylesheet_directory_uri() . '/dist/js/shop/cart.js',
            [],
            filemtime($path),
            true
        );

        $ageGateEnabled = function_exists('get_field') && get_field('age_verification_enabled', 'option');
        $ageGateMessage = function_exists('get_field')
            ? (get_field('age_verification_message', 'option') ?: 'Some products in this category contain mature content. You must be 18 or older to view them.')
            : '';
        $matureCategorySlug = function_exists('get_field')
            ? (get_field('mature_category_slug', 'option') ?: 'mature')
            : 'mature';

        $shopPageId  = function_exists('get_field') ? get_field('shop_page', 'option') : 0;
        $shopPageUrl = $shopPageId ? get_permalink((int) $shopPageId) : '/shop/';

        wp_localize_script('vincentragosta-shop-cart', 'shopConfig', [
            'stripeKey'           => defined('STRIPE_PUBLISHABLE_KEY') ? STRIPE_PUBLISHABLE_KEY : '',
            'restUrl'             => rest_url('shop/v1/'),
            'nonce'               => wp_create_nonce('wp_rest'),
            'shopUrl'             => $shopPageUrl ?: '/shop/',
            'ageGateEnabled'      => (bool) $ageGateEnabled,
            'ageGateMessage'      => $ageGateMessage,
            'matureCategorySlug'  => $matureCategorySlug,
        ]);
    }

    /**
     * Render the cart button in the header on shop pages.
     */
    public function renderHeaderCartButton(): void
    {
        if (!$this->isShopContext()) {
            return;
        }

        $factory = new \IX\Services\IconServiceFactory();
        $icon = $factory->create('cart')->render();
        echo '<button class="header__cart-toggle" type="button" aria-label="Open cart" data-cart-toggle>'
            . '<span class="header__cart-icon" aria-hidden="true">' . $icon . '</span>'
            . '<span class="header__cart-count" data-cart-count>0</span>'
            . '</button>';
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueStyle('vincentragosta-shop', 'shop.css');
        $this->enqueueStyle('vincentragosta-shop-products', 'products.css');
    }

    /**
     * Whether the current page is a shop context (shop page, cart, or thank you).
     */
    private function isShopContext(): bool
    {
        if (is_page('thank-you')) {
            return true;
        }

        if (!function_exists('get_field')) {
            return false;
        }

        $shopPageId = get_field('shop_page', 'option');

        return $shopPageId && is_page((int) $shopPageId);
    }
}
