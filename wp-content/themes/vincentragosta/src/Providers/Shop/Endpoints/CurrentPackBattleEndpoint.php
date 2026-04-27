<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\ProductRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Writes the "current pack battle" state for the itzenzo.tv homepage widget.
 *
 * Called by the Nous Discord bot on !battle start, close, cancel, and winner
 * declaration. itzenzo.tv reads the resulting ACF options group via WPGraphQL
 * and renders the homepage widget accordingly.
 *
 * Secured with the LIVESTREAM_SECRET shared secret (same model as the
 * stock-decrement endpoint).
 */
class CurrentPackBattleEndpoint extends Endpoint
{
    private const ALLOWED_STATUSES = ['idle', 'open', 'in_progress'];

    public function __construct(
        private readonly ProductRepository $repository,
    ) {}

    public function getRoute(): string
    {
        return '/current-pack-battle';
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
            'secret' => [
                'required' => true,
                'type'     => 'string',
            ],
            'status' => [
                'required' => true,
                'type'     => 'string',
            ],
            'stripe_price_id' => [
                'required' => false,
                'type'     => 'string',
                'default'  => '',
            ],
            'battle_id' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 0,
            ],
            'buy_url' => [
                'required' => false,
                'type'     => 'string',
                'default'  => '',
            ],
            'max_entries' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 0,
            ],
            'paid_entries' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 0,
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $secret = $request->get_param('secret');
        $expectedSecret = defined('LIVESTREAM_SECRET') ? LIVESTREAM_SECRET : '';

        if (!$expectedSecret || $secret !== $expectedSecret) {
            return new WP_Error('unauthorized', 'Invalid secret.', ['status' => 403]);
        }

        $status = sanitize_text_field((string) $request->get_param('status'));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return new WP_Error(
                'invalid_status',
                sprintf('Status must be one of: %s', implode(', ', self::ALLOWED_STATUSES)),
                ['status' => 400]
            );
        }

        update_field('field_cpb_status', $status, 'option');

        if ($status === 'idle') {
            update_field('field_cpb_product', null, 'option');
            update_field('field_cpb_battle_id', 0, 'option');
            update_field('field_cpb_buy_url', '', 'option');
            update_field('field_cpb_max_entries', 0, 'option');
            update_field('field_cpb_paid_entries', 0, 'option');

            return new WP_REST_Response(['status' => 'idle']);
        }

        $priceId = sanitize_text_field((string) $request->get_param('stripe_price_id'));
        $product = $priceId ? $this->repository->findByPriceId($priceId) : null;

        update_field('field_cpb_product', $product?->id, 'option');
        update_field('field_cpb_battle_id', (int) $request->get_param('battle_id'), 'option');
        update_field('field_cpb_buy_url', esc_url_raw((string) $request->get_param('buy_url')), 'option');
        update_field('field_cpb_max_entries', (int) $request->get_param('max_entries'), 'option');
        update_field('field_cpb_paid_entries', (int) $request->get_param('paid_entries'), 'option');

        return new WP_REST_Response([
            'status'        => $status,
            'product_id'    => $product?->id,
            'product_title' => $product?->title(),
            'battle_id'     => (int) $request->get_param('battle_id'),
            'paid_entries'  => (int) $request->get_param('paid_entries'),
        ]);
    }
}
