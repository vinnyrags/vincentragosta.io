<?php

namespace ChildTheme\Tests\Integration\Providers;

use ChildTheme\Providers\AssetServiceProvider;
use WorDBless\BaseTestCase;

/**
 * Integration tests for AssetServiceProvider.
 */
class AssetServiceProviderTest extends BaseTestCase
{
    private AssetServiceProvider $provider;

    public function set_up(): void
    {
        parent::set_up();
        $this->provider = new AssetServiceProvider();
    }

    /**
     * Test that provider can be instantiated.
     */
    public function testProviderCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AssetServiceProvider::class, $this->provider);
    }

    /**
     * Test that provider extends parent theme's AssetServiceProvider.
     */
    public function testProviderExtendsParentProvider(): void
    {
        $this->assertInstanceOf(\ParentTheme\Providers\AssetServiceProvider::class, $this->provider);
    }

    /**
     * Test that register method hooks into WordPress.
     */
    public function testRegisterAddsWordPressHooks(): void
    {
        $this->provider->register();

        $this->assertGreaterThan(
            0,
            has_action('wp_enqueue_scripts', [$this->provider, 'enqueueFrontendAssets'])
        );
        $this->assertGreaterThan(
            0,
            has_action('enqueue_block_editor_assets', [$this->provider, 'enqueueEditorAssets'])
        );
        $this->assertGreaterThan(
            0,
            has_action('enqueue_block_assets', [$this->provider, 'enqueueBlockAssets'])
        );
    }
}
