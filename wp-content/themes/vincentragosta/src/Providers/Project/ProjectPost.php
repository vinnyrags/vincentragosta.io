<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project;

use ChildTheme\Support\TitleSplitter;
use IX\Providers\Project\ProjectPost as BaseProjectPost;

/**
 * Project post model.
 *
 * Extends the parent ProjectPost with typed accessors for the "Project" ACF
 * group (acf-json/group_project_fields.json). Projects have no block editor:
 * the detail page renders entirely from these fields.
 */
class ProjectPost extends BaseProjectPost
{
    public const FIELD_LEAD          = 'project_lead';
    public const FIELD_LEAD_NOTE     = 'project_lead_note';
    public const FIELD_SECTIONS      = 'project_sections';
    public const FIELD_CLIENT        = 'project_client';
    public const FIELD_ROLE          = 'project_role';
    public const FIELD_YEAR          = 'project_year';
    public const FIELD_LIVE_URL      = 'project_live_url';
    public const FIELD_TECHNOLOGIES  = 'project_technologies';
    public const FIELD_STACK_SUMMARY = 'project_stack_summary';
    public const FIELD_CASE_STUDY    = 'project_case_study';
    public const FIELD_STATUS_LABEL  = 'project_status_label';

    /**
     * Opening paragraph (formatted HTML from the WYSIWYG field).
     */
    public function lead(): string
    {
        return trim((string) $this->getField(self::FIELD_LEAD));
    }

    /**
     * Plain-text field, so it gets the smart quotes and dashes that ACF
     * applies to WYSIWYG fields but not to text fields.
     */
    public function leadNote(): string
    {
        return wptexturize(trim((string) $this->getField(self::FIELD_LEAD_NOTE)));
    }

    /**
     * Body sections, in order, each with a unique anchor id derived from its
     * label. Rows missing a label, heading, or body are skipped. The heading
     * and note are plain-text fields, so they get the smart quotes and dashes
     * the WYSIWYG body already has.
     *
     * @return array<int, array{id: string, label: string, heading: string, body: string, note: string}>
     */
    public function sections(): array
    {
        $rows = $this->getField(self::FIELD_SECTIONS);

        if (!is_array($rows)) {
            return [];
        }

        $sections = [];
        $usedIds  = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label   = trim((string) ($row['label'] ?? ''));
            $heading = trim((string) ($row['heading'] ?? ''));
            $body    = trim((string) ($row['body'] ?? ''));

            if ($label === '' || $heading === '' || $body === '') {
                continue;
            }

            $sections[] = [
                'id'      => self::uniqueId(sanitize_title($label) ?: 'section', $usedIds),
                'label'   => $label,
                'heading' => wptexturize($heading),
                'body'    => $body,
                'note'    => wptexturize(trim((string) ($row['note'] ?? ''))),
            ];
        }

        return $sections;
    }

    public function client(): string
    {
        return trim((string) $this->getField(self::FIELD_CLIENT));
    }

    public function role(): string
    {
        return trim((string) $this->getField(self::FIELD_ROLE));
    }

    /**
     * Year shipped, or null when unset or not a plausible year.
     */
    public function year(): ?int
    {
        $year = (int) $this->getField(self::FIELD_YEAR);

        return $year >= 1990 && $year <= 2100 ? $year : null;
    }

    /**
     * Get the year used for sorting: the project year, falling back to the
     * publish year.
     */
    public function sortYear(): string
    {
        $year = $this->year();

        return $year !== null ? (string) $year : $this->date('Y');
    }

    public function liveUrl(): string
    {
        return trim((string) $this->getField(self::FIELD_LIVE_URL));
    }

    /**
     * Host of the live site without "www." (e.g. "celebrityautobiography.com"),
     * or '' when there is no live URL.
     */
    public function liveHost(): string
    {
        $host = (string) parse_url($this->liveUrl(), PHP_URL_HOST);

        return (string) preg_replace('/^www\./i', '', $host);
    }

    /**
     * Title split for the display heading (see TitleSplitter).
     *
     * @return array{lead: string, accent: string, suffix: string}
     */
    public function titleParts(): array
    {
        return TitleSplitter::split(html_entity_decode((string) $this->title(), ENT_QUOTES));
    }

    /**
     * Technologies as a list, split on commas, trimmed, empties and
     * case-insensitive duplicates removed (first spelling wins).
     *
     * @return string[]
     */
    public function technologies(): array
    {
        return self::parseList((string) $this->getField(self::FIELD_TECHNOLOGIES));
    }

    /**
     * Short stack line for the projects index. Falls back to the first three
     * technologies when no summary is set.
     */
    public function stackSummary(): string
    {
        $summary = trim((string) $this->getField(self::FIELD_STACK_SUMMARY));

        if ($summary !== '') {
            return $summary;
        }

        return implode(' · ', array_slice($this->technologies(), 0, 3));
    }

    public function isCaseStudy(): bool
    {
        return (bool) $this->getField(self::FIELD_CASE_STUDY);
    }

    public function statusLabel(): string
    {
        return trim((string) $this->getField(self::FIELD_STATUS_LABEL));
    }

    /**
     * Return $base, or $base-2, $base-3 … if already taken; records the result.
     *
     * @param array<string, true> $used
     */
    private static function uniqueId(string $base, array &$used): string
    {
        $id = $base;

        for ($n = 2; isset($used[$id]); $n++) {
            $id = "{$base}-{$n}";
        }

        $used[$id] = true;

        return $id;
    }

    /**
     * Split a comma-separated string into a clean, de-duplicated list.
     *
     * @return string[]
     */
    public static function parseList(string $value): array
    {
        $items = [];

        foreach (explode(',', $value) as $item) {
            $item = trim(preg_replace('/\s+/', ' ', $item) ?? '');
            $key  = mb_strtolower($item);

            if ($item !== '' && !isset($items[$key])) {
                $items[$key] = $item;
            }
        }

        return array_values($items);
    }
}
