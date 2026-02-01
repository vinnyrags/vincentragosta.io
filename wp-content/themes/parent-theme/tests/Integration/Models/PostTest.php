<?php

namespace ParentTheme\Tests\Integration\Models;

use ParentTheme\Models\Post;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the Post model.
 *
 * Note: These tests verify WordPress meta operations work correctly.
 * Timber-based tests are in Unit tests due to WorDBless limitations.
 */
class PostTest extends BaseTestCase
{
    private int $postId;

    public function set_up(): void
    {
        parent::set_up();

        $this->postId = wp_insert_post([
            'post_title' => 'Test Post',
            'post_content' => 'Test content here.',
            'post_excerpt' => 'Test excerpt.',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);

        update_post_meta($this->postId, 'test_meta_key', 'test_meta_value');
    }

    public function tear_down(): void
    {
        wp_delete_post($this->postId, true);
        parent::tear_down();
    }

    /**
     * Test that posts can be inserted via WordPress.
     */
    public function testPostCanBeInserted(): void
    {
        $this->assertIsInt($this->postId);
        $this->assertGreaterThan(0, $this->postId);
    }

    /**
     * Test that post can be retrieved via WordPress.
     */
    public function testPostCanBeRetrieved(): void
    {
        $post = get_post($this->postId);

        $this->assertInstanceOf(\WP_Post::class, $post);
        $this->assertEquals('Test Post', $post->post_title);
    }

    /**
     * Test that post meta can be set and retrieved.
     */
    public function testPostMetaCanBeSetAndRetrieved(): void
    {
        $value = get_post_meta($this->postId, 'test_meta_key', true);

        $this->assertEquals('test_meta_value', $value);
    }

    /**
     * Test that post meta can be updated.
     */
    public function testPostMetaCanBeUpdated(): void
    {
        update_post_meta($this->postId, 'test_meta_key', 'new_value');

        $value = get_post_meta($this->postId, 'test_meta_key', true);
        $this->assertEquals('new_value', $value);
    }

    /**
     * Test that post meta can be deleted.
     */
    public function testPostMetaCanBeDeleted(): void
    {
        delete_post_meta($this->postId, 'test_meta_key');

        $value = get_post_meta($this->postId, 'test_meta_key', true);
        $this->assertEmpty($value);
    }

    /**
     * Test that post can be updated.
     */
    public function testPostCanBeUpdated(): void
    {
        wp_update_post([
            'ID' => $this->postId,
            'post_title' => 'Updated Title',
        ]);

        $post = get_post($this->postId);
        $this->assertEquals('Updated Title', $post->post_title);
    }

    /**
     * Test POST_TYPE constant value.
     */
    public function testPostTypeConstant(): void
    {
        $this->assertEquals('post', Post::POST_TYPE);
    }

    /**
     * Test that Post class extends Timber\Post.
     */
    public function testExtendsTimberPost(): void
    {
        $this->assertTrue(is_subclass_of(Post::class, \Timber\Post::class));
    }
}
