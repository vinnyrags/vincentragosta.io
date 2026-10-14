<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Blog;

/**
 * A transmission's place in the chronological run: its signal number (the
 * oldest is № 1) and its previous / next neighbors.
 *
 * Pure — takes the ordered post IDs, so it is testable without a database.
 */
final class NousSequence
{
    /**
     * @param int[] $ids Published post IDs, oldest first.
     * @return array{number: int, previous: int|null, next: int|null}|null Null when $id is not in the run.
     */
    public static function locate(array $ids, int $id): ?array
    {
        $ids = array_values(array_map('intval', $ids));
        $index = array_search($id, $ids, true);

        if ($index === false) {
            return null;
        }

        return [
            'number'   => $index + 1,
            'previous' => $ids[$index - 1] ?? null,
            'next'     => $ids[$index + 1] ?? null,
        ];
    }
}
