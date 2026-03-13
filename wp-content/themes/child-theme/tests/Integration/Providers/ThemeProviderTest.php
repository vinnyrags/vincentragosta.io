<?php

namespace ChildTheme\Tests\Integration\Providers;

use ChildTheme\Providers\Project\ProjectPost;
use DI\Container;
use ChildTheme\Providers\Theme\ThemeProvider;
use ChildTheme\Providers\Theme\Hooks\ContainerBlockStyles;
use ChildTheme\Providers\Theme\Hooks\CoverBlockStyles;
use ChildTheme\Providers\Theme\Hooks\TextBlockStyles;
use ChildTheme\Providers\Theme\Hooks\SocialIconChoices;
use ParentTheme\Providers\Theme\Hooks\AccordionIconEnhancer;
use ParentTheme\Providers\Theme\Hooks\ButtonIconEnhancer;
use ParentTheme\Providers\Theme\Hooks\FeaturedImageFocalPoint;
use ParentTheme\Providers\Theme\Hooks\TermsQuerySupports;
use ChildTheme\Tests\Support\HasContainer;
use ParentTheme\Providers\Provider;
use ParentTheme\Providers\Support\Feature\FeatureManager;
use ParentTheme\Providers\Theme\Features\DisableBlocks;
use ParentTheme\Providers\Theme\Features\DisableComments;
use ParentTheme\Providers\Theme\Features\DisableDefaultPatterns;
use ParentTheme\Providers\Theme\Features\DisablePosts;
use ParentTheme\Providers\Theme\Features\EnableSvgUploads;
use ParentTheme\Providers\Theme\Features\ScrollReveal;
use ParentTheme\Providers\Theme\Features\WpFormsBlockDetection;
use ParentTheme\Providers\Theme\Features\WpFormsFloatingLabels;
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
     * Test that provider inherits parent features only (child hooks excluded).
     */
    public function testProviderInheritsParentFeatures(): void
    {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('collectFeatures');
        $method->setAccessible(true);

        $features = $method->invoke($this->provider);
        $manager = new FeatureManager($features, $this->container);
        $enabled = $manager->getEnabled();

        // Parent theme features (inherited)
        $this->assertContains(DisableBlocks::class, $enabled);
        $this->assertContains(DisableComments::class, $enabled);
        $this->assertContains(DisablePosts::class, $enabled);
        $this->assertContains(EnableSvgUploads::class, $enabled);

        // Child theme opt-in features
        $this->assertContains(DisableDefaultPatterns::class, $enabled);
        $this->assertContains(ScrollReveal::class, $enabled);
        $this->assertContains(WpFormsBlockDetection::class, $enabled);
        $this->assertContains(WpFormsFloatingLabels::class, $enabled);
        $this->assertCount(8, $enabled);

        // Hook classes should NOT be in features
        $this->assertNotContains(AccordionIconEnhancer::class, $enabled);
        $this->assertNotContains(ButtonIconEnhancer::class, $enabled);
        $this->assertNotContains(CoverBlockStyles::class, $enabled);
        $this->assertNotContains(SocialIconChoices::class, $enabled);
    }

    /**
     * Test that provider has hooks configured via collectHooks.
     */
    public function testProviderHasHooks(): void
    {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('collectHooks');
        $method->setAccessible(true);

        $hooks = $method->invoke($this->provider);

        // Parent hooks (inherited)
        $this->assertContains(AccordionIconEnhancer::class, $hooks);
        $this->assertContains(ButtonIconEnhancer::class, $hooks);
        $this->assertContains(FeaturedImageFocalPoint::class, $hooks);
        $this->assertContains(TermsQuerySupports::class, $hooks);

        // Child hooks
        $this->assertContains(ContainerBlockStyles::class, $hooks);
        $this->assertContains(CoverBlockStyles::class, $hooks);
        $this->assertContains(TextBlockStyles::class, $hooks);
        $this->assertContains(SocialIconChoices::class, $hooks);
        $this->assertCount(8, $hooks);
    }

    /**
     * Test that provider has blocks configured (child + inherited parent).
     */
    public function testProviderHasBlocks(): void
    {
        $blocks = $this->provider->getBlocks();

        // Child blocks
        $this->assertContains('shutter-cards', $blocks);
        $this->assertContains('shutter-card', $blocks);

        // Inherited parent blocks
        $this->assertContains('testimonials', $blocks);
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
            has_action('enqueue_block_editor_assets', [$this->provider, 'localizeButtonIconData'])
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

        $this->assertNotFalse(
            has_action('acf/init'),
            'acf/init action should be registered for options page auto-discovery'
        );

        $this->assertGreaterThan(
            0,
            has_filter('timber/context', [$this->provider, 'addOptionsToContext'])
        );
    }

    /**
     * Test that shutter-cards block directory exists in parent theme.
     */
    public function testShutterCardsBlockDirectoryExistsInParent(): void
    {
        $parentBlocksPath = realpath(dirname(__DIR__, 3) . '/../parent-theme') . '/src/Providers/Theme/blocks';

        $this->assertDirectoryExists($parentBlocksPath . '/shutter-cards');
        $this->assertFileExists($parentBlocksPath . '/shutter-cards/block.json');
    }

    /**
     * Test that shutter-card block directory exists in parent theme.
     */
    public function testShutterCardBlockDirectoryExistsInParent(): void
    {
        $parentBlocksPath = realpath(dirname(__DIR__, 3) . '/../parent-theme') . '/src/Providers/Theme/blocks';

        $this->assertDirectoryExists($parentBlocksPath . '/shutter-card');
        $this->assertFileExists($parentBlocksPath . '/shutter-card/block.json');
    }

    /**
     * Test that testimonials block directory exists in parent theme.
     */
    public function testTestimonialsBlockDirectoryExistsInParent(): void
    {
        $parentBlocksPath = realpath(dirname(__DIR__, 3) . '/../parent-theme') . '/src/Providers/Theme/blocks';

        $this->assertDirectoryExists($parentBlocksPath . '/testimonials');
        $this->assertFileExists($parentBlocksPath . '/testimonials/block.json');
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
