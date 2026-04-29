<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Integration\Providers\Shop;

use ChildTheme\Providers\Shop\Hooks\ActivityWebhook;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the WP→Nous Activity Feed bridge.
 *
 * Mirrors QueueChangeWebhookTest — fires the `shop_pull_box_slot_claimed`
 * action and intercepts wp_remote_post via `pre_http_request` to assert
 * URL, headers, and event payload without real network I/O.
 */
class ActivityWebhookTest extends BaseTestCase
{
    /** @var array<int,array{url:string,args:array}> */
    private array $captured = [];

    public function set_up(): void
    {
        parent::set_up();
        $this->captured = [];

        if (!defined('NOUS_BOT_URL')) {
            define('NOUS_BOT_URL', 'http://nous.test');
        }
        if (!defined('LIVESTREAM_SECRET')) {
            define('LIVESTREAM_SECRET', 'test-secret-activity');
        }

        add_filter('pre_http_request', [$this, 'captureHttp'], 10, 3);

        (new ActivityWebhook())->register();
    }

    public function tear_down(): void
    {
        remove_filter('pre_http_request', [$this, 'captureHttp'], 10);
        remove_all_actions('shop_pull_box_slot_claimed');
        parent::tear_down();
    }

    public function captureHttp($preempt, $args, $url)
    {
        $this->captured[] = ['url' => (string) $url, 'args' => $args];
        return ['response' => ['code' => 200], 'body' => '', 'headers' => []];
    }

    public function testSlotClaimedFiresActivityEventWithBotSecret(): void
    {
        do_action(
            'shop_pull_box_slot_claimed',
            $this->sampleBox(),
            [17, 23],
            ['discord_handle' => 'vinnyrags']
        );

        $this->assertCount(1, $this->captured, 'expected exactly one HTTP call');

        $call = $this->captured[0];
        $this->assertSame(rtrim(NOUS_BOT_URL, '/') . '/webhooks/activity-changed', $call['url']);
        $this->assertSame(LIVESTREAM_SECRET, $call['args']['headers']['X-Bot-Secret']);
        $this->assertFalse($call['args']['blocking'], 'should not block on Nous response');

        $body = json_decode($call['args']['body'], true);
        $this->assertSame('activity.pull_box.claim', $body['event']);
        $this->assertSame('pull_box.claim', $body['data']['kind']);
        $this->assertSame('@vinnyrags claimed slots 17, 23 in V Box', $body['data']['description']);
        $this->assertSame([17, 23], $body['data']['meta']['slots']);
        $this->assertSame(4, $body['data']['meta']['boxId']);
        $this->assertArrayHasKey('timestamp', $body);
    }

    public function testSingleSlotUsesSingularLabel(): void
    {
        do_action(
            'shop_pull_box_slot_claimed',
            $this->sampleBox(),
            [9],
            ['discord_handle' => 'somebody']
        );

        $body = json_decode($this->captured[0]['args']['body'], true);
        $this->assertStringContainsString('claimed slot 9', $body['data']['description']);
    }

    public function testEmailFallbackIsRedacted(): void
    {
        do_action(
            'shop_pull_box_slot_claimed',
            $this->sampleBox(),
            [3],
            ['customer_email' => 'someone@example.com']
        );

        $body = json_decode($this->captured[0]['args']['body'], true);
        $this->assertStringContainsString('s•••@example.com', $body['data']['description']);
    }

    public function testGuestFallbackWhenNoIdentity(): void
    {
        do_action(
            'shop_pull_box_slot_claimed',
            $this->sampleBox(),
            [1],
            []
        );

        $body = json_decode($this->captured[0]['args']['body'], true);
        $this->assertStringContainsString('Someone claimed', $body['data']['description']);
    }

    public function testNullBoxNoOps(): void
    {
        do_action(
            'shop_pull_box_slot_claimed',
            null,
            [1],
            ['discord_handle' => 'whoever']
        );

        $this->assertCount(0, $this->captured, 'should not POST when box lookup failed');
    }

    private function sampleBox(array $overrides = []): array
    {
        return array_merge([
            'id'                 => 4,
            'name'               => 'V Box',
            'tier'               => 'v',
            'price_cents'        => 100,
            'stripe_price_id'    => 'price_test',
            'total_slots'        => 100,
            'status'             => 'open',
            'discord_message_id' => '12345',
            'created_at'         => '2026-04-28 10:00:00',
            'closed_at'          => null,
        ], $overrides);
    }
}
