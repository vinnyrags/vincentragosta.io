<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project\Hooks;

use ChildTheme\Providers\Project\ProjectPost;
use Mythus\Contracts\Hook;

/**
 * Makes Relevanssi index project text fields.
 *
 * Projects have no block editor — their copy lives in ACF fields (lead,
 * sections repeater, client, technologies…), not post_content. Without this,
 * site search would only match project titles.
 */
class ProjectSearchIndex implements Hook
{
    /** Top-level text fields to index. */
    public const TEXT_FIELDS = [
        ProjectPost::FIELD_LEAD,
        ProjectPost::FIELD_LEAD_NOTE,
        ProjectPost::FIELD_CLIENT,
        ProjectPost::FIELD_ROLE,
        ProjectPost::FIELD_TECHNOLOGIES,
        ProjectPost::FIELD_STACK_SUMMARY,
    ];

    /** Sections repeater sub fields to index, per row. */
    public const SECTION_SUB_FIELDS = ['label', 'heading', 'body', 'note'];

    public function register(): void
    {
        add_filter('relevanssi_index_custom_fields', [$this, 'addProjectFields'], 10, 2);
    }

    /**
     * @param array<int, string>|mixed $fields Custom field names Relevanssi will index.
     * @param int|string $postId
     * @return array<int, string>|mixed
     */
    public function addProjectFields(mixed $fields, int|string $postId): mixed
    {
        if (!is_array($fields) || get_post_type((int) $postId) !== ProjectPost::POST_TYPE) {
            return $fields;
        }

        $rows = (int) get_post_meta((int) $postId, ProjectPost::FIELD_SECTIONS, true);

        return array_values(array_unique(array_merge($fields, self::metaKeys($rows))));
    }

    /**
     * Meta keys to index for a project with $sectionRows repeater rows
     * (ACF stores row data as project_sections_{i}_{sub_field}).
     *
     * @return array<int, string>
     */
    public static function metaKeys(int $sectionRows): array
    {
        $keys = self::TEXT_FIELDS;

        for ($i = 0; $i < max(0, $sectionRows); $i++) {
            foreach (self::SECTION_SUB_FIELDS as $sub) {
                $keys[] = ProjectPost::FIELD_SECTIONS . "_{$i}_{$sub}";
            }
        }

        return $keys;
    }
}
