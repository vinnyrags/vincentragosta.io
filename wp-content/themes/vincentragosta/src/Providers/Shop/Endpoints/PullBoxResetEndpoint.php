<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Reset the active pull box.
 *
 * Operator action — typically called when the chase prize is hit
 * mid-stream and the operator wants a fresh box for the next batch.
 * Closes the current box and opens a new one with the configured
 * defaults (`pb_title`, `pb_total_slots`, `pb_price_id`). The slot
 * picker on the homepage immediately shows 0/N for the new box.
 *
 * Bot-secret authenticated — Discord `/pull reset` calls this
 * directly. The WP admin "Reset Pull Box" button uses the same
 * underlying repository method via admin-post.php.
 */
class PullBoxResetEndpoint extends Endpoint
{
    public function __construct(private readonly PullBoxRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/pull-boxes/reset';
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
        return [];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $newBox = $this->repository->resetActiveBox();
        if (!$newBox) {
            return new WP_Error(
                'pull_box_unconfigured',
                'Cannot reset — pb_price_id is not configured. Set it on the Pull Box & Bundle settings tab first.',
                ['status' => 503]
            );
        }

        return new WP_REST_Response([
            'box' => PullBoxRepository::serializeBox($newBox),
        ], 201);
    }
}
