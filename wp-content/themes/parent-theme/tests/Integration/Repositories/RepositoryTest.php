<?php

namespace ParentTheme\Tests\Integration\Repositories;

use ParentTheme\Models\Post;
use ParentTheme\Repositories\Repository;
use ParentTheme\Repositories\RepositoryInterface;
use WorDBless\BaseTestCase;
use ReflectionClass;

/**
 * Integration tests for the Repository class.
 *
 * Note: WP_Query and Timber queries don't work in WorDBless.
 * These tests focus on structure and WordPress-compatible operations.
 */
class RepositoryTest extends BaseTestCase
{
    private Repository $repository;
    private array $postIds = [];

    public function set_up(): void
    {
        parent::set_up();

        $this->repository = new Repository();

        // Create test posts
        $this->postIds[] = wp_insert_post([
            'post_title' => 'First Post',
            'post_content' => 'First content.',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);

        $this->postIds[] = wp_insert_post([
            'post_title' => 'Second Post',
            'post_content' => 'Second content.',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);

        update_post_meta($this->postIds[0], 'featured', '1');
    }

    public function tear_down(): void
    {
        foreach ($this->postIds as $postId) {
            wp_delete_post($postId, true);
        }
        parent::tear_down();
    }

    /**
     * Test that Repository implements RepositoryInterface.
     */
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(RepositoryInterface::class, $this->repository);
    }

    /**
     * Test that posts were created successfully.
     */
    public function testPostsAreCreated(): void
    {
        $this->assertCount(2, $this->postIds);
        foreach ($this->postIds as $id) {
            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
        }
    }

    /**
     * Test that posts can be retrieved via get_post().
     */
    public function testPostsCanBeRetrieved(): void
    {
        foreach ($this->postIds as $id) {
            $post = get_post($id);
            $this->assertInstanceOf(\WP_Post::class, $post);
        }
    }

    /**
     * Test that post meta is set correctly.
     */
    public function testPostMetaIsSet(): void
    {
        $featured = get_post_meta($this->postIds[0], 'featured', true);
        $this->assertEquals('1', $featured);
    }

    /**
     * Test findMany returns empty array for empty IDs.
     */
    public function testFindManyReturnsEmptyArrayForEmptyIds(): void
    {
        $posts = $this->repository->findMany([]);

        $this->assertIsArray($posts);
        $this->assertEmpty($posts);
    }

    /**
     * Test that Repository has correct model class.
     */
    public function testHasCorrectModelClass(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        $this->assertEquals(Post::class, $property->getValue($this->repository));
    }

    /**
     * Test that postType returns correct value.
     */
    public function testPostTypeReturnsPost(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('postType');
        $method->setAccessible(true);

        $this->assertEquals('post', $method->invoke($this->repository));
    }

    /**
     * Test that buildArgs includes post_type.
     */
    public function testBuildArgsIncludesPostType(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('buildArgs');
        $method->setAccessible(true);

        $args = $method->invoke($this->repository, []);

        $this->assertArrayHasKey('post_type', $args);
        $this->assertEquals('post', $args['post_type']);
    }

    /**
     * Test that buildArgs includes default post_status.
     */
    public function testBuildArgsIncludesPostStatus(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('buildArgs');
        $method->setAccessible(true);

        $args = $method->invoke($this->repository, []);

        $this->assertArrayHasKey('post_status', $args);
        $this->assertEquals('publish', $args['post_status']);
    }

    /**
     * Test that buildArgs allows overriding defaults.
     */
    public function testBuildArgsAllowsOverrides(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('buildArgs');
        $method->setAccessible(true);

        $args = $method->invoke($this->repository, ['post_status' => 'draft']);

        $this->assertEquals('draft', $args['post_status']);
    }

    /**
     * Test that post can be deleted via WordPress.
     */
    public function testPostCanBeDeleted(): void
    {
        $newPostId = wp_insert_post([
            'post_title' => 'To Delete',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);

        $result = wp_delete_post($newPostId, true);

        $this->assertNotFalse($result);
        $this->assertNull(get_post($newPostId));
    }

    /**
     * Test excludeCurrentPost property defaults to true.
     */
    public function testExcludeCurrentPostDefaultsToTrue(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('excludeCurrentPost');
        $property->setAccessible(true);

        $this->assertTrue($property->getValue($this->repository));
    }
}
