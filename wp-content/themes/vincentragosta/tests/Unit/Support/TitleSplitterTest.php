<?php

namespace ChildTheme\Tests\Unit\Support;

use ChildTheme\Support\TitleSplitter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the display-title splitter.
 */
class TitleSplitterTest extends TestCase
{
    /**
     * @dataProvider titleProvider
     */
    public function testSplitTitle(string $title, string $lead, string $accent, string $suffix): void
    {
        $this->assertSame(
            ['lead' => $lead, 'accent' => $accent, 'suffix' => $suffix],
            TitleSplitter::split($title)
        );
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function titleProvider(): array
    {
        return [
            'two words'        => ['Celebrity Autobiography', 'Celebrity', 'Autobiography', ''],
            'many words'       => ['A View From The Bridge', 'A View From The', 'Bridge', ''],
            'single word'      => ['Shucked', 'Shucked', '', ''],
            'domain'           => ['itzenzo.tv', 'itzenzo.tv', '', ''],
            'year suffix'      => ['Wicked The Musical (2018)', 'Wicked The', 'Musical', '2018'],
            'extra whitespace' => ["  The   Band's Visit ", "The Band's", 'Visit', ''],
        ];
    }
}
