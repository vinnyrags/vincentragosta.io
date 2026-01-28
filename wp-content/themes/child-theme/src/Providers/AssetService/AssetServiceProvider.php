<?php

namespace ChildTheme\Providers\AssetService;

use ParentTheme\Providers\AssetService\AssetServiceProvider as BaseAssetServiceProvider;

/**
 * Handles all asset enqueueing for frontend and editor.
 */
class AssetServiceProvider extends BaseAssetServiceProvider
{
    /**
     * Theme handle prefix for asset registration.
     *
     * @var string
     */
    protected string $handlePrefix = 'child-theme';

    public function register(): void
    {
        parent::register();

        add_action('wp_head', [$this, 'addFontPreconnects']);
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueueFrontendAssets(): void
    {
        // Enqueue Google Fonts
        wp_enqueue_style(
            'fira-code-font',
            'https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600;700&display=swap',
            [],
            null
        );

        // Call parent to enqueue theme styles and scripts
        parent::enqueueFrontendAssets();
    }

    /**
     * Add preconnect links for Google Fonts.
     */
    public function addFontPreconnects(): void
    {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }
}
