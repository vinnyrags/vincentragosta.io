<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use IX\Services\IconServiceFactory;
use Mythus\Contracts\Hook;

/**
 * Replaces WordPress core social-link block SVGs with theme icons from IX.
 *
 * Falls back to the core SVG if no matching theme icon exists.
 * Checks both the parent theme (IX) and child theme icon directories.
 */
class SocialIconOverride implements Hook
{
    public function __construct(
        private readonly IconServiceFactory $iconFactory,
    ) {}

    public function register(): void
    {
        add_filter('render_block_core/social-link', [$this, 'replaceSocialIcon'], 10, 2);
    }

    /**
     * Replace the core SVG with the theme's icon and show the label visibly.
     *
     * @param string $blockContent The rendered block HTML.
     * @param array  $block        The parsed block data.
     */
    public function replaceSocialIcon(string $blockContent, array $block): string
    {
        $service = $block['attrs']['service'] ?? '';
        $label = $block['attrs']['label'] ?? '';

        if (!$service) {
            return $blockContent;
        }

        $iconName = 'social/' . $service;

        try {
            $icon = $this->iconFactory->create($iconName);
            $svg = $icon->render();
        } catch (\Throwable $e) {
            $svg = '';
        }

        // Replace the existing SVG with the theme icon if available
        if ($svg) {
            $blockContent = preg_replace(
                '/<svg[^>]*>.*?<\/svg>/s',
                $svg,
                $blockContent,
                1
            );
        }

        // Show the label as visible text alongside the icon
        if ($label) {
            $blockContent = str_replace(
                'class="wp-block-social-link-label screen-reader-text"',
                'class="wp-block-social-link-label"',
                $blockContent
            );
        }

        return $blockContent;
    }
}
