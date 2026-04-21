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
 * Stock is decremented optimistically at checkout time (CreateCheckoutEndpoint).
 * This webhook handles:
 * - checkout.session.completed: no-op (stock already decremented, notifications via Discord bot)
 * - checkout.session.expired: restores stock for abandoned checkouts
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
        } elseif ($event->type === 'checkout.session.expired') {
            $this->handleCheckoutExpired($event->data->object);
        }

        return new WP_REST_Response(['received' => true]);
    }

    /**
     * Handle a completed checkout session.
     *
     * Stock was already decremented optimistically by CreateCheckoutEndpoint.
     * Order notifications are handled by the Discord bot via its own Stripe webhook.
     */
    private function handleCheckoutCompleted(object $session): void
    {
        // No-op on the WordPress side. Stock is already decremented at checkout time.
        // The bot's Stripe webhook handles order notifications in Discord (#order-feed).
    }

    /**
     * Handle an expired/abandoned checkout session by restoring stock.
     *
     * Stock was decremented optimistically at checkout time.
     * If the session expires without payment, restore the quantities.
     */
    private function handleCheckoutExpired(object $session): void
    {
        $productData = $session->metadata->product_ids ?? '';

        if (!$productData) {
            return;
        }

        // Skip if stock was already restored by the cancel endpoint
        if (get_transient('stock_restored_session_' . $productData)) {
            return;
        }

        global $wpdb;
        $pairs = explode(',', $productData);

        foreach ($pairs as $pair) {
            [$postId, $quantity] = explode(':', $pair);
            $postId = (int) $postId;
            $quantity = (int) $quantity;

            if ($postId < 1 || $quantity < 1) {
                continue;
            }

            // Atomic stock restore
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = CAST(meta_value AS SIGNED) + %d
                 WHERE post_id = %d AND meta_key = %s",
                $quantity,
                $postId,
                'stock_quantity',
            ));

            // Sync in-memory meta and cache
            $newStock = (int) get_post_meta($postId, 'stock_quantity', true) + $quantity;
            update_post_meta($postId, 'stock_quantity', $newStock);
            clean_post_cache($postId);

            // Keep Stripe metadata in sync
            $stripeProductId = get_post_meta($postId, 'stripe_product_id', true);
            if ($stripeProductId) {
                $this->stripe->syncStockToStripe($stripeProductId, $newStock);
            }
        }
    }

}
