<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use Mythus\Support\Rest\Endpoint;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public read of the currently-active pull box for a tier.
 *
 * Returns the box metadata + every claimed slot's number + buyer
 * label, so the homepage modal can render a slot grid showing which
 * positions are taken (and by whom). Returns `null` when no box is
 * open for the requested tier.
 */
class PullBoxActiveEndpoint extends Endpoint
{
    public function __construct(private readonly PullBoxRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/pull-boxes/active';
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
            'tier' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response
    {
        $tier = (string) $request->get_param('tier');
        $tier = in_array($tier, PullBoxRepository::TIERS, true) ? $tier : null;

        $box = $this->repository->findActiveBox($tier);
        if (!$box) {
            return new WP_REST_Response(['box' => null]);
        }

        $claims = $this->repository->getSlotClaims((int) $box['id']);
        $serializedClaims = array_map([PullBoxRepository::class, 'serializeSlotClaim'], $claims);

        return new WP_REST_Response([
            'box' => PullBoxRepository::serializeBox($box, array_map(static fn($c) => [
                'slotNumber'   => $c['slotNumber'],
                'claimStatus'  => $c['claimStatus'],
                'displayLabel' => $c['displayLabel'],
            ], $serializedClaims)),
        ]);
    }
}
