<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public snapshot of the active queue session.
 *
 * Returns the active session metadata, the entry currently being served,
 * the top N upcoming queued entries, and the total count. Designed to be
 * cached at the edge for ~2 seconds with ETag-driven 304 responses.
 */
class QueueSnapshotEndpoint extends Endpoint
{
    public function __construct(private readonly QueueRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/queue';
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
            'limit' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => QueueRepository::DEFAULT_UPCOMING_LIMIT,
            ],
            'session_id' => [
                'required' => false,
                'type'     => 'integer',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response
    {
        $limit = max(1, min(50, (int) $request->get_param('limit')));
        $sessionId = (int) $request->get_param('session_id');

        $session = $sessionId > 0
            ? $this->repository->findSession($sessionId)
            : $this->repository->findActiveSession();

        if (!$session) {
            return $this->withCacheHeaders(new WP_REST_Response([
                'session'   => null,
                'active'    => null,
                'upcoming'  => [],
                'completed' => [],
                'total'     => 0,
                'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            ]), 'empty');
        }

        $snapshot = $this->repository->snapshot((int) $session['id'], $limit);

        $activeSerialized = null;
        if ($snapshot['active']) {
            $activeSerialized = QueueRepository::serializeEntry($snapshot['active'], 1);
        }

        $startPosition = $activeSerialized ? 2 : 1;
        $upcomingSerialized = [];
        foreach ($snapshot['upcoming'] as $i => $row) {
            $upcomingSerialized[] = QueueRepository::serializeEntry($row, $startPosition + $i);
        }

        // Completed entries don't carry positions — they're a chronological
        // tail used for narrative context ("already opened on stream").
        $completedSerialized = [];
        foreach ($snapshot['completed'] as $row) {
            $completedSerialized[] = QueueRepository::serializeEntry($row);
        }

        $payload = [
            'session'   => QueueRepository::serializeSession($session),
            'active'    => $activeSerialized,
            'upcoming'  => $upcomingSerialized,
            'completed' => $completedSerialized,
            'total'     => $snapshot['total'],
            'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $etagSeed = sprintf(
            '%d:%s:%s:%s:%d',
            (int) $session['id'],
            $activeSerialized['id'] ?? 'none',
            implode(',', array_map(fn($e) => $e['id'], $upcomingSerialized)),
            implode(',', array_map(fn($e) => $e['id'], $completedSerialized)),
            $snapshot['total']
        );

        return $this->withCacheHeaders(new WP_REST_Response($payload), $etagSeed);
    }

    private function withCacheHeaders(WP_REST_Response $response, string $etagSeed): WP_REST_Response
    {
        $etag = '"' . md5($etagSeed) . '"';
        $response->header('ETag', $etag);
        $response->header('Cache-Control', 'public, max-age=2, stale-while-revalidate=10');

        $clientEtag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
        if ($clientEtag !== '' && hash_equals($etag, $clientEtag)) {
            $response->set_status(304);
            $response->set_data(null);
        }

        return $response;
    }
}
