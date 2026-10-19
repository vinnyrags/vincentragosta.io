<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Blog;

use DateTimeImmutable;

/**
 * Builds the view model for the Nous Signal carrier wave (the archive hero):
 * one bar per transmission on a day axis that runs to today, so a silent
 * feed reads as a flat line, plus the readout strip beneath it.
 *
 * Pure — takes already-queried posts and today's date, so it is testable
 * without a database. Geometry is in viewBox units (WIDTH × HEIGHT); labels
 * carry their position as a percentage of the width so they can render as
 * HTML at any size.
 */
class NousCarrier
{
    /** viewBox width. */
    public const WIDTH = 1200;

    /** viewBox height; bars stand on the bottom edge. */
    public const HEIGHT = 100;

    /** Bar height for the shortest and longest reads, in viewBox units. */
    public const MIN_BAR = 30;
    public const MAX_BAR = 92;

    /** Share of a day's slot the bar fills. */
    public const BAR_FILL = 0.64;

    /** Days without a transmission before the carrier counts as lost. */
    public const SILENT_AFTER = 2;

    /**
     * @param NousPost[] $posts Every published transmission, newest first.
     */
    public function __construct(
        private readonly array $posts,
        private readonly DateTimeImmutable $today,
    ) {
    }

    /**
     * @return array{
     *     bars: array<int, array<string, mixed>>,
     *     ticks: array<int, array{label: string, x: float}>,
     *     flatline: array{x: float}|null,
     *     silent: int,
     *     live: bool,
     *     label: string,
     *     readout: array<int, array{label: string, value: string, accent: bool}>
     * }|null Null when there are no transmissions.
     */
    public function toArray(): ?array
    {
        if (!$this->posts) {
            return null;
        }

        $silent = $this->silentDays();
        $live = $silent < self::SILENT_AFTER;

        return [
            'bars'     => $this->bars(),
            'ticks'    => $this->ticks(),
            'flatline' => $live ? null : ['x' => $this->x($this->span() - $silent)],
            'silent'   => $silent,
            'live'     => $live,
            'label'    => $this->label($silent, $live),
            'readout'  => $this->readout($silent, $live),
        ];
    }

    /**
     * One bar per transmission, oldest first. Height scales read time
     * between the archive's shortest and longest reads.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bars(): array
    {
        $minutes = array_map(static fn(NousPost $post): int => $post->readingTime(), $this->posts);
        $low = min($minutes);
        $range = max($minutes) - $low;
        $slot = self::WIDTH / $this->span();
        $bars = [];

        foreach (array_reverse($this->posts) as $post) {
            $read = $post->readingTime();
            $height = $range > 0
                ? self::MIN_BAR + ($read - $low) / $range * (self::MAX_BAR - self::MIN_BAR)
                : (self::MIN_BAR + self::MAX_BAR) / 2;

            $bars[] = [
                'x'       => round($this->x($this->dayIndex($post)) + $slot * (1 - self::BAR_FILL) / 2, 2),
                'y'       => round(self::HEIGHT - $height, 2),
                'width'   => round($slot * self::BAR_FILL, 2),
                'height'  => round($height, 2),
                'date'    => (new DateTimeImmutable($post->publishedOn()))->format('Y.m.d'),
                'title'   => html_entity_decode((string) $post->title(), ENT_QUOTES),
                'minutes' => $read,
                'link'    => $post->link(),
            ];
        }

        return $bars;
    }

    /**
     * The first of each month after the first transmission, up to today.
     *
     * @return array<int, array{label: string, x: float}>
     */
    public function ticks(): array
    {
        $start = $this->start();
        $month = $start->modify('first day of next month');
        $ticks = [];

        while ($month <= $this->end()) {
            $ticks[] = [
                'label' => $month->format('M'),
                'x'     => round($this->x((int) $start->diff($month)->days) / self::WIDTH * 100, 3),
            ];
            $month = $month->modify('+1 month');
        }

        return $ticks;
    }

    /**
     * Whole days since the newest transmission (0 when it went out today).
     */
    public function silentDays(): int
    {
        $newest = new DateTimeImmutable($this->posts[0]->publishedOn());

        return $newest >= $this->today() ? 0 : (int) $newest->diff($this->today())->days;
    }

    /**
     * @return array<int, array{label: string, value: string, accent: bool}>
     */
    private function readout(int $silent, bool $live): array
    {
        $oldest = new DateTimeImmutable($this->posts[count($this->posts) - 1]->publishedOn());
        $newest = new DateTimeImmutable($this->posts[0]->publishedOn());
        $format = $oldest->format('Y') === $newest->format('Y') ? 'm.d' : 'Y.m.d';

        return [
            ['label' => 'Transmissions', 'value' => (string) count($this->posts), 'accent' => false],
            ['label' => 'Range', 'value' => $oldest->format($format) . ' — ' . $newest->format($format), 'accent' => false],
            ['label' => 'Avg read', 'value' => '~' . (new NousArchive($this->posts))->averageReadingTime() . ' min', 'accent' => false],
            ['label' => 'Status', 'value' => $live ? 'Receiving' : 'Dormant · ' . $silent . ' days', 'accent' => true],
        ];
    }

    private function label(int $silent, bool $live): string
    {
        return $live ? 'Receiving' : 'No carrier · ' . $silent . ' days';
    }

    /**
     * Days on the axis: the first transmission through today, inclusive.
     */
    private function span(): int
    {
        return (int) $this->start()->diff($this->end())->days + 1;
    }

    private function dayIndex(NousPost $post): int
    {
        return (int) $this->start()->diff(new DateTimeImmutable($post->publishedOn()))->days;
    }

    /**
     * Left edge of a day's slot, in viewBox units.
     */
    private function x(int $day): float
    {
        return $day * self::WIDTH / $this->span();
    }

    private function start(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->posts[count($this->posts) - 1]->publishedOn());
    }

    /**
     * The axis ends today, or on the newest transmission if that is later
     * (a post scheduled in the site's timezone ahead of the server's).
     */
    private function end(): DateTimeImmutable
    {
        $newest = new DateTimeImmutable($this->posts[0]->publishedOn());

        return max($newest, $this->today());
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->today->format('Y-m-d'));
    }
}
