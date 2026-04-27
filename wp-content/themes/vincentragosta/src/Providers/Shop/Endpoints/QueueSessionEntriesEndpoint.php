<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Full entries list for a session — used by Nous duck race roster
 * (which needs every buyer, not just the top-N snapshot).
 */
class QueueSessionEntriesEndpoint extends Endpoint
{
    public function __construct(private readonly QueueRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/queue/sessions/(?P<id>\d+)/entries';
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
            'id' => [
                'required' => true,
                'type'     => 'integer',
            ],
            'status' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId = (int) $request->get_param('id');
        $session = $this->repository->findSession($sessionId);
        if (!$session) {
            return new WP_Error('not_found', 'Session not found.', ['status' => 404]);
        }

        $statusParam = (string) $request->get_param('status');
        $statusFilter = in_array($statusParam, QueueRepository::ENTRY_STATUSES, true) ? $statusParam : null;

        $rows = $this->repository->allEntries($sessionId, $statusFilter);
        $entries = [];
        foreach ($rows as $i => $row) {
            $entries[] = QueueRepository::serializeEntryRaw($row, $i + 1);
        }

        $buyers = $this->repository->uniqueBuyers($sessionId);

        return new WP_REST_Response([
            'session'      => QueueRepository::serializeSession($session),
            'entries'      => $entries,
            'uniqueBuyers' => array_values(array_map(static fn($r) => (string) $r['buyer'], $buyers)),
        ]);
    }
}
