<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Unit\Providers\Theme;

use ChildTheme\Providers\Theme\TableOfContents;
use PHPUnit\Framework\TestCase;

class TableOfContentsTest extends TestCase
{
    private static function eyebrow(string $text): array
    {
        return [
            'blockName'   => 'core/paragraph',
            'attrs'       => ['className' => 'is-style-eyebrow'],
            'innerHTML'   => "<p class=\"is-style-eyebrow\">{$text}</p>",
            'innerBlocks' => [],
        ];
    }

    private static function heading(string $html, int $level = 2): array
    {
        return [
            'blockName'   => 'core/heading',
            'attrs'       => $level === 2 ? [] : ['level' => $level],
            'innerHTML'   => $html,
            'innerBlocks' => [],
        ];
    }

    private static function group(array $inner): array
    {
        return ['blockName' => 'core/group', 'attrs' => [], 'innerHTML' => '', 'innerBlocks' => $inner];
    }

    public function testLabelsComeFromTheEyebrowWithNumberingStripped(): void
    {
        $entries = TableOfContents::fromBlocks([
            self::group([
                self::eyebrow('01 / Overview'),
                self::heading('<h2 id="overview" class="wp-block-heading">Your privacy <em>matters</em>.</h2>'),
            ]),
            self::group([
                self::eyebrow('02 / Information collected'),
                self::heading('<h2 id="information" class="wp-block-heading">Only what you send.</h2>'),
            ]),
        ]);

        $this->assertSame([
            ['id' => 'overview', 'label' => 'Overview'],
            ['id' => 'information', 'label' => 'Information collected'],
        ], $entries);
    }

    public function testFallsBackToHeadingTextWithoutAnEyebrow(): void
    {
        $entries = TableOfContents::fromBlocks([
            self::heading('<h2 id="cookies" class="wp-block-heading">Minimal cookie <em>usage</em> &amp; more</h2>'),
        ]);

        $this->assertSame([['id' => 'cookies', 'label' => 'Minimal cookie usage & more']], $entries);
    }

    public function testSkipsHeadingsWithoutAnAnchorAndNonH2Levels(): void
    {
        $entries = TableOfContents::fromBlocks([
            self::eyebrow('01 / Overview'),
            self::heading('<h2 class="wp-block-heading">No anchor</h2>'),
            self::heading('<h3 id="sub" class="wp-block-heading">Subhead</h3>', 3),
        ]);

        $this->assertSame([], $entries);
    }

    public function testFindsHeadingsNestedInColumns(): void
    {
        $entries = TableOfContents::fromBlocks([
            self::group([self::group([self::group([
                self::eyebrow('03 / Changes'),
                self::heading('<h2 id="changes" class="wp-block-heading">When this changes.</h2>'),
            ])])]),
        ]);

        $this->assertSame([['id' => 'changes', 'label' => 'Changes']], $entries);
    }
}
