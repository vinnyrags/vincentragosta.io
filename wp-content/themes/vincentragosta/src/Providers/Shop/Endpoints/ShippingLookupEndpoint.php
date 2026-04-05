<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Proxies a shipping status lookup to the Nous bot.
 *
 * Called by the cart JS before checkout to determine whether
 * the buyer already has shipping covered this period.
 */
class ShippingLookupEndpoint extends Endpoint
{
    private const BOT_URL = 'http://127.0.0.1:3100';

    public function getRoute(): string
    {
        return '/shipping-lookup';
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
            'email' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $email = $request->get_param('email');

        if (!$email || !is_email($email)) {
            return new WP_Error(
                'invalid_email',
                'A valid email address is required.',
                ['status' => 400]
            );
        }

        $response = wp_remote_get(
            self::BOT_URL . '/shipping/lookup?' . http_build_query(['email' => $email]),
            ['timeout' => 5]
        );

        if (is_wp_error($response)) {
            // Bot unreachable — return safe defaults
            return new WP_REST_Response([
                'email'         => $email,
                'known'         => false,
                'covered'       => false,
                'international' => false,
                'rate'          => 1000,
                'label'         => 'Standard Shipping (US)',
            ]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body)) {
            return new WP_REST_Response([
                'email'         => $email,
                'known'         => false,
                'covered'       => false,
                'international' => false,
                'rate'          => 1000,
                'label'         => 'Standard Shipping (US)',
            ]);
        }

        return new WP_REST_Response($body);
    }
}
