<?php

namespace ChildTheme\Tests\Unit\Providers\Blog;

use ChildTheme\Providers\Blog\NousCarrier;
use ChildTheme\Tests\Mocks\MockNousPost;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Nous Signal carrier wave view model.
 */
class NousCarrierTest extends TestCase
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
     * Three transmissions over three days, newest first: 1, 2 and 3 min reads.
     *
     * @return MockNousPost[]
     */
    private function fixture(): array
    {
        return [
            $this->post(3, '2026-03-03', 690, 'Guaranteed by the Seller &amp; More'),
            $this->post(2, '2026-03-02', 460),
            $this->post(1, '2026-03-01', 230),
        ];
    }

    private function carrier(array $posts, string $today): NousCarrier
    {
        return new NousCarrier($posts, new DateTimeImmutable($today . ' 15:30:00'));
    }

    public function testEmptyArchiveHasNoCarrier(): void
    {
        $this->assertNull($this->carrier([], '2026-03-03')->toArray());
    }

    public function testBarsRunOldestFirstAndScaleHeightBetweenShortestAndLongestRead(): void
    {
        $bars = $this->carrier($this->fixture(), '2026-03-03')->bars();

        $this->assertSame(['2026.03.01', '2026.03.02', '2026.03.03'], array_column($bars, 'date'));
        $this->assertSame([1, 2, 3], array_column($bars, 'minutes'));
        $this->assertSame([30.0, 61.0, 92.0], array_column($bars, 'height'));
        $this->assertSame([70.0, 39.0, 8.0], array_column($bars, 'y'), 'Bars stand on the bottom edge');
    }

    public function testBarsShareTheWidthOneSlotPerDayCenteredInTheirSlot(): void
    {
        $bars = $this->carrier($this->fixture(), '2026-03-04')->bars();

        // Four days on the axis → 300-unit slots; bars fill 64%, inset 18% each side.
        $this->assertSame([54.0, 354.0, 654.0], array_column($bars, 'x'));
        $this->assertSame(192.0, $bars[0]['width']);
    }

    public function testEqualReadsGetTheMidHeight(): void
    {
        $bars = $this->carrier([$this->post(2, '2026-03-02'), $this->post(1, '2026-03-01')], '2026-03-02')->bars();

        $this->assertSame([61.0, 61.0], array_column($bars, 'height'));
    }

    public function testBarCarriesDecodedTitleAndLink(): void
    {
        $bar = $this->carrier($this->fixture(), '2026-03-03')->bars()[2];

        $this->assertSame('Guaranteed by the Seller & More', $bar['title']);
        $this->assertSame('https://example.com/s3/', $bar['link']);
    }

    public function testFeedThatPostedTodayIsReceivingWithNoFlatline(): void
    {
        $carrier = $this->carrier($this->fixture(), '2026-03-03')->toArray();

        $this->assertSame(0, $carrier['silent']);
        $this->assertTrue($carrier['live']);
        $this->assertNull($carrier['flatline']);
        $this->assertSame('Receiving', $carrier['label']);
    }

    public function testOneMissedDayStillCountsAsReceiving(): void
    {
        $this->assertTrue($this->carrier($this->fixture(), '2026-03-04')->toArray()['live']);
    }

    public function testSilentFeedIsDormantWithAFlatlineFromTheLastSlotToToday(): void
    {
        $carrier = $this->carrier($this->fixture(), '2026-03-12')->toArray();

        $this->assertSame(9, $carrier['silent']);
        $this->assertFalse($carrier['live']);
        $this->assertSame('No carrier · 9 days', $carrier['label']);
        // Twelve days on the axis (100 units each); the newest occupies day 2, so silence starts at 300.
        $this->assertSame(300.0, $carrier['flatline']['x']);
    }

    public function testTicksMarkEachMonthAfterTheFirstTransmissionAsAPercentage(): void
    {
        $posts = [$this->post(2, '2026-05-01'), $this->post(1, '2026-03-01')];
        $ticks = $this->carrier($posts, '2026-05-31')->ticks();

        // 92 days, Mar 1 → May 31: April 1 is day 31, May 1 is day 61.
        $this->assertSame(['Apr', 'May'], array_column($ticks, 'label'));
        $this->assertSame([33.696, 66.304], array_column($ticks, 'x'));
    }

    public function testReadoutReportsTotalRangeAverageAndStatus(): void
    {
        $readout = array_column($this->carrier($this->fixture(), '2026-03-12')->toArray()['readout'], null, 'label');

        $this->assertSame('3', $readout['Transmissions']['value']);
        $this->assertSame('03.01 — 03.03', $readout['Range']['value']);
        $this->assertSame('~2 min', $readout['Avg read']['value']);
        $this->assertSame('Dormant · 9 days', $readout['Status']['value']);
        $this->assertTrue($readout['Status']['accent']);
    }

    public function testReadoutRangeNamesYearsAcrossAYearBoundary(): void
    {
        $posts = [$this->post(2, '2027-01-02'), $this->post(1, '2026-12-30')];
        $readout = array_column($this->carrier($posts, '2027-01-02')->toArray()['readout'], 'value', 'label');

        $this->assertSame('2026.12.30 — 2027.01.02', $readout['Range']);
        $this->assertSame('Receiving', $readout['Status']);
    }

    public function testAPostDatedAfterTodayExtendsTheAxisInsteadOfGoingNegative(): void
    {
        $carrier = $this->carrier($this->fixture(), '2026-03-02');

        $this->assertSame(0, $carrier->silentDays());
        // The axis ends on the newest post (three days, 400-unit slots), not today.
        $this->assertSame(872.0, $carrier->bars()[2]['x']);
    }
}
