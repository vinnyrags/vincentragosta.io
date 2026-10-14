<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Blog;

/**
 * View model for the Nous Signal Log block.
 */
final class NousLog
{
    /** How many transmissions the log shows. */
    public const ROWS = 5;

    /**
     * Number the latest posts as transmissions. $posts are newest first; the
     * newest is TX·{total} and is marked new. Numbers are zero-padded to two
     * digits (TX·05) and grow naturally past 99 (TX·167).
     *
     * @param array<int, array{title: string, link: string}> $posts
     * @return array<int, array{number: string, title: string, link: string, new: bool}>
     */
    public static function rows(array $posts, int $total): array
    {
        $total = max($total, count($posts));
        $rows  = [];

        foreach (array_values($posts) as $i => $post) {
            $rows[] = [
                'number' => str_pad((string) ($total - $i), 2, '0', STR_PAD_LEFT),
                'title'  => $post['title'],
                'link'   => $post['link'],
                'new'    => $i === 0,
            ];
        }

        return $rows;
    }
}
