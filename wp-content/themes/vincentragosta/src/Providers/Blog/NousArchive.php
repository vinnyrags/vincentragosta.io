<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Blog;

use DateTimeImmutable;

/**
 * Builds the view model for the Nous Signal archive (the blog block in "all"
 * mode): the latest transmission, month filter pills, and the month-grouped
 * index. The hero's figures live in NousCarrier's readout.
 *
 * Pure — takes already-queried posts, so it is testable without a database.
 */
class NousArchive
{
    /** The newest months that get a pill of their own; older ones share "Earlier". */
    public const MONTH_PILLS = 4;

    /** Pill key for every month older than the newest MONTH_PILLS. */
    public const EARLIER = 'earlier';

    /** Excerpt length in the index, in words. */
    public const EXCERPT_WORDS = 32;

    /** Tags shown beside the latest transmission. */
    public const LATEST_TAGS = 3;

    /**
     * @param NousPost[] $posts Every published transmission, newest first.
     */
    public function __construct(
        private readonly array $posts,
    ) {
    }

    /**
     * @return array{
     *     total: int,
     *     latest: array<string, mixed>|null,
     *     pills: array<int, array{key: string, label: string, count: int}>,
     *     groups: array<int, array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'total'  => count($this->posts),
            'latest' => $this->latest(),
            'pills'  => $this->pills(),
            'groups' => $this->groups(),
        ];
    }

    /**
     * Mean reading time across every post, rounded to whole minutes.
     */
    public function averageReadingTime(): int
    {
        if (!$this->posts) {
            return 0;
        }

        $minutes = array_map(static fn(NousPost $post): int => $post->readingTime(), $this->posts);

        return (int) round(array_sum($minutes) / count($minutes));
    }

    /**
     * The newest transmission, with the facts shown beside it.
     *
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        if (!$this->posts) {
            return null;
        }

        $post = $this->posts[0];
        $tags = array_slice(
            array_map(static fn(object $term): string => html_entity_decode($term->name, ENT_QUOTES), $post->tags()),
            0,
            self::LATEST_TAGS
        );

        return $this->row($post, count($this->posts)) + [
            'date_long' => $this->date($post)->format('F j, Y'),
            'tag_names' => $tags,
        ];
    }

    /**
     * Filter pills: one per newest month, then "Earlier" for the rest. "All"
     * is rendered by the template from the total.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function pills(): array
    {
        $months = $this->months();
        $pills = [];
        $earlier = 0;
        $newestYear = $months ? substr(array_key_first($months), 0, 4) : '';

        foreach (array_keys($months) as $index => $key) {
            if ($index < self::MONTH_PILLS) {
                $pills[] = ['key' => $key, 'label' => $this->monthLabel($key, $newestYear), 'count' => count($months[$key])];
            } else {
                $earlier += count($months[$key]);
            }
        }

        if ($earlier > 0) {
            $pills[] = ['key' => self::EARLIER, 'label' => 'Earlier', 'count' => $earlier];
        }

        return $pills;
    }

    /**
     * Month groups, newest first. Each carries the pill it belongs to.
     *
     * @return array<int, array{key: string, filter: string, month: string, year: string, count: int, rows: array<int, array<string, mixed>>}>
     */
    public function groups(): array
    {
        $groups = [];
        $number = count($this->posts);
        $index = 0;

        foreach ($this->months() as $key => $posts) {
            $date = new DateTimeImmutable($key . '-01');
            $rows = [];

            foreach ($posts as $post) {
                $rows[] = $this->row($post, $number--);
            }

            $groups[] = [
                'key'    => $key,
                'filter' => $index++ < self::MONTH_PILLS ? $key : self::EARLIER,
                'month'  => $date->format('F'),
                'year'   => $date->format('Y'),
                'count'  => count($posts),
                'rows'   => $rows,
            ];
        }

        return $groups;
    }

    /**
     * Posts keyed by month (Y-m), newest month first, order preserved within.
     *
     * @return array<string, NousPost[]>
     */
    public function months(): array
    {
        $months = [];

        foreach ($this->posts as $post) {
            $months[substr($post->publishedOn(), 0, 7)][] = $post;
        }

        return $months;
    }

    /**
     * One index row. $number is the post's chronological position (the
     * oldest transmission is 1).
     *
     * @return array<string, mixed>
     */
    private function row(NousPost $post, int $number): array
    {
        $title = html_entity_decode((string) $post->title(), ENT_QUOTES);
        $excerpt = html_entity_decode($post->shortExcerpt(self::EXCERPT_WORDS), ENT_QUOTES);

        return [
            'number'  => $number,
            'date'    => $this->date($post)->format('m.d'),
            'iso'     => $post->publishedOn(),
            'title'   => $title,
            'link'    => $post->link(),
            'excerpt' => $excerpt,
            'minutes' => $post->readingTime(),
            'tags'    => $post->tagSlugs(),
            'search'  => mb_strtolower($title . ' ' . $excerpt),
        ];
    }

    private function date(NousPost $post): DateTimeImmutable
    {
        return new DateTimeImmutable($post->publishedOn());
    }

    /**
     * "August", or "December 2025" when the month is not in the newest year.
     */
    private function monthLabel(string $key, string $newestYear): string
    {
        $date = new DateTimeImmutable($key . '-01');

        return $date->format('Y') === $newestYear ? $date->format('F') : $date->format('F Y');
    }
}
