<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Features;

use ParentTheme\Services\IconServiceFactory;
use ParentTheme\Providers\Contracts\Registrable;

/**
 * Replaces the default +/− text in accordion toggle icons with an SVG arrow.
 *
 * Hooks into the accordion-heading block render to swap the text content
 * of the toggle-icon span with the icon-arrow SVG. CSS handles rotation
 * based on aria-expanded state.
 */
class AccordionIconEnhancer implements Registrable
{
    /**
     * Create the enhancer with its icon factory dependency.
     *
     * @param IconServiceFactory $iconFactory Factory for resolving icon SVG content.
     */
    public function __construct(
        private readonly IconServiceFactory $iconFactory,
    ) {}

    public function register(): void
    {
        add_filter('render_block_core/accordion-heading', [$this, 'render'], 10, 2);
    }

    /**
     * Filter the accordion heading output to replace toggle icon text with SVG.
     *
     * @param string $content Rendered block HTML.
     * @param array{blockName: string, attrs: array<string, mixed>} $block Parsed block data.
     */
    public function render(string $content, array $block): string
    {
        $icon = $this->iconFactory->create('chevron');
        if (!$icon->exists()) {
            return $content;
        }

        $svg = (string) $icon;

        // Replace the text content (+/−) inside the toggle-icon span with the SVG
        return preg_replace(
            '/(<span\b[^>]*class="[^"]*wp-block-accordion-heading__toggle-icon[^"]*"[^>]*>)[^<]*(<\/span>)/i',
            '$1' . $svg . '$2',
            $content
        );
    }
}
