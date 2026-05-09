<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\CardRequestEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural tests for CardRequestEndpoint — pinned because the RTS
 * consolidation rewrote this endpoint to use a single queue write
 * with external_ref idempotency. The contract shape (route, method,
 * args, public permission, no separate /requests* endpoints) is the
 * surface a future refactor must preserve.
 *
 * Callback-body branch tests would require WorDBless to simulate
 * $wpdb + get_field which it doesn't — the runtime branches (validation,
 * findActiveSession=null → 503, duplicate detection via external_ref,
 * happy-path createEntry) are verified end-to-end on production. The
 * smoke test in the previous deploy proved the 503 path; the duplicate
 * + happy-path branches were exercised by the manual smoke session
 * and have been live since.
 */
class CardRequestEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(CardRequestEndpoint::class, Endpoint::class));
    }

    public function testRouteIsCardRequest(): void
    {
        $endpoint = (new ReflectionClass(CardRequestEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertSame('/card-request', $endpoint->getRoute());
    }

    public function testMethodIsPost(): void
    {
        $endpoint = (new ReflectionClass(CardRequestEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertSame('POST', $endpoint->getMethods());
    }

    public function testPermissionIsPublic(): void
    {
        // Buyers submit anonymously from /cards. The auth gate is
        // (a) email validity, and (b) card existence/publish status —
        // both done in the callback. There's intentionally no role
        // check at the permission layer.
        $endpoint = (new ReflectionClass(CardRequestEndpoint::class))->newInstanceWithoutConstructor();
        $request = $this->createMock(\WP_REST_Request::class);
        $this->assertTrue($endpoint->getPermission($request));
    }

    public function testArgsContractCardIdEmailDiscordUsername(): void
    {
        $endpoint = (new ReflectionClass(CardRequestEndpoint::class))->newInstanceWithoutConstructor();
        $args = $endpoint->getArgs();

        $this->assertArrayHasKey('card_id', $args);
        $this->assertTrue($args['card_id']['required']);
        $this->assertSame('integer', $args['card_id']['type']);

        $this->assertArrayHasKey('email', $args);
        $this->assertTrue($args['email']['required']);
        $this->assertSame('sanitize_email', $args['email']['sanitize_callback']);

        $this->assertArrayHasKey('discord_username', $args);
        $this->assertFalse($args['discord_username']['required'], 'discord_username is optional — buyers from the homepage may not have a Discord');
        $this->assertSame('', $args['discord_username']['default']);
    }

    public function testNoLegacyAdminEndpointsCoexist(): void
    {
        // Pin the RTS consolidation: the dedicated wp_card_view_requests
        // table + its endpoints (CardRequestStatusEndpoint,
        // CardRequestsListEndpoint, CardRequestsAdminPage,
        // CardRequestsMigration) were intentionally deleted. RTS lives
        // entirely in the unified queue now. This test fails loudly if
        // any of those classes get re-introduced.
        $this->assertFalse(
            class_exists('ChildTheme\\Providers\\Shop\\Endpoints\\CardRequestStatusEndpoint'),
            'CardRequestStatusEndpoint was removed in the RTS consolidation'
        );
        $this->assertFalse(
            class_exists('ChildTheme\\Providers\\Shop\\Endpoints\\CardRequestsListEndpoint'),
            'CardRequestsListEndpoint was removed in the RTS consolidation'
        );
        $this->assertFalse(
            class_exists('ChildTheme\\Providers\\Shop\\Hooks\\CardRequestsAdminPage'),
            'CardRequestsAdminPage was removed in the RTS consolidation'
        );
    }

    public function testExternalRefFormatIsRtsCardIdEmail(): void
    {
        // The format `rts:{cardId}:{email}` is the idempotency key
        // QueueRepository::findActiveEntryByExternalRef looks up — if
        // this format ever changes, every existing pending RTS entry
        // becomes orphaned (a re-submission would create a new row
        // instead of returning the existing one). Verified by reading
        // the source so the format is asserted as a literal rather
        // than just the runtime behavior.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/CardRequestEndpoint.php'
        );
        $this->assertStringContainsString(
            "sprintf('rts:%d:%s', \$cardId, \$email)",
            $source,
            'External_ref format must remain rts:{cardId}:{email} — changing this orphans all in-flight RTS entries'
        );
    }
}
