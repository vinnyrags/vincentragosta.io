<?php

namespace ChildTheme\Tests\Integration\Providers;

use ChildTheme\Providers\Project\ProjectPost;
use DI\Container;
use ChildTheme\Providers\Theme\ThemeProvider;
use ChildTheme\Providers\Theme\Features\ButtonIconEnhancer;
use ChildTheme\Providers\Theme\Features\CoverBlockStyles;
use ChildTheme\Tests\Support\HasContainer;
use ParentTheme\Providers\Provider;
use ParentTheme\Providers\Support\Feature\FeatureManager;
use ParentTheme\Providers\Theme\Features\DisableBlocks;
use ParentTheme\Providers\Theme\Features\DisableComments;
use ParentTheme\Providers\Theme\Features\DisablePosts;
use ParentTheme\Providers\Theme\Features\EnableSvgUploads;
use WorDBless\BaseTestCase;
use ReflectionClass;

/**
 * Integration tests for ThemeProvider.
 */
class ThemeProviderTest extends BaseTestCase
{
    use HasContainer;

    private ThemeProvider $provider;
    private Container $container;

    public function set_up(): void
    {
        parent::set_up();
        $this->container = $this->buildTestContainer();
        $this->provider = $this->container->get(ThemeProvider::class);
    }

    /**
     * Test that provider can be instantiated.
     */
    public function testProviderCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ThemeProvider::class, $this->provider);
    }

    /**
     * Test that provider extends parent theme's Provider.
     */
    public function testProviderExtendsBaseProvider(): void
    {
        $this->assertInstanceOf(Provider::class, $this->provider);
    }

    /**
     * Test that provider has features configured via collectFeatures.
     */
    public function testProviderHasFeatures(): void
    {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('collectFeatures');
        $method->setAccessible(true);

        $features = $method->invoke($this->provider);
        $manager = new FeatureManager($features, $this->container);
        $enabled = $manager->getEnabled();

        // Child theme features
        $this->assertContains(ButtonIconEnhancer::class, $enabled);
        $this->assertContains(CoverBlockStyles::class, $enabled);

        // Parent theme features (inherited)
        $this->assertContains(DisableBlocks::class, $enabled);
        $this->assertContains(DisableComments::class, $enabled);
        $this->assertContains(DisablePosts::class, $enabled);
        $this->assertContains(EnableSvgUploads::class, $enabled);
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

        // Preconnects now use wp_resource_hints filter instead of wp_head
        $this->assertGreaterThan(
            0,
            has_filter('wp_resource_hints', [$this->provider, 'addResourceHints'])
        );

        $this->assertGreaterThan(
            0,
            has_action('enqueue_block_editor_assets', [$this->provider, 'enqueueButtonEditorAssets'])
        );

        $this->assertGreaterThan(
            0,
            has_action('enqueue_block_editor_assets', [$this->provider, 'localizeEditorData'])
        );

        // Core asset hooks (inherited from parent ThemeProvider)
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

        $this->assertGreaterThan(
            0,
            has_action('acf/init', [$this->provider, 'registerOptionsPage'])
        );

        $this->assertGreaterThan(
            0,
            has_filter('timber/context', [$this->provider, 'addOptionsToContext'])
        );
    }

    /**
     * Test that blocks path points to correct directory.
     */
    public function testBlocksPathIsCorrect(): void
    {
        $blocksPath = $this->provider->getBlocksPath();
        $expectedPath = dirname(__DIR__, 3) . '/src/Providers/Theme/blocks';

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
     * Test that addResourceHints adds preconnect URLs.
     */
    public function testAddResourceHintsAddsPreconnectUrls(): void
    {
        $urls = $this->provider->addResourceHints([], 'preconnect');

        $this->assertCount(2, $urls);

        // Check for fonts.googleapis.com
        $this->assertContains(['href' => 'https://fonts.googleapis.com'], $urls);

        // Check for fonts.gstatic.com with crossorigin
        $this->assertContains([
            'href' => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        ], $urls);
    }

    /**
     * Test that addResourceHints doesn't add URLs for other relation types.
     */
    public function testAddResourceHintsIgnoresOtherRelationTypes(): void
    {
        $urls = $this->provider->addResourceHints([], 'dns-prefetch');

        $this->assertEmpty($urls);
    }

    /**
     * Test that registerClassMap includes project post type mapping.
     */
    public function testRegisterClassMapIncludesProjectMapping(): void
    {
        $classMap = $this->provider->registerClassMap([]);

        $this->assertArrayHasKey('project', $classMap);
        $this->assertEquals(ProjectPost::class, $classMap['project']);
    }

    /**
     * Test that registerClassMap preserves parent mappings.
     */
    public function testRegisterClassMapPreservesParentMappings(): void
    {
        $classMap = $this->provider->registerClassMap([]);

        $this->assertArrayHasKey('post', $classMap);
        $this->assertArrayHasKey('page', $classMap);
        $this->assertArrayHasKey('attachment', $classMap);
    }

    /**
     * Test that addOptionsToContext preserves existing context data.
     */
    public function testAddOptionsToContextPreservesExistingData(): void
    {
        $existingContext = [
            'site' => 'test-site',
            'menu' => ['item1', 'item2'],
        ];

        $context = $this->provider->addOptionsToContext($existingContext);

        $this->assertArrayHasKey('site', $context);
        $this->assertEquals('test-site', $context['site']);
        $this->assertArrayHasKey('menu', $context);
    }

    /**
     * Test that addOptionsToContext returns context with options key when ACF is available.
     */
    public function testAddOptionsToContextReturnsOptionsKey(): void
    {
        $context = $this->provider->addOptionsToContext([]);

        if (function_exists('get_field')) {
            $this->assertArrayHasKey('options', $context);
            $this->assertArrayHasKey('footer_description', $context['options']);
            $this->assertArrayHasKey('social_icons', $context['options']);
        } else {
            $this->assertEmpty($context);
        }
    }
}
