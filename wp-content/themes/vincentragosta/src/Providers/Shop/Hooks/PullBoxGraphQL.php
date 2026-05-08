<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use Mythus\Contracts\Hook;

/**
 * Registers WPGraphQL types for slot-based pull boxes and an
 * `activePullBox` root query field. itzenzo.tv consumes this for the
 * homepage modal's initial slot grid (then stays current via the SSE
 * stream once Phase 4 wires up live broadcasts).
 */
class PullBoxGraphQL implements Hook
{
    public function __construct(private readonly PullBoxRepository $repository)
    {
    }

    public function register(): void
    {
        add_action('graphql_register_types', [$this, 'registerTypes']);
    }

    public function registerTypes(): void
    {
        register_graphql_object_type('PullBoxSlotClaim', [
            'description' => 'A single claimed slot in a pull box.',
            'fields'      => [
                'slotNumber'   => ['type' => ['non_null' => 'Int']],
                'claimStatus'  => ['type' => ['non_null' => 'String'], 'description' => 'pending | confirmed'],
                'displayLabel' => ['type' => ['non_null' => 'String'], 'description' => 'Pre-formatted buyer label (e.g. @vinnyrags or v•••@example.com).'],
            ],
        ]);

        register_graphql_object_type('PullBox', [
            'description' => 'A pull box opened on stream — buyers claim numbered slots up to total_slots.',
            'fields'      => [
                'id'               => ['type' => ['non_null' => 'Int']],
                'name'             => ['type' => ['non_null' => 'String']],
                'priceCents'       => ['type' => ['non_null' => 'Int']],
                'stripePriceId'    => ['type' => 'String'],
                'totalSlots'       => ['type' => ['non_null' => 'Int']],
                'status'           => ['type' => ['non_null' => 'String'], 'description' => 'open | closed'],
                'discordMessageId' => ['type' => 'String'],
                'claimedSlots'     => ['type' => ['list_of' => 'PullBoxSlotClaim']],
                'createdAt'        => ['type' => ['non_null' => 'String']],
                'closedAt'         => ['type' => 'String'],
            ],
        ]);

        register_graphql_field('RootQuery', 'activePullBox', [
            'type'        => 'PullBox',
            'description' => 'The currently-open pull box, or null if none is open.',
            'resolve'     => function () {
                $box = $this->repository->findActiveBox();
                if (!$box) {
                    return null;
                }

                $claims = $this->repository->getSlotClaims((int) $box['id']);
                $serializedClaims = array_map(static function ($claim) {
                    $s = PullBoxRepository::serializeSlotClaim($claim);
                    return [
                        'slotNumber'   => $s['slotNumber'],
                        'claimStatus'  => $s['claimStatus'],
                        'displayLabel' => $s['displayLabel'],
                    ];
                }, $claims);

                return PullBoxRepository::serializeBox($box, $serializedClaims);
            },
        ]);
    }
}
