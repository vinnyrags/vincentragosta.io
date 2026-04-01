<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\ProductRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Decrements stock for a product by Stripe price ID.
 *
 * Called by Nous when the owner joins a pack battle
 * (consumes inventory without a Stripe payment).
 *
 * Secured with a shared secret from wp-config-env.php.
 */
class StockDecrementEndpoint extends Endpoint
{
    public function __construct(
        private readonly ProductRepository $repository,
    ) {}

    public function getRoute(): string
    {
        return '/decrement-stock';
    }

    public function getMethods(): string
    {
        return 'POST';
    }

    public function getPermission(WP_REST_Request $request): bool
    {
        return true;
    }

    public function getArgs(): array
    {
        return [
            'price_id' => [
                'required' => true,
                'type'     => 'string',
            ],
            'quantity' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 1,
            ],
            'secret' => [
                'required' => true,
                'type'     => 'string',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $secret = $request->get_param('secret');
        $expectedSecret = defined('LIVESTREAM_SECRET') ? LIVESTREAM_SECRET : '';

        if (!$expectedSecret || $secret !== $expectedSecret) {
            return new WP_Error(
                'unauthorized',
                'Invalid secret.',
                ['status' => 403]
            );
        }

        $priceId = sanitize_text_field($request->get_param('price_id'));
        $quantity = (int) $request->get_param('quantity');

        $product = $this->repository->findByPriceId($priceId);

        if (!$product) {
            return new WP_Error(
                'product_not_found',
                sprintf('No product found for price ID: %s', $priceId),
                ['status' => 404]
            );
        }

        $currentStock = (int) get_field('stock_quantity', $product->id);
        $newStock = max(0, $currentStock - $quantity);
        update_field('stock_quantity', $newStock, $product->id);

        // Keep Stripe metadata in sync
        $stripeProductId = $product->stripeProductId();
        if ($stripeProductId && defined('STRIPE_SECRET_KEY') && STRIPE_SECRET_KEY !== '') {
            try {
                $stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
                $stripe->products->update($stripeProductId, [
                    'metadata' => ['stock' => (string) $newStock],
                ]);
            } catch (\Throwable $e) {
                error_log("Failed to sync stock to Stripe: {$e->getMessage()}");
            }
        }

        return new WP_REST_Response([
            'product'   => $product->title(),
            'old_stock' => $currentStock,
            'new_stock' => $newStock,
        ]);
    }
}
