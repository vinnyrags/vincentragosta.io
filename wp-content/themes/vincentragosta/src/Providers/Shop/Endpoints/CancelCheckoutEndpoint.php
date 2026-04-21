<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\ShopProvider;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Restores stock when a customer cancels/abandons Stripe Checkout.
 *
 * Stripe redirects to the cancel URL with a token. This endpoint
 * validates the token, restores stock, and redirects to the shop page.
 */
class CancelCheckoutEndpoint extends Endpoint
{
    public function getRoute(): string
    {
        return '/cancel-checkout';
    }

    public function getMethods(): string
    {
        return 'GET';
    }

    public function getPermission(WP_REST_Request $request): bool
    {
        return true;
    }

    public function getArgs(): array
    {
        return [
            'token' => [
                'required' => true,
                'type'     => 'string',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $token = $request->get_param('token');

        if (!$token) {
            wp_redirect(ShopProvider::frontendUrl());
            exit;
        }

        // Decode and validate the token
        $decoded = base64_decode($token, true);

        if (!$decoded) {
            wp_redirect(ShopProvider::frontendUrl());
            exit;
        }

        $data = json_decode($decoded, true);
        $productIds = $data['product_ids'] ?? '';
        $timestamp = $data['timestamp'] ?? 0;

        // Token expires after 35 minutes (slightly longer than the 30-min session)
        if (!$productIds || (time() - $timestamp) > 2100) {
            wp_redirect(ShopProvider::frontendUrl());
            exit;
        }

        // Prevent double-restore (cancel URL + session expired webhook)
        $cacheKey = 'stock_restored_' . md5($productIds . $timestamp);

        if (get_transient($cacheKey)) {
            wp_redirect(ShopProvider::frontendUrl());
            exit;
        }

        // Restore stock atomically
        global $wpdb;
        $pairs = explode(',', $productIds);

        foreach ($pairs as $pair) {
            $parts = explode(':', $pair);

            if (count($parts) !== 2) {
                continue;
            }

            $postId = (int) $parts[0];
            $quantity = (int) $parts[1];

            if ($postId < 1 || $quantity < 1) {
                continue;
            }

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
        }

        // Mark as restored so the expired webhook doesn't double-restore
        // Store the product_ids so the webhook can check
        set_transient($cacheKey, true, 2100); // 35 minutes
        set_transient('stock_restored_session_' . $productIds, true, 2100);

        wp_redirect(ShopProvider::frontendUrl());
        exit;
    }
}
