<?php

namespace ChildTheme\Tests\Integration\Providers;

use DI\Container;
use ChildTheme\Providers\Project\ProjectProvider;
use ParentTheme\Providers\ServiceProvider;
use ParentTheme\Tests\Support\HasContainer;
use WorDBless\BaseTestCase;

/**
 * Integration tests for ProjectProvider.
 */
class ProjectProviderTest extends BaseTestCase
{
    use HasContainer;

    private ProjectProvider $provider;
    private Container $container;

    public function set_up(): void
    {
        parent::set_up();
        $this->container = $this->buildTestContainer();
        $this->provider = new ProjectProvider($this->container);
    }

    /**
     * Test that provider can be instantiated.
     */
    public function testProviderCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ProjectProvider::class, $this->provider);
    }

    /**
     * Test that provider extends parent theme's ServiceProvider.
     */
    public function testProviderExtendsServiceProvider(): void
    {
        $this->assertInstanceOf(ServiceProvider::class, $this->provider);
    }

    /**
     * Test that register method hooks into WordPress.
     */
    public function testRegisterAddsWordPressHooks(): void
    {
        $this->provider->register();

        $this->assertGreaterThan(
            0,
            has_action('init', [$this->provider, 'registerPostType'])
        );
    }

    /**
     * Test that config file exists.
     */
    public function testConfigFileExists(): void
    {
        $configPath = dirname(__DIR__, 3) . '/src/Providers/Project/config/post-type.json';

        $this->assertFileExists($configPath);
    }

    /**
     * Test that config file contains valid JSON.
     */
    public function testConfigFileIsValidJson(): void
    {
        $configPath = dirname(__DIR__, 3) . '/src/Providers/Project/config/post-type.json';
        $content = file_get_contents($configPath);
        $data = json_decode($content, true);

        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertIsArray($data);
    }

    /**
     * Test that config has required keys.
     */
    public function testConfigHasRequiredKeys(): void
    {
        $configPath = dirname(__DIR__, 3) . '/src/Providers/Project/config/post-type.json';
        $content = file_get_contents($configPath);
        $data = json_decode($content, true);

        $this->assertArrayHasKey('post_type', $data);
        $this->assertArrayHasKey('args', $data);
        $this->assertEquals('project', $data['post_type']);
    }

    /**
     * Test that post type args contain expected configuration.
     */
    public function testPostTypeArgsContainExpectedConfig(): void
    {
        $configPath = dirname(__DIR__, 3) . '/src/Providers/Project/config/post-type.json';
        $content = file_get_contents($configPath);
        $data = json_decode($content, true);
        $args = $data['args'];

        $this->assertArrayHasKey('labels', $args);
        $this->assertArrayHasKey('public', $args);
        $this->assertArrayHasKey('has_archive', $args);
        $this->assertArrayHasKey('supports', $args);
        $this->assertArrayHasKey('show_in_rest', $args);
        $this->assertTrue($args['public']);
        $this->assertTrue($args['has_archive']);
        $this->assertTrue($args['show_in_rest']);
    }

    /**
     * Test that post type is registered after calling registerPostType.
     */
    public function testPostTypeIsRegistered(): void
    {
        $this->provider->registerPostType();

        $this->assertTrue(post_type_exists('project'));
    }

    /**
     * Test that registered post type has correct configuration.
     */
    public function testRegisteredPostTypeHasCorrectConfig(): void
    {
        $this->provider->registerPostType();

        $postType = get_post_type_object('project');

        $this->assertNotNull($postType);
        $this->assertTrue($postType->public);
        $this->assertTrue($postType->has_archive);
        $this->assertTrue($postType->show_in_rest);
        $this->assertEquals('dashicons-portfolio', $postType->menu_icon);
    }

    /**
     * Test that post type supports expected features.
     */
    public function testPostTypeSupportsExpectedFeatures(): void
    {
        $this->provider->registerPostType();

        $this->assertTrue(post_type_supports('project', 'title'));
        $this->assertTrue(post_type_supports('project', 'editor'));
        $this->assertTrue(post_type_supports('project', 'thumbnail'));
        $this->assertTrue(post_type_supports('project', 'excerpt'));
        $this->assertTrue(post_type_supports('project', 'custom-fields'));
    }

    /**
     * Test that post type uses default categories.
     */
    public function testPostTypeUsesDefaultCategories(): void
    {
        $this->provider->registerPostType();

        $taxonomies = get_object_taxonomies('project');

        $this->assertContains('category', $taxonomies);
    }
}
