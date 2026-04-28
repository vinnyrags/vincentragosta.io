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
 * `!pull` command.
 *
 * Refuses if a box for the same tier is already open. One pull box
 * per tier at a time matches the Discord workflow and keeps the
 * homepage modal's "active box" lookup unambiguous.
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
            'tier'              => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'price_cents'       => ['required' => true, 'type' => 'integer'],
            'total_slots'       => ['required' => true, 'type' => 'integer'],
            'stripe_price_id'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'discord_message_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $tier = (string) $request->get_param('tier');
        if (!in_array($tier, PullBoxRepository::TIERS, true)) {
            return new WP_Error('invalid_tier', 'Tier must be one of: ' . implode(', ', PullBoxRepository::TIERS), ['status' => 400]);
        }

        $totalSlots = (int) $request->get_param('total_slots');
        if ($totalSlots < 1) {
            return new WP_Error('invalid_total_slots', 'total_slots must be at least 1.', ['status' => 400]);
        }

        $existing = $this->repository->findActiveBox($tier);
        if ($existing) {
            return new WP_Error(
                'box_already_open',
                "A pull box for tier '{$tier}' is already open. Close it before opening another.",
                ['status' => 409, 'box' => PullBoxRepository::serializeBox($existing)]
            );
        }

        // If the caller didn't supply a Stripe price ID, fall back to the
        // configured tier price from the shop-settings options page.
        // That way Nous's !pull doesn't have to know about ACF — it
        // just specifies the tier and WP fills in the right Stripe ID.
        $stripePriceId = $request->get_param('stripe_price_id');
        if (!$stripePriceId) {
            $stripePriceId = $tier === 'v'
                ? (string) get_field('pb_v_price_id', 'option')
                : (string) get_field('pb_vmax_price_id', 'option');
        }

        $id = $this->repository->createBox([
            'name'               => $request->get_param('name'),
            'tier'               => $tier,
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
