<?php

declare(strict_types=1);

namespace ChildTheme\Support;

/**
 * Splits a title for a display heading: the last word is set as a serif
 * accent ("Celebrity *Autobiography*"); a trailing "(YYYY)" becomes a suffix
 * ("Wicked The Musical" + "2018"). Single-word titles have no accent.
 *
 * Used by project and Nous post headers.
 */
final class TitleSplitter
{
    /**
     * @return array{lead: string, accent: string, suffix: string}
     */
    public static function split(string $title): array
    {
        $title  = trim(preg_replace('/\s+/', ' ', $title) ?? '');
        $suffix = '';

        if (preg_match('/^(.*\S)\s*\((\d{4})\)$/', $title, $m)) {
            [$title, $suffix] = [$m[1], $m[2]];
        }

        $words = explode(' ', $title);

        if (count($words) < 2) {
            return ['lead' => $title, 'accent' => '', 'suffix' => $suffix];
        }

        $accent = array_pop($words);

        return ['lead' => implode(' ', $words), 'accent' => $accent, 'suffix' => $suffix];
    }
}
