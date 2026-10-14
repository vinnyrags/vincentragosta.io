<?php

namespace ChildTheme\Tests\Unit\Providers\Blog;

use ChildTheme\Providers\Blog\NousArchive;
use ChildTheme\Tests\Mocks\MockNousPost;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Nous Signal archive view model.
 */
class NousArchiveTest extends TestCase
{
    private function post(int $id, string $date, int $words = 230, string $title = ''): MockNousPost
    {
        return MockNousPost::create([
            'ID' => $id,
            'post_name' => "s{$id}",
            'post_title' => $title ?: "Signal {$id}",
            'post_date' => $date . ' 06:00:00',
            'post_content' => trim(str_repeat('word ', $words)),
        ]);
    }

    /**
     * Seven posts over six months, newest first.
     *
     * @return MockNousPost[]
     */
    private function fixture(): array
    {
        return [
            $this->post(7, '2026-08-17', 1840, 'Guaranteed By The Seller')->setMockTags([['nvidia', 'Nvidia'], ['openai', 'OpenAI'], ['data-centers', 'Data Centers'], ['debt', 'Debt']]),
            $this->post(6, '2026-08-16', 460),
            $this->post(5, '2026-07-31'),
            $this->post(4, '2026-06-30'),
            $this->post(3, '2026-05-31'),
            $this->post(2, '2026-04-30'),
            $this->post(1, '2026-03-29'),
        ];
    }

    public function testStatsReportTotalSpanAverageReadMonthsAndLatest(): void
    {
        $stats = array_column((new NousArchive($this->fixture()))->stats(), null, 'label');

        $this->assertSame('7', $stats['Transmissions']['value']);
        $this->assertSame('March – August 2026', $stats['Transmissions']['detail']);
        $this->assertSame('~2', $stats['Average read']['value'], '(8 + 2 + 1×5) / 7 rounds to 2');
        $this->assertSame('6', $stats['Months']['value']);
        $this->assertSame('08.17', $stats['Latest']['value']);
        $this->assertSame('August 17, 2026', $stats['Latest']['detail']);
    }

    public function testStatsSpanNamesBothYearsAcrossAYearBoundary(): void
    {
        $archive = new NousArchive([$this->post(2, '2027-01-02'), $this->post(1, '2026-12-30')]);

        $this->assertSame('December 2026 – January 2027', $archive->stats()[0]['detail']);
    }

    public function testReadingTimeIsWordsOverTwoThirtyRoundedUp(): void
    {
        $this->assertSame(8, $this->post(1, '2026-08-01', 1840)->readingTime());
        $this->assertSame(9, $this->post(1, '2026-08-01', 1841)->readingTime());
        $this->assertSame(1, $this->post(1, '2026-08-01', 0)->readingTime());
    }

    public function testPillsGiveTheNewestMonthsTheirOwnAndCollectTheRestAsEarlier(): void
    {
        $pills = (new NousArchive($this->fixture()))->pills();

        $this->assertSame(
            [
                ['key' => '2026-08', 'label' => 'August', 'count' => 2],
                ['key' => '2026-07', 'label' => 'July', 'count' => 1],
                ['key' => '2026-06', 'label' => 'June', 'count' => 1],
                ['key' => '2026-05', 'label' => 'May', 'count' => 1],
                ['key' => 'earlier', 'label' => 'Earlier', 'count' => 2],
            ],
            $pills
        );
    }

    public function testPillsOmitEarlierWhenEveryMonthHasAPill(): void
    {
        $pills = (new NousArchive(array_slice($this->fixture(), 0, 3)))->pills();

        $this->assertSame(['2026-08', '2026-07'], array_column($pills, 'key'));
    }

    public function testPillLabelsCarryTheYearOutsideTheNewestYear(): void
    {
        $pills = (new NousArchive([$this->post(2, '2027-01-02'), $this->post(1, '2026-12-30')]))->pills();

        $this->assertSame(['January', 'December 2026'], array_column($pills, 'label'));
    }

    public function testGroupsAreMonthsNewestFirstWithTheirPill(): void
    {
        $groups = (new NousArchive($this->fixture()))->groups();

        $this->assertSame(['2026-08', '2026-07', '2026-06', '2026-05', '2026-04', '2026-03'], array_column($groups, 'key'));
        $this->assertSame(['2026-08', '2026-07', '2026-06', '2026-05', 'earlier', 'earlier'], array_column($groups, 'filter'));
        $this->assertSame('August', $groups[0]['month']);
        $this->assertSame('2026', $groups[0]['year']);
        $this->assertSame(2, $groups[0]['count']);
    }

    public function testRowsAreNumberedChronologicallyWithTheOldestAsOne(): void
    {
        $groups = (new NousArchive($this->fixture()))->groups();
        $numbers = array_merge(...array_map(static fn(array $g): array => array_column($g['rows'], 'number'), $groups));

        $this->assertSame([7, 6, 5, 4, 3, 2, 1], $numbers);
    }

    public function testRowCarriesDateReadTimeAndLowercaseSearchText(): void
    {
        $row = (new NousArchive($this->fixture()))->groups()[0]['rows'][0];

        $this->assertSame('08.17', $row['date']);
        $this->assertSame('2026-08-17', $row['iso']);
        $this->assertSame('Guaranteed By The Seller', $row['title']);
        $this->assertSame(8, $row['minutes']);
        $this->assertSame('nvidia openai data-centers debt', $row['tags']);
        $this->assertStringStartsWith('guaranteed by the seller word', $row['search']);
    }

    public function testLatestIsTheNewestPostWithUpToThreeTagNames(): void
    {
        $latest = (new NousArchive($this->fixture()))->latest();

        $this->assertSame(7, $latest['number']);
        $this->assertSame('Guaranteed By The Seller', $latest['title']);
        $this->assertSame('August 17, 2026', $latest['date_long']);
        $this->assertSame(['Nvidia', 'OpenAI', 'Data Centers'], $latest['tag_names']);
    }

    public function testEmptyArchiveHasNoStatsLatestPillsOrGroups(): void
    {
        $archive = (new NousArchive([]))->toArray();

        $this->assertSame(0, $archive['total']);
        $this->assertSame([], $archive['stats']);
        $this->assertNull($archive['latest']);
        $this->assertSame([], $archive['pills']);
        $this->assertSame([], $archive['groups']);
    }
}
