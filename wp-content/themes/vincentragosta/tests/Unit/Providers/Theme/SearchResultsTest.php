<?php

namespace ChildTheme\Tests\Unit\Providers\Theme;

use ChildTheme\Providers\Theme\SearchResults;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the search type filter and summary.
 */
class SearchResultsTest extends TestCase
{
    public function testActiveTypeAcceptsOnlySearchableTypes(): void
    {
        $this->assertSame('project', SearchResults::activeType('project'));
        $this->assertSame('', SearchResults::activeType(''));
        $this->assertSame('', SearchResults::activeType('product'), 'Shop types are never a filter');
        $this->assertSame('', SearchResults::activeType('content-partial'));
    }

    public function testPillsListAllThenTypesWithResultsInOrder(): void
    {
        $pills = SearchResults::pills(['page' => 3, 'post' => 12, 'project' => 9], '');

        $this->assertSame(['', 'project', 'post', 'page'], array_column($pills, 'type'));
        $this->assertSame([24, 9, 12, 3], array_column($pills, 'count'));
        $this->assertSame([true, false, false, false], array_column($pills, 'active'));
    }

    public function testPillsSkipEmptyTypesUnlessActive(): void
    {
        $this->assertSame(['', 'post'], array_column(SearchResults::pills(['post' => 2], ''), 'type'));
        $this->assertSame(['', 'project', 'post'], array_column(SearchResults::pills(['post' => 2], 'project'), 'type'));
    }

    public function testPillsIgnoreUnsearchableTypesInTheTotal(): void
    {
        $this->assertSame(2, SearchResults::pills(['post' => 2, 'product' => 40], '')[0]['count']);
    }

    public function testSummaryNamesEachTypeWithResults(): void
    {
        $this->assertSame('24 results · 9 projects · 12 signals · 3 pages', SearchResults::summary(['page' => 3, 'post' => 12, 'project' => 9]));
        $this->assertSame('1 result · 1 signal', SearchResults::summary(['post' => 1]));
        $this->assertSame('0 results', SearchResults::summary([]));
    }

    public function testExcerptGetsSmartQuotesIncludingAfterAHighlight(): void
    {
        $this->assertSame('the <strong>ADL</strong>&#8217;s summit', SearchResults::excerpt("the <strong>ADL</strong>'s summit"));
        $this->assertSame('North America&#8217;s oldest &#8212; <mark>show</mark>', SearchResults::excerpt("North America's oldest --- <mark>show</mark>"));
    }
}
