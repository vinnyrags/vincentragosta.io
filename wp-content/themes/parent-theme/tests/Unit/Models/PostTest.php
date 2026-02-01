<?php

namespace ParentTheme\Tests\Unit\Models;

use ParentTheme\Models\Post;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Post model.
 */
class PostTest extends TestCase
{
    /**
     * Test POST_TYPE constant.
     */
    public function testPostTypeConstant(): void
    {
        $this->assertEquals('post', Post::POST_TYPE);
    }

    /**
     * Test that Post extends Timber\Post.
     */
    public function testExtendsTimberPost(): void
    {
        $this->assertTrue(is_subclass_of(Post::class, \Timber\Post::class));
    }

    /**
     * Test that url() method exists.
     */
    public function testHasUrlMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'url'));
    }

    /**
     * Test that publishedDate() method exists.
     */
    public function testHasPublishedDateMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'publishedDate'));
    }

    /**
     * Test that modifiedDate() method exists.
     */
    public function testHasModifiedDateMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'modifiedDate'));
    }

    /**
     * Test that isPublished() method exists.
     */
    public function testHasIsPublishedMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'isPublished'));
    }

    /**
     * Test that isDraft() method exists.
     */
    public function testHasIsDraftMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'isDraft'));
    }

    /**
     * Test that getMeta() method exists.
     */
    public function testHasGetMetaMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'getMeta'));
    }

    /**
     * Test that setMeta() method exists.
     */
    public function testHasSetMetaMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'setMeta'));
    }

    /**
     * Test that deleteMeta() method exists.
     */
    public function testHasDeleteMetaMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'deleteMeta'));
    }

    /**
     * Test that hasTerm() method exists.
     */
    public function testHasHasTermMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'hasTerm'));
    }

    /**
     * Test that refresh() method exists.
     */
    public function testHasRefreshMethod(): void
    {
        $this->assertTrue(method_exists(Post::class, 'refresh'));
    }
}
