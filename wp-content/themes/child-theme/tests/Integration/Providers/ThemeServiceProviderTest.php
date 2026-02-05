<?php

namespace ChildTheme\Tests\Integration\Providers;

use ChildTheme\Providers\ThemeService\ThemeServiceProvider;
use ChildTheme\Providers\ThemeService\Features\ButtonIconEnhancer;
use ChildTheme\Providers\ThemeService\Features\CoverBlockStyles;
use ParentTheme\Providers\ServiceProvider;
use ParentTheme\Providers\Contracts\HasBlocks;
use ParentTheme\Providers\ThemeService\Features\DisableBlocks;
use ParentTheme\Providers\ThemeService\Features\DisableComments;
use ParentTheme\Providers\ThemeService\Features\DisablePosts;
use ParentTheme\Providers\ThemeService\Features\EnableSvgUploads;
use WorDBless\BaseTestCase;
use ReflectionClass;

/**
 * Integration tests for ThemeServiceProvider.
 */
class ThemeServiceProviderTest extends BaseTestCase
{
    private ThemeServiceProvider $provider;

    public function set_up(): void
    {
        parent::set_up();
        $this->provider = new ThemeServiceProvider();
    }

    /**
     * Test that provider can be instantiated.
     */
    public function testProviderCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ThemeServiceProvider::class, $this->provider);
    }

    /**
     * Test that provider extends parent theme's ServiceProvider.
     */
    public function testProviderExtendsServiceProvider(): void
    {
        $this->assertInstanceOf(ServiceProvider::class, $this->provider);
    }

    /**
     * Test that provider implements HasBlocks.
     */
    public function testProviderImplementsHasBlocks(): void
    {
        $this->assertInstanceOf(HasBlocks::class, $this->provider);
    }

    /**
     * Test that provider has features configured.
     */
    public function testProviderHasFeatures(): void
    {
        $reflection = new ReflectionClass($this->provider);
        $property = $reflection->getProperty('features');
        $property->setAccessible(true);

        $features = $property->getValue($this->provider);

        // Child theme features
        $this->assertContains(ButtonIconEnhancer::class, $features);
        $this->assertContains(CoverBlockStyles::class, $features);

        // Parent theme features (inherited)
        $this->assertContains(DisableBlocks::class, $features);
        $this->assertContains(DisableComments::class, $features);
        $this->assertContains(DisablePosts::class, $features);
        $this->assertContains(EnableSvgUploads::class, $features);
    }

    /**
     * Test that provider has blocks configured.
     */
    public function testProviderHasBlocks(): void
    {
        $blocks = $this->provider->getBlocks();

        $this->assertContains('shutter-cards', $blocks);
        $this->assertContains('shutter-card', $blocks);
    }

    /**
     * Test that register method hooks into WordPress.
     */
    public function testRegisterAddsWordPressHooks(): void
    {
        $this->provider->register();

        $this->assertGreaterThan(
            0,
            has_action('wp_enqueue_scripts', [$this->provider, 'enqueueAssets'])
        );

        $this->assertGreaterThan(
            0,
            has_filter('show_admin_bar', '__return_false')
        );

        $this->assertGreaterThan(
            0,
            has_action('wp_head', [$this->provider, 'addFontPreconnects'])
        );

        $this->assertGreaterThan(
            0,
            has_action('enqueue_block_editor_assets', [$this->provider, 'enqueueButtonEditorAssets'])
        );

        $this->assertGreaterThan(
            0,
            has_action('enqueue_block_editor_assets', [$this->provider, 'localizeEditorData'])
        );

        // Core asset hooks (inherited from parent ThemeServiceProvider)
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

    /**
     * Test that blocks path points to correct directory.
     */
    public function testBlocksPathIsCorrect(): void
    {
        $blocksPath = $this->provider->getBlocksPath();
        $expectedPath = dirname(__DIR__, 3) . '/src/Providers/ThemeService/blocks';

        $this->assertEquals($expectedPath, $blocksPath);
    }

    /**
     * Test that shutter-cards block directory exists.
     */
    public function testShutterCardsBlockDirectoryExists(): void
    {
        $blocksPath = $this->provider->getBlocksPath();

        $this->assertDirectoryExists($blocksPath . '/shutter-cards');
        $this->assertFileExists($blocksPath . '/shutter-cards/block.json');
    }

    /**
     * Test that shutter-card block directory exists.
     */
    public function testShutterCardBlockDirectoryExists(): void
    {
        $blocksPath = $this->provider->getBlocksPath();

        $this->assertDirectoryExists($blocksPath . '/shutter-card');
        $this->assertFileExists($blocksPath . '/shutter-card/block.json');
    }

    /**
     * Test that provider has addFontPreconnects method.
     */
    public function testHasAddFontPreconnectsMethod(): void
    {
        $this->assertTrue(method_exists($this->provider, 'addFontPreconnects'));
    }

    /**
     * Test that provider has enqueueBlockAssets method.
     */
    public function testHasEnqueueBlockAssetsMethod(): void
    {
        $this->assertTrue(method_exists($this->provider, 'enqueueBlockAssets'));
    }

    /**
     * Test that provider has enqueueBlockEditorAssets method.
     */
    public function testHasEnqueueBlockEditorAssetsMethod(): void
    {
        $this->assertTrue(method_exists($this->provider, 'enqueueBlockEditorAssets'));
    }

    /**
     * Test that provider has enqueueButtonEditorAssets method.
     */
    public function testHasEnqueueButtonEditorAssetsMethod(): void
    {
        $this->assertTrue(method_exists($this->provider, 'enqueueButtonEditorAssets'));
    }

    /**
     * Test that provider has localizeEditorData method.
     */
    public function testHasLocalizeEditorDataMethod(): void
    {
        $this->assertTrue(method_exists($this->provider, 'localizeEditorData'));
    }
}
