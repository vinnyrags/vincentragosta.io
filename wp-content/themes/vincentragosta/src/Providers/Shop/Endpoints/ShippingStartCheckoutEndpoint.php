<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\TouAcceptance;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Start a no-Discord shipping payment checkout.
 *
 * A buyer on itzenzo.tv enters their email in the ShippingPaymentModal,
 * accepts the current Terms, and submits. This endpoint:
 *
 *   1. Validates ToS acceptance via TouAcceptance (same gate every
 *      other checkout endpoint runs).
 *   2. Forwards the email + ToS audit metadata to the Nous bot's
 *      POST /shipping/start-checkout.
 *   3. Returns the response untouched — either "you're covered"
 *      or a Stripe checkout URL the frontend redirects to.
 *
 * The Nous side is responsible for the actual rate lookup + Stripe
 * session creation. This endpoint exists because the itzenzo.tv
 * frontend only talks to WordPress; it doesn't reach Nous directly.
 *
 * Pattern mirrors BundleCheckoutEndpoint — the same shape used for
 * every other "frontend → WP → Nous → Stripe" pass-through.
 */
class ShippingStartCheckoutEndpoint extends Endpoint
{
    private const BOT_URL = 'http://127.0.0.1:3100';

    public function getRoute(): string
    {
        return '/shipping/start-checkout';
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
            'email' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $email = (string) $request->get_param('email');

        if (!$email || !is_email($email)) {
            return new WP_Error(
                'invalid_email',
                'Enter a valid email so we can look up your shipping.',
                ['status' => 400]
            );
        }

        // ToS gate. Mirrors the Bundle / Pull-box / Cart flow — any
        // Stripe-bound transaction goes through this validator first.
        $touMetadata = TouAcceptance::validate($request);
        if ($touMetadata instanceof WP_Error) {
            return $touMetadata;
        }

        // Forward to Nous. The bot computes the rate server-side
        // (no client-trusted amount) and creates the Stripe session.
        $response = wp_remote_post(self::BOT_URL . '/shipping/start-checkout', [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'email'        => $email,
                'tos_metadata' => $touMetadata,
            ]),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'bot_unreachable',
                'Shipping service is temporarily unavailable. Try again in a moment.',
                ['status' => 503]
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return new WP_Error(
                'bot_invalid_response',
                'Shipping service returned an unexpected response.',
                ['status' => 502]
            );
        }

        return new WP_REST_Response($body, $code);
    }
}
