<?php

namespace ChildTheme\Tests\Unit\Providers\Blog;

use ChildTheme\Providers\Blog\NousLog;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Nous Signal Log view model.
 */
class NousLogTest extends TestCase
{
    private function posts(int $n): array
    {
        return array_map(static fn(int $i): array => ['title' => "Post {$i}", 'link' => "/p{$i}/"], range(1, $n));
    }

    public function testNumbersCountDownFromTheTotalAndMarkTheNewest(): void
    {
        $rows = NousLog::rows($this->posts(3), 167);

        $this->assertSame(['167', '166', '165'], array_column($rows, 'number'));
        $this->assertSame([true, false, false], array_column($rows, 'new'));
        $this->assertSame('Post 1', $rows[0]['title']);
    }

    public function testSmallNumbersArePaddedToTwoDigits(): void
    {
        $this->assertSame(['05', '04'], array_column(NousLog::rows($this->posts(2), 5), 'number'));
    }

    public function testTotalNeverUndercountsThePosts(): void
    {
        $this->assertSame(['03', '02', '01'], array_column(NousLog::rows($this->posts(3), 0), 'number'));
    }

    public function testEmptyFeed(): void
    {
        $this->assertSame([], NousLog::rows([], 0));
    }
}
