<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Open a new pull box. Bot-secret authenticated — called by Nous's
 * `/pull` command.
 *
 * Refuses if a box is already open. One pull box at a time matches the
 * Discord workflow and keeps the homepage modal's "active box" lookup
 * unambiguous.
 */
class PullBoxCreateEndpoint extends Endpoint
{
    public function __construct(private readonly PullBoxRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/pull-boxes';
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
            'name'              => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'price_cents'       => ['required' => true, 'type' => 'integer'],
            'total_slots'       => ['required' => true, 'type' => 'integer'],
            'stripe_price_id'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'discord_message_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $totalSlots = (int) $request->get_param('total_slots');
        if ($totalSlots < 1) {
            return new WP_Error('invalid_total_slots', 'total_slots must be at least 1.', ['status' => 400]);
        }

        $existing = $this->repository->findActiveBox();
        if ($existing) {
            return new WP_Error(
                'box_already_open',
                'A pull box is already open. Close it before opening another.',
                ['status' => 409, 'box' => PullBoxRepository::serializeBox($existing)]
            );
        }

        // If the caller didn't supply a Stripe price ID, fall back to the
        // configured price from the shop-settings options page so Nous's
        // /pull doesn't have to know about ACF.
        $stripePriceId = $request->get_param('stripe_price_id');
        if (!$stripePriceId) {
            $stripePriceId = (string) get_field('pb_price_id', 'option');
        }

        $id = $this->repository->createBox([
            'name'               => $request->get_param('name'),
            'price_cents'        => (int) $request->get_param('price_cents'),
            'stripe_price_id'    => $stripePriceId ?: null,
            'total_slots'        => $totalSlots,
            'discord_message_id' => $request->get_param('discord_message_id'),
        ]);

        return new WP_REST_Response([
            'box' => PullBoxRepository::serializeBox($this->repository->findBox($id)),
        ], 201);
    }
}
