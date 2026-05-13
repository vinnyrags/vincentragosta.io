<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Hooks;

use PHPUnit\Framework\TestCase;

/**
 * Structural tests for the QueueGraphQL hook. WPGraphQL field resolvers
 * are wired to runtime context (the WP request lifecycle), so end-to-end
 * resolver tests aren't viable in WorDBless. These pin the field shape
 * + resolver wiring via source inspection — the same pattern used by
 * QueueEntryUpdateEndpoint's session_id test.
 */
class QueueGraphQLTest extends TestCase
{
    public function testRegistersLiveDuckRaceField(): void
    {
        // The DuckRaceSnapshot shape is the contract the itzenzo.tv
        // homepage Duck Race column reads. Pin the field names so a
        // future refactor can't silently drop one — the frontend's
        // GraphQL query would break with a clearer error than a silent
        // empty render.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Hooks/QueueGraphQL.php'
        );

        $this->assertStringContainsString("'DuckRaceRosterEntry'", $source);
        $this->assertStringContainsString("'DuckRaceSnapshot'", $source);
        $this->assertStringContainsString("'liveDuckRace'", $source);

        // Roster ordering — confirmed first-purchase-time ascending by
        // uniqueBuyers SQL's `ORDER BY first_seen ASC`. The roster
        // entry must carry firstSeenAt so the homepage can match the
        // server order.
        $this->assertStringContainsString("'firstSeenAt'", $source);
        $this->assertStringContainsString("'rosterCount'", $source);
        $this->assertStringContainsString("'winnerUserId'", $source);
    }

    public function testSessionTypeExposesDuckRaceChannelMessageId(): void
    {
        // Nous's Duck Race embed code path needs the persistent message
        // ID to edit-in-place across roster updates. Without this
        // exposed on QueueSession, Nous would have no way to track which
        // Discord message belongs to which session.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Hooks/QueueGraphQL.php'
        );

        $this->assertStringContainsString("'duckRaceChannelMessageId'", $source);
    }
}
