<?php

namespace ChildTheme\Tests\Unit\Providers\Blog;

use ChildTheme\Providers\Blog\NousSequence;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for a transmission's place in the run.
 */
class NousSequenceTest extends TestCase
{
    private const IDS = [11, 12, 15, 20];

    public function testNumbersFromOneWithTheOldestFirst(): void
    {
        $this->assertSame(1, NousSequence::locate(self::IDS, 11)['number']);
        $this->assertSame(3, NousSequence::locate(self::IDS, 15)['number']);
    }

    public function testMiddlePostHasBothNeighbors(): void
    {
        $this->assertSame(['number' => 2, 'previous' => 11, 'next' => 15], NousSequence::locate(self::IDS, 12));
    }

    public function testEndsHaveOneNeighbor(): void
    {
        $this->assertNull(NousSequence::locate(self::IDS, 11)['previous']);
        $this->assertNull(NousSequence::locate(self::IDS, 20)['next']);
        $this->assertSame(15, NousSequence::locate(self::IDS, 20)['previous']);
    }

    public function testStringIdsFromTheDatabaseStillMatch(): void
    {
        $this->assertSame(4, NousSequence::locate(['11', '12', '15', '20'], 20)['number']);
    }

    public function testUnknownPostIsNull(): void
    {
        $this->assertNull(NousSequence::locate(self::IDS, 99));
    }
}
