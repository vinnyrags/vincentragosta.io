<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Services\StripeService;
use Mythus\Support\Rest\Endpoint;
use Stripe\Exception\SignatureVerificationException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Receives Stripe webhook events.
 *
 * Verifies the webhook signature and handles checkout.session.completed
 * events to decrement stock quantities.
 */
class StripeWebhookEndpoint extends Endpoint
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    public function getRoute(): string
    {
        return '/webhook';
    }

    public function getMethods(): string
    {
        return 'POST';
    }

    /**
     * Public endpoint — Stripe sends webhooks from their servers.
     * Signature verification happens in the callback.
     */
    public function getPermission(WP_REST_Request $request): bool
    {
        return true;
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = $request->get_body();
        $signature = $request->get_header('stripe-signature') ?? '';

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $signature);
        } catch (SignatureVerificationException $e) {
            return new WP_Error(
                'invalid_signature',
                'Webhook signature verification failed.',
                ['status' => 400]
            );
        } catch (\Throwable $e) {
            return new WP_Error(
                'webhook_error',
                'Failed to process webhook.',
                ['status' => 400]
            );
        }

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event->data->object);
        }

        return new WP_REST_Response(['received' => true]);
    }

    /**
     * Handle a completed checkout session by decrementing stock.
     */
    private function handleCheckoutCompleted(object $session): void
    {
        $productData = $session->metadata->product_ids ?? '';

        if (!$productData) {
            return;
        }

        // Format: "123:2,456:1" (product_id:quantity pairs)
        $pairs = explode(',', $productData);

        foreach ($pairs as $pair) {
            [$postId, $quantity] = explode(':', $pair);
            $postId = (int) $postId;
            $quantity = (int) $quantity;

            if ($postId < 1 || $quantity < 1) {
                continue;
            }

            $currentStock = (int) get_field('stock_quantity', $postId);
            $newStock = max(0, $currentStock - $quantity);

            update_field('stock_quantity', $newStock, $postId);
        }
    }
}
