<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop;

use ChildTheme\Providers\Shop\Endpoints\CancelCheckoutEndpoint;
use ChildTheme\Providers\Shop\Endpoints\CreateCheckoutEndpoint;
use ChildTheme\Providers\Shop\Endpoints\ShippingLookupEndpoint;
use ChildTheme\Providers\Shop\Endpoints\StockDecrementEndpoint;
use ChildTheme\Providers\Shop\Endpoints\StripeWebhookEndpoint;
use ChildTheme\Providers\Shop\Hooks\StockStatusBadge;
use IX\Providers\Provider;

/**
 * Shop Provider.
 *
 * Registers the product post type, REST endpoints for Stripe checkout
 * and stock management, and ACF field groups. The frontend storefront
 * has moved to itzenzo.tv (Next.js) — this provider serves as the
 * headless backend only.
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
     * REST API endpoints.
     */
    protected array $routes = [
        CancelCheckoutEndpoint::class,
        CreateCheckoutEndpoint::class,
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
