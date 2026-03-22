<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;
use IX\Services\IconServiceFactory;

/**
 * Populates the ACF social icon field with choices from the filesystem.
 *
 * Hooks into the acf/load_field filter for the social icon select field
 * and dynamically builds the choices array from SVG files in the
 * svg/icons/social/ directory.
 */
class SocialIconChoices implements Hook
{
    public function __construct(
        private readonly IconServiceFactory $iconFactory,
    ) {}

    public function register(): void
    {
        add_filter('acf/load_field/key=field_social_icon', [$this, 'populateChoices']);
    }

    /**
     * Populate the social icon field with choices from the icons/social/ directory.
     *
     * @param array<string, mixed> $field ACF field configuration.
     * @return array<string, mixed> Modified field with choices populated.
     */
    public function populateChoices(array $field): array
    {
        $icons = $this->iconFactory->all('icon', 'social');

        $choices = [];
        foreach ($icons as $icon) {
            $choices[$icon['name']] = $icon['label'];
        }

        $field['choices'] = $choices;

        return $field;
    }
}
