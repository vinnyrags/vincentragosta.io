<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme;

/**
 * Builds the contents list for the TOC block from a page's blocks.
 *
 * Every H2 with an HTML anchor becomes an entry. Its label is the eyebrow
 * paragraph directly above it (with a leading "01 / " numbering stripped), or
 * the heading text when there is no eyebrow — so editors drive the contents
 * entirely through the headings they write.
 */
final class TableOfContents
{
    /**
     * @param array<int, array<string, mixed>> $blocks Output of parse_blocks().
     * @return array<int, array{id: string, label: string}>
     */
    public static function fromBlocks(array $blocks): array
    {
        $entries = [];
        self::collect($blocks, $entries);

        return $entries;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<int, array{id: string, label: string}> $entries
     */
    private static function collect(array $blocks, array &$entries): void
    {
        $previous = null;

        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                continue;
            }

            if ($block['blockName'] === 'core/heading' && (int) ($block['attrs']['level'] ?? 2) === 2) {
                $html = (string) ($block['innerHTML'] ?? '');
                $id = self::anchor($html);

                if ($id !== null) {
                    $label = self::eyebrowLabel($previous) ?? self::text($html);
                    if ($label !== '') {
                        $entries[] = ['id' => $id, 'label' => $label];
                    }
                }
            }

            if (!empty($block['innerBlocks'])) {
                self::collect($block['innerBlocks'], $entries);
            }

            $previous = $block;
        }
    }

    private static function anchor(string $html): ?string
    {
        if (preg_match('/<h2\b[^>]*\sid="([^"]+)"/i', $html, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * @param array<string, mixed>|null $block
     */
    private static function eyebrowLabel(?array $block): ?string
    {
        if (
            $block === null
            || $block['blockName'] !== 'core/paragraph'
            || !str_contains((string) ($block['attrs']['className'] ?? ''), 'is-style-eyebrow')
        ) {
            return null;
        }

        $label = preg_replace('/^\s*\d+\s*\/\s*/', '', self::text((string) ($block['innerHTML'] ?? '')));

        return $label === '' ? null : $label;
    }

    private static function text(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }
}
