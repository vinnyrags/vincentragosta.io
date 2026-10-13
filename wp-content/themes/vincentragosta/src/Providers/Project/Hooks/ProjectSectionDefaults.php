<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Project\Hooks;

use Mythus\Contracts\Hook;

/**
 * Pre-fills the Sections repeater on new projects with the standard
 * Background / Implementation / Results rows (labels only), so every
 * case study starts from the same structure.
 */
class ProjectSectionDefaults implements Hook
{
    public const FIELD_KEY = 'field_project_sections';

    public const DEFAULT_LABELS = ['Background', 'Implementation', 'Results'];

    public function register(): void
    {
        add_filter('acf/load_value/key=' . self::FIELD_KEY, [$this, 'prefillNewProject'], 10, 2);
    }

    /**
     * @param mixed $value Stored repeater value.
     * @param int|string $postId ACF post ID.
     * @return mixed
     */
    public function prefillNewProject(mixed $value, int|string $postId): mixed
    {
        if (!empty($value) || !is_numeric($postId) || get_post_status((int) $postId) !== 'auto-draft') {
            return $value;
        }

        return array_map(
            static fn(string $label): array => [
                'field_project_section_label'   => $label,
                'field_project_section_heading' => '',
                'field_project_section_body'    => '',
                'field_project_section_note'    => '',
            ],
            self::DEFAULT_LABELS
        );
    }
}
