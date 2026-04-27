<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Integration\Providers\Shop;

use ChildTheme\Providers\Shop\Hooks\QueueChangeWebhook;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the WP→Nous queue change bridge.
 *
 * Fires the `shop_queue_*` actions directly and intercepts wp_remote_post
 * via the `pre_http_request` filter — proves the webhook builds the right
 * URL, headers, and event payload without requiring a real database, a
 * running Nous, or any actual network I/O.
 */
class QueueChangeWebhookTest extends BaseTestCase
{
    /** @var array<int,array{url:string,args:array}> */
    private array $captured = [];

    public function set_up(): void
    {
        parent::set_up();
        $this->captured = [];

        // Required by the webhook to address Nous + sign requests.
        if (!defined('NOUS_BOT_URL')) {
            define('NOUS_BOT_URL', 'http://nous.test');
        }
        if (!defined('LIVESTREAM_SECRET')) {
            define('LIVESTREAM_SECRET', 'test-secret-queue');
        }

        add_filter('pre_http_request', [$this, 'captureHttp'], 10, 3);

        (new QueueChangeWebhook())->register();
    }

    public function tear_down(): void
    {
        remove_filter('pre_http_request', [$this, 'captureHttp'], 10);
        remove_all_actions('shop_queue_entry_created');
        remove_all_actions('shop_queue_entry_updated');
        remove_all_actions('shop_queue_session_created');
        remove_all_actions('shop_queue_session_updated');
        parent::tear_down();
    }

    public function captureHttp($preempt, $args, $url)
    {
        $this->captured[] = ['url' => (string) $url, 'args' => $args];
        // Returning a non-false value short-circuits the actual HTTP call.
        return ['response' => ['code' => 200], 'body' => '', 'headers' => []];
    }

    public function testEntryCreatedFiresAddedEventWithBotSecret(): void
    {
        do_action('shop_queue_entry_created', $this->sampleEntry());

        $this->assertCount(1, $this->captured, 'expected exactly one HTTP call');

        $call = $this->captured[0];
        $this->assertSame('http://nous.test/webhooks/queue-changed', $call['url']);
        $this->assertSame('test-secret-queue', $call['args']['headers']['X-Bot-Secret']);
        $this->assertFalse($call['args']['blocking'], 'should not block on Nous response');

        $body = json_decode($call['args']['body'], true);
        $this->assertSame('entry.added', $body['event']);
        $this->assertSame('q_42', $body['data']['entry']['id']);
        $this->assertSame(42, $body['data']['rawEntry']['id']);
        $this->assertArrayHasKey('timestamp', $body);
    }

    public function testEntryStatusToCompletedFiresCompletedEvent(): void
    {
        $before = $this->sampleEntry(['status' => 'active']);
        $after = $this->sampleEntry(['status' => 'completed']);

        do_action('shop_queue_entry_updated', $after, $before);

        $this->assertCount(1, $this->captured);
        $body = json_decode($this->captured[0]['args']['body'], true);
        $this->assertSame('entry.completed', $body['event']);
        $this->assertSame('active', $body['data']['previousStatus']);
    }

    public function testEntryStatusChangeOtherThanCompletedFiresAdvancedEvent(): void
    {
        $before = $this->sampleEntry(['status' => 'queued']);
        $after = $this->sampleEntry(['status' => 'active']);

        do_action('shop_queue_entry_updated', $after, $before);

        $body = json_decode($this->captured[0]['args']['body'], true);
        $this->assertSame('entry.advanced', $body['event']);
    }

    public function testEntryUpdateWithNoStatusChangeFiresGenericUpdated(): void
    {
        $before = $this->sampleEntry(['status' => 'queued']);
        $after = $this->sampleEntry(['status' => 'queued', 'detail_label' => 'edited']);

        do_action('shop_queue_entry_updated', $after, $before);

        $body = json_decode($this->captured[0]['args']['body'], true);
        $this->assertSame('entry.updated', $body['event']);
    }

    public function testSessionCreatedFiresOpenedEvent(): void
    {
        do_action('shop_queue_session_created', $this->sampleSession());

        $body = json_decode($this->captured[0]['args']['body'], true);
        $this->assertSame('session.opened', $body['event']);
        $this->assertSame(7, $body['data']['session']['id']);
    }

    public function testSessionUpdatedFiresUpdatedEventWithPreviousStatus(): void
    {
        $before = $this->sampleSession(['status' => 'open']);
        $after = $this->sampleSession(['status' => 'closed', 'closed_at' => '2026-04-27 11:00:00']);

        do_action('shop_queue_session_updated', $after, $before);

        $body = json_decode($this->captured[0]['args']['body'], true);
        $this->assertSame('session.updated', $body['event']);
        $this->assertSame('open', $body['data']['previousStatus']);
    }

    private function sampleEntry(array $overrides = []): array
    {
        return array_merge([
            'id'                => 42,
            'session_id'        => 1,
            'type'              => 'pull_box',
            'source'            => 'discord',
            'status'            => 'queued',
            'discord_user_id'   => '12345',
            'discord_handle'    => 'vinnyrags',
            'customer_email'    => null,
            'order_number'      => null,
            'display_name'      => null,
            'detail_label'      => '$2 tier',
            'detail_data'       => '{"tier":2}',
            'stripe_session_id' => null,
            'external_ref'      => null,
            'created_at'        => '2026-04-27 10:21:03',
            'completed_at'      => null,
        ], $overrides);
    }

    private function sampleSession(array $overrides = []): array
    {
        return array_merge([
            'id'                       => 7,
            'status'                   => 'open',
            'channel_message_id'       => '1234567890',
            'duck_race_winner_user_id' => null,
            'created_at'               => '2026-04-27 09:00:00',
            'closed_at'                => null,
        ], $overrides);
    }
}
