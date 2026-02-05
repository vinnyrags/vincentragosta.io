<?php

namespace ParentTheme\Providers\Theme\Features;

use ParentTheme\Providers\Contracts\Registrable;
use WP_Block_Type_Registry;

/**
 * Disables specified Gutenberg core blocks.
 *
 * Child themes can filter the disabled blocks using:
 * - 'theme/disabled_block_types' - Filter block types to disable
 * - 'theme/disabled_embed_variations' - Filter embed variations to disable
 *
 * @example
 * // In child theme - remove a block from the disabled list (re-enable it)
 * add_filter('theme/disabled_block_types', function($blocks) {
 *     return array_diff($blocks, ['core/cover']);
 * });
 *
 * @example
 * // In child theme - add additional blocks to disable
 * add_filter('theme/disabled_block_types', function($blocks) {
 *     $blocks[] = 'core/pullquote';
 *     return $blocks;
 * });
 */
class DisableBlocks implements Registrable
{
    /**
     * Default block types to disable.
     *
     * @var array<string>
     */
    protected array $defaultDisabledBlocks = [
        // Template/Site blocks
        'core/template-part',
        'core/post-content',
        'core/navigation',
        'core/navigation-link',
        'core/navigation-submenu',
        'core/site-logo',
        'core/site-title',
        'core/site-tagline',

        // Query/Loop blocks
        'core/query',
        'core/query-title',
        'core/query-pagination',
        'core/query-pagination-next',
        'core/query-pagination-numbers',
        'core/query-pagination-previous',
        'core/query-no-results',
        'core/post-template',

        // Post blocks
        'core/avatar',
        'core/post-title',
        'core/post-excerpt',
        'core/post-featured-image',
        'core/post-author',
        'core/post-author-name',
        'core/post-author-biography',
        'core/post-date',
        'core/post-terms',
        'core/post-navigation-link',
        'core/read-more',

        // Comment blocks
        'core/comments',
        'core/comment-author-name',
        'core/comment-content',
        'core/comment-date',
        'core/comment-edit-link',
        'core/comment-reply-link',
        'core/comment-template',
        'core/comments-title',
        'core/comments-pagination',
        'core/comments-pagination-next',
        'core/comments-pagination-numbers',
        'core/comments-pagination-previous',
        'core/post-comments-form',

        // Widget blocks
        'core/loginout',
        'core/term-description',
        'core/archives',
        'core/calendar',
        'core/categories',
        'core/latest-comments',
        'core/latest-posts',
        'core/page-list',
        'core/page-list-item',
        'core/rss',
        'core/search',
        'core/tag-cloud',

        // Layout blocks
        'core/spacer',
        'core/nextpage',
        'core/more',

        // Content blocks
        'core/media-text',
        'core/verse',
        'core/details',
        'core/quote',
        'core/freeform',
    ];

    /**
     * Default embed variations to disable.
     *
     * @var array<string>
     */
    protected array $defaultDisabledEmbedVariations = [
        'twitter',
        'wordpress',
        'soundcloud',
        'spotify',
        'flickr',
        'vimeo',
        'animoto',
        'cloudup',
        'crowdsignal',
        'dailymotion',
        'imgur',
        'issuu',
        'kickstarter',
        'mixcloud',
        'pocket-casts',
        'reddit',
        'reverbnation',
        'screencast',
        'scribd',
        'slideshare',
        'smugmug',
        'speaker-deck',
        'tiktok',
        'ted',
        'tumblr',
        'videopress',
        'wordpress-tv',
        'amazon-kindle',
        'pinterest',
        'wolfram-cloud',
        'bluesky',
        'facebook',
        'instagram',
    ];

    public function register(): void
    {
        add_action('init', [$this, 'unregisterBlocks'], 100);
        add_filter('allowed_block_types_all', [$this, 'filterAllowedBlocks'], 10, 2);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    /**
     * Get the filtered list of disabled block types.
     *
     * @return array<string>
     */
    public function getDisabledBlocks(): array
    {
        return apply_filters('theme/disabled_block_types', $this->defaultDisabledBlocks);
    }

    /**
     * Get the filtered list of disabled embed variations.
     *
     * @return array<string>
     */
    public function getDisabledEmbedVariations(): array
    {
        return apply_filters('theme/disabled_embed_variations', $this->defaultDisabledEmbedVariations);
    }

    /**
     * Unregister disabled blocks.
     */
    public function unregisterBlocks(): void
    {
        $registry = WP_Block_Type_Registry::get_instance();
        $disabledBlocks = $this->getDisabledBlocks();

        foreach ($disabledBlocks as $blockName) {
            if ($registry->is_registered($blockName)) {
                $registry->unregister($blockName);
            }
        }
    }

    /**
     * Filter allowed block types to remove disabled blocks.
     *
     * @param array|bool $allowedBlocks Array of allowed blocks or true for all.
     * @param \WP_Block_Editor_Context $context The editor context.
     * @return array|bool Filtered allowed blocks.
     */
    public function filterAllowedBlocks($allowedBlocks, $context)
    {
        $disabledBlocks = $this->getDisabledBlocks();

        if (is_array($allowedBlocks)) {
            return array_values(array_diff($allowedBlocks, $disabledBlocks));
        }

        if ($allowedBlocks === true) {
            $registry = WP_Block_Type_Registry::get_instance();
            $allBlocks = array_keys($registry->get_all_registered());

            return array_values(array_diff($allBlocks, $disabledBlocks));
        }

        return $allowedBlocks;
    }

    /**
     * Enqueue editor assets to disable blocks via JavaScript.
     */
    public function enqueueEditorAssets(): void
    {
        $script_path = get_template_directory() . '/dist/js/theme/disable-blocks.js';
        $script_url = get_template_directory_uri() . '/dist/js/theme/disable-blocks.js';

        if (!file_exists($script_path)) {
            return;
        }

        wp_enqueue_script(
            'parent-theme-disable-blocks',
            $script_url,
            ['wp-blocks', 'wp-dom-ready'],
            filemtime($script_path),
            true
        );

        // Pass filtered arrays to JavaScript
        wp_localize_script('parent-theme-disable-blocks', 'themeDisabledBlocks', [
            'blockTypes' => array_values($this->getDisabledBlocks()),
            'embedVariations' => array_values($this->getDisabledEmbedVariations()),
        ]);
    }
}
