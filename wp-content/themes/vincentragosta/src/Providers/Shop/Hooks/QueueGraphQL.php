<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Contracts\Hook;

/**
 * Registers WPGraphQL types and a `liveQueue` root query field for the
 * unified queue. itzenzo.tv consumes this for the homepage initial
 * snapshot before subscribing to the SSE stream for live updates.
 */
class QueueGraphQL implements Hook
{
    public function __construct(private readonly QueueRepository $repository)
    {
    }

    public function register(): void
    {
        add_action('graphql_register_types', [$this, 'registerTypes']);
    }

    public function registerTypes(): void
    {
        register_graphql_object_type('QueueEntryIdentifier', [
            'description' => 'How a queue entry is identified to viewers.',
            'fields'      => [
                'kind'  => ['type' => ['non_null' => 'String'], 'description' => 'discord_handle | order_number | display_name'],
                'label' => ['type' => ['non_null' => 'String'], 'description' => 'Pre-formatted label for display.'],
            ],
        ]);

        register_graphql_object_type('QueueEntryDetail', [
            'description' => 'Type-specific detail attached to a queue entry.',
            'fields'      => [
                'label' => ['type' => 'String', 'description' => 'Pre-formatted detail label (e.g. "$2 tier").'],
                'data'  => ['type' => 'String', 'description' => 'JSON-encoded structured data; clients parse as needed.'],
            ],
        ]);

        register_graphql_object_type('QueueEntry', [
            'description' => 'A single entry in the live queue.',
            'fields'      => [
                'id'         => ['type' => ['non_null' => 'String'], 'description' => 'Stable opaque ID (e.g. q_42).'],
                'position'   => ['type' => 'Int', 'description' => 'Logical position in the queue (1 = active).'],
                'status'     => ['type' => ['non_null' => 'String'], 'description' => 'queued | active | completed | skipped'],
                'type'       => ['type' => ['non_null' => 'String'], 'description' => 'order | pack_battle | pull_box | rts'],
                'source'     => ['type' => ['non_null' => 'String'], 'description' => 'discord | shop'],
                'identifier' => ['type' => ['non_null' => 'QueueEntryIdentifier']],
                'detail'     => ['type' => ['non_null' => 'QueueEntryDetail']],
                'createdAt'  => ['type' => ['non_null' => 'String'], 'description' => 'ISO 8601 timestamp.'],
            ],
        ]);

        register_graphql_object_type('QueueSession', [
            'description' => 'A queue session — one livestream window.',
            'fields'      => [
                'id'                    => ['type' => ['non_null' => 'Int']],
                'status'                => ['type' => ['non_null' => 'String'], 'description' => 'open | closed | racing | complete'],
                'channelMessageId'     => ['type' => 'String'],
                'duckRaceWinnerUserId' => ['type' => 'String'],
                'createdAt'            => ['type' => ['non_null' => 'String']],
                'closedAt'             => ['type' => 'String'],
            ],
        ]);

        register_graphql_object_type('LiveQueueSnapshot', [
            'description' => 'Snapshot of the active queue for the homepage LIVE QUEUE section.',
            'fields'      => [
                'session'   => ['type' => 'QueueSession'],
                'active'    => ['type' => 'QueueEntry'],
                'upcoming'  => ['type' => ['list_of' => 'QueueEntry']],
                'completed' => ['type' => ['list_of' => 'QueueEntry'], 'description' => 'Recent completed entries (chronological tail) for the "already opened on stream" timeline.'],
                'total'     => ['type' => ['non_null' => 'Int']],
                'updatedAt' => ['type' => ['non_null' => 'String']],
            ],
        ]);

        register_graphql_field('RootQuery', 'liveQueue', [
            'type'        => 'LiveQueueSnapshot',
            'description' => 'The currently active queue snapshot, or empty payload when no session is open.',
            'args'        => [
                'limit' => [
                    'type'        => 'Int',
                    'description' => 'Max upcoming entries (1-50). Defaults to 10.',
                ],
            ],
            'resolve'     => function ($_root, array $args) {
                $limit = max(1, min(50, (int) ($args['limit'] ?? QueueRepository::DEFAULT_UPCOMING_LIMIT)));
                $session = $this->repository->findActiveSession();

                if (!$session) {
                    return [
                        'session'   => null,
                        'active'    => null,
                        'upcoming'  => [],
                        'completed' => [],
                        'total'     => 0,
                        'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
                    ];
                }

                $snapshot = $this->repository->snapshot((int) $session['id'], $limit);

                $active = $snapshot['active']
                    ? $this->reshapeEntryForGraphQL(QueueRepository::serializeEntry($snapshot['active'], 1))
                    : null;

                $startPosition = $active ? 2 : 1;
                $upcoming = [];
                foreach ($snapshot['upcoming'] as $i => $row) {
                    $upcoming[] = $this->reshapeEntryForGraphQL(
                        QueueRepository::serializeEntry($row, $startPosition + $i)
                    );
                }

                $completed = [];
                foreach ($snapshot['completed'] as $row) {
                    $completed[] = $this->reshapeEntryForGraphQL(
                        QueueRepository::serializeEntry($row)
                    );
                }

                return [
                    'session'   => QueueRepository::serializeSession($session),
                    'active'    => $active,
                    'upcoming'  => $upcoming,
                    'completed' => $completed,
                    'total'     => $snapshot['total'],
                    'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
                ];
            },
        ]);
    }

    /**
     * Convert REST-shape detail.data (array) into GraphQL-shape (JSON string).
     */
    private function reshapeEntryForGraphQL(array $entry): array
    {
        if (isset($entry['detail']['data']) && is_array($entry['detail']['data'])) {
            $entry['detail']['data'] = wp_json_encode($entry['detail']['data']);
        }
        return $entry;
    }
}
