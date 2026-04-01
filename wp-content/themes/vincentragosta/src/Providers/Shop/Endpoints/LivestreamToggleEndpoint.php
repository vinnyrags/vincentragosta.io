<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Toggles the livestream-active transient.
 *
 * Called by Nous on !live and !offline to enable/disable
 * shipping-free checkout for livestream buyers.
 *
 * Secured with a shared secret from wp-config-env.php.
 */
class LivestreamToggleEndpoint extends Endpoint
{
    public function getRoute(): string
    {
        return '/livestream';
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
            'active' => [
                'required' => true,
                'type'     => 'boolean',
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

        $active = (bool) $request->get_param('active');

        if ($active) {
            // Set for 12 hours — well beyond any stream length, auto-expires as a safety net
            set_transient('itzenzo_livestream_active', true, 43200);
        } else {
            delete_transient('itzenzo_livestream_active');
        }

        return new WP_REST_Response([
            'livestream_active' => $active,
        ]);
    }
}
