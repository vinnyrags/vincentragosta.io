<?php

namespace ChildTheme\Tests\Integration\Providers;

use ChildTheme\Providers\Project\ProjectPost;
use DI\Container;
use ChildTheme\Providers\Theme\ThemeProvider;
use IX\Providers\Theme\Features\ContentPartial\ContentPartial;
use ChildTheme\Providers\Theme\Hooks\AccentHighlight;
use ChildTheme\Providers\Theme\Hooks\DistAssetVersioning;
use ChildTheme\Providers\Theme\Hooks\ContainerBlockStyles;
use ChildTheme\Providers\Theme\Hooks\MediaBlockStyles;
use ChildTheme\Providers\Theme\Hooks\TextBlockStyles;
use ChildTheme\Providers\Theme\Hooks\SearchSetup;
use ChildTheme\Providers\Theme\Hooks\MailIdentity;
use ChildTheme\Providers\Theme\Hooks\SocialIconChoices;
use ChildTheme\Providers\Theme\Hooks\SocialIconOverride;
use IX\Providers\Theme\Features\ButtonIconEnhancer;
use IX\Providers\Theme\Features\AccordionIconEnhancer;
use IX\Providers\Theme\Features\CoreBlockStyles;
use IX\Providers\Theme\Hooks\FeaturedImageFocalPoint;
use IX\Providers\Theme\Hooks\HeadingBlockStyles;
use IX\Providers\Theme\Hooks\TermsQuerySupports;
use ChildTheme\Tests\Support\HasContainer;
use IX\Providers\Provider;
use Mythus\Support\Feature\FeatureManager;
use IX\Providers\Theme\Features\DisableAuthorArchives;
use IX\Providers\Theme\Features\DisableBlocks;
use IX\Providers\Theme\Features\DisableComments;
use IX\Providers\Theme\Features\DisableDefaultPatterns;
use IX\Providers\Theme\Features\DisablePluginUpdates;
use IX\Providers\Theme\Features\DisablePosts;
use IX\Providers\Theme\Features\EnableSvgUploads;
use IX\Providers\Theme\Features\ScrollReveal;
use IX\Providers\Theme\Features\WpFormsBaseStyles;
use IX\Providers\Theme\Features\WpFormsBlockDetection;
use IX\Providers\Theme\Features\WpFormsFloatingLabels;
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
        $this->assertContains(DisableAuthorArchives::class, $enabled);
        $this->assertContains(DisableBlocks::class, $enabled);
        $this->assertContains(DisableComments::class, $enabled);
        $this->assertContains(DisableDefaultPatterns::class, $enabled);
        $this->assertContains(DisablePluginUpdates::class, $enabled);
        $this->assertContains(DisablePosts::class, $enabled);
        $this->assertContains(EnableSvgUploads::class, $enabled);
        // Header/footer render from content partials (no longer opted out)
        $this->assertContains(ContentPartial::class, $enabled);

        // Child theme opt-in features
        $this->assertContains(AccordionIconEnhancer::class, $enabled);
        $this->assertContains(ButtonIconEnhancer::class, $enabled);
        $this->assertContains(ScrollReveal::class, $enabled);
        $this->assertContains(WpFormsBaseStyles::class, $enabled);
        $this->assertContains(WpFormsBlockDetection::class, $enabled);
        // Static labels sit above the fields; floating labels are not used.
        $this->assertNotContains(WpFormsFloatingLabels::class, $enabled);
        // Core's bundled block theme CSS is off; this theme styles those blocks itself.
        $this->assertNotContains(CoreBlockStyles::class, $enabled);
        $this->assertCount(13, $enabled);

        // Hook classes should NOT be in features
        $this->assertNotContains(MediaBlockStyles::class, $enabled);
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
        $this->assertContains(FeaturedImageFocalPoint::class, $hooks);
        $this->assertContains(HeadingBlockStyles::class, $hooks);
        $this->assertContains(TermsQuerySupports::class, $hooks);
        // IX >= 1.8.0. Inert here: it acts only when IX_MAIL_FROM is set, and this
        // site sets its From in the child's own MailIdentity instead.
        $this->assertContains(\IX\Providers\Theme\Hooks\MailIdentity::class, $hooks);

        // Child hooks
        $this->assertContains(ContainerBlockStyles::class, $hooks);
        $this->assertContains(MediaBlockStyles::class, $hooks);
        $this->assertContains(TextBlockStyles::class, $hooks);
        $this->assertContains(SocialIconChoices::class, $hooks);
        $this->assertContains(SocialIconOverride::class, $hooks);
        $this->assertContains(AccentHighlight::class, $hooks);
        $this->assertContains(SearchSetup::class, $hooks);
        $this->assertContains(MailIdentity::class, $hooks);
        $this->assertContains(DistAssetVersioning::class, $hooks);
        $this->assertContains(\ChildTheme\Providers\Theme\Hooks\SitemapFilterUrls::class, $hooks);
        $this->assertCount(14, $hooks);

        // ButtonIconEnhancer is now a Feature, not a Hook
        $this->assertNotContains(ButtonIconEnhancer::class, $hooks);
    }

    /**
     * Test that provider has blocks configured (child + inherited parent).
     */
    public function testProviderHasBlocks(): void
    {
        $blocks = $this->provider->getBlocks();

        // Inherited parent blocks (IX >= 1.11 dropped shutter-cards)
        $this->assertContains('content-slider', $blocks);
        $this->assertNotContains('shutter-cards', $blocks);
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

        $this->assertSame(
            1,
            has_action('wp_head', [$this->provider, 'preloadFonts'])
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
     * Test that content-slider block directory exists in parent theme.
     */
    public function testContentSliderBlockDirectoryExistsInParent(): void
    {
        $parentBlocksPath = realpath(dirname(__DIR__, 3) . '/../ix') . '/src/Providers/Theme/blocks';

        $this->assertDirectoryExists($parentBlocksPath . '/content-slider');
        $this->assertFileExists($parentBlocksPath . '/content-slider/block.json');
    }

    /**
     * Test that preloadFonts prints a woff2 preload link for each listed font.
     */
    public function testPreloadFontsPrintsWoff2PreloadLinks(): void
    {
        ob_start();
        $this->provider->preloadFonts();
        $html = (string) ob_get_clean();

        $this->assertSame(count(ThemeProvider::PRELOAD_FONTS), substr_count($html, 'rel="preload"'));

        foreach (ThemeProvider::PRELOAD_FONTS as $file) {
            $this->assertStringContainsString($file, $html);
        }

        $this->assertStringContainsString('as="font" type="font/woff2" crossorigin', $html);
    }

    /**
     * Test that every preloaded font file exists on disk.
     */
    public function testPreloadFontFilesExist(): void
    {
        $fontsDir = dirname(__DIR__, 3) . '/src/Providers/Theme/assets/fonts/';

        foreach (ThemeProvider::PRELOAD_FONTS as $file) {
            $this->assertFileExists($fontsDir . $file);
        }
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
