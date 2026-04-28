<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Flip pending slot claims to confirmed by their Stripe session id.
 * Bot-secret only. Called by the Stripe webhook handler in Nous after
 * a successful checkout — the slots were pre-claimed at session-create
 * time, this confirms them after payment lands.
 */
class PullBoxConfirmEndpoint extends Endpoint
{
    public function __construct(private readonly PullBoxRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/pull-boxes/(?P<id>\d+)/confirm-by-session';
    }

    public function getMethods(): string
    {
        return 'POST';
    }

    public function getPermission(WP_REST_Request $request): bool
    {
        if (current_user_can('edit_posts')) {
            return true;
        }
        $secret = (string) $request->get_header('X-Bot-Secret');
        $expected = defined('LIVESTREAM_SECRET') ? LIVESTREAM_SECRET : '';
        return $expected !== '' && hash_equals($expected, $secret);
    }

    public function getArgs(): array
    {
        return [
            'id'                 => ['required' => true, 'type' => 'integer'],
            'stripe_session_id'  => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $stripeSessionId = (string) $request->get_param('stripe_session_id');
        if ($stripeSessionId === '') {
            return new WP_Error('invalid_session_id', 'stripe_session_id is required.', ['status' => 400]);
        }

        $confirmed = $this->repository->confirmClaimsByStripeSession($stripeSessionId);
        return new WP_REST_Response(['confirmed' => $confirmed]);
    }
}
