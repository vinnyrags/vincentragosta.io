<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme;

/**
 * Site search: the searchable types, the type filter, and the summary line.
 *
 * Pure — takes counts already queried, so it is testable without a database.
 */
final class SearchResults
{
    /**
     * Searchable post types, in pill order, with their plural labels.
     * Anything else (the shop's products and cards, partials) is never a
     * search filter, whatever the query string asks for.
     */
    public const TYPES = [
        'project' => 'Projects',
        'post'    => 'Nous Signal',
        'page'    => 'Pages',
    ];

    /** Singular / plural nouns for the summary line. */
    private const NOUNS = [
        'project' => ['project', 'projects'],
        'post'    => ['signal', 'signals'],
        'page'    => ['page', 'pages'],
    ];

    /**
     * The requested type filter, or '' (all types) when it isn't searchable.
     */
    public static function activeType(string $requested): string
    {
        return array_key_exists($requested, self::TYPES) ? $requested : '';
    }

    /**
     * Filter pills: "All", then each type that has results (or is active).
     *
     * @param array<string, int> $counts Results per post type, unfiltered.
     * @return array<int, array{type: string, label: string, count: int, active: bool}>
     */
    public static function pills(array $counts, string $active): array
    {
        $pills = [['type' => '', 'label' => 'All', 'count' => array_sum(self::known($counts)), 'active' => $active === '']];

        foreach (self::TYPES as $type => $label) {
            $count = $counts[$type] ?? 0;

            if ($count > 0 || $type === $active) {
                $pills[] = ['type' => $type, 'label' => $label, 'count' => $count, 'active' => $type === $active];
            }
        }

        return $pills;
    }

    /**
     * "24 results · 9 projects · 12 signals · 3 pages" — types without
     * results are left out.
     *
     * @param array<string, int> $counts
     */
    public static function summary(array $counts): string
    {
        $known = self::known($counts);
        $total = array_sum($known);
        $parts = [$total . ' ' . ($total === 1 ? 'result' : 'results')];

        foreach (self::NOUNS as $type => [$one, $many]) {
            $count = $known[$type] ?? 0;
            if ($count > 0) {
                $parts[] = $count . ' ' . ($count === 1 ? $one : $many);
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Counts for searchable types only.
     *
     * @param array<string, int> $counts
     * @return array<string, int>
     */
    private static function known(array $counts): array
    {
        return array_map('intval', array_intersect_key($counts, self::TYPES));
    }
}
