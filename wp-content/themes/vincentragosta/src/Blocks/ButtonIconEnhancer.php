<?php

namespace ChildTheme\Blocks;

use ChildTheme\Services\Icon;

/**
 * Enhances the core/button block with icon support on the frontend.
 */
class ButtonIconEnhancer
{
    /**
     * Register the block filter.
     */
    public function register(): void
    {
        add_filter('render_block_core/button', [$this, 'render'], 10, 2);
    }

    /**
     * Filter the button block output to add icons.
     */
    public function render(string $block_content, array $block): string
    {
        if (!$this->shouldEnhance($block)) {
            return $block_content;
        }

        $icon = new Icon($block['attrs']['selectedIcon']);
        if (!$icon->exists()) {
            return $block_content;
        }

        $position = $block['attrs']['iconPosition'] ?? 'left';
        $block_content = $this->addWrapperClass($block_content, $position);
        return $this->insertIcon($block_content, (string) $icon, $position);
    }

    /**
     * Check if this button should be enhanced with an icon.
     */
    private function shouldEnhance(array $block): bool
    {
        return isset($block['blockName'])
            && $block['blockName'] === 'core/button'
            && !empty($block['attrs']['selectedIcon']);
    }

    /**
     * Add icon-related classes to the button wrapper.
     */
    private function addWrapperClass(string $content, string $position): string
    {
        $class = ' has-icon icon-pos-' . esc_attr($position);

        if (strpos($content, 'class="') !== false) {
            return preg_replace(
                '/(<div\s+[^>]*class=")([^"]*wp-block-button[^"]*)/i',
                '$1$2' . $class . '"',
                $content,
                1
            );
        }

        return preg_replace(
            '/(<div\s+[^>]*wp-block-button)/i',
            '$1 class="' . trim($class) . '"',
            $content,
            1
        );
    }

    /**
     * Insert the icon into the button/link element.
     */
    private function insertIcon(string $content, string $svg, string $position): string
    {
        $pattern = '/(<(a|button)\s+[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>)(.*?)(<\/\2>)/is';

        return preg_replace_callback(
            $pattern,
            function ($matches) use ($svg, $position) {
                $opening = $matches[1];
                $text = $matches[3];
                $closing = $matches[4];
                $icon = '<span class="wp-block-button__icon" aria-hidden="true">' . $svg . '</span>';

                return $position === 'right'
                    ? $opening . $text . $icon . $closing
                    : $opening . $icon . $text . $closing;
            },
            $content,
            1
        );
    }
}
