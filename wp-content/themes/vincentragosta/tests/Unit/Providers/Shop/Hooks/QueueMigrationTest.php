<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Hooks\QueueMigration;
use Mythus\Contracts\Hook;
use PHPUnit\Framework\TestCase;

class QueueMigrationTest extends TestCase
{
    public function testImplementsHook(): void
    {
        $this->assertTrue(is_subclass_of(QueueMigration::class, Hook::class));
    }

    public function testTableNamesAreNamespacedByPrefix(): void
    {
        global $wpdb;

        // WorDBless sets up $wpdb with the wp_ prefix; this guards against
        // accidental global-table writes that would collide with WP core.
        $sessionsName = QueueMigration::sessionsTable();
        $entriesName = QueueMigration::entriesTable();

        $this->assertStringContainsString($wpdb->prefix, $sessionsName);
        $this->assertStringContainsString($wpdb->prefix, $entriesName);
        $this->assertStringEndsWith('queue_sessions', $sessionsName);
        $this->assertStringEndsWith('queue_entries', $entriesName);
    }
}
