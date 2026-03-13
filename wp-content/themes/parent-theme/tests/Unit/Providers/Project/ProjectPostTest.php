<?php

namespace ParentTheme\Tests\Unit\Providers\Project;

use ParentTheme\Models\Post;
use ParentTheme\Providers\Project\ProjectPost;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the parent ProjectPost model.
 */
class ProjectPostTest extends TestCase
{
    /**
     * Test that POST_TYPE constant is set to 'project'.
     */
    public function testPostTypeConstant(): void
    {
        $this->assertEquals('project', ProjectPost::POST_TYPE);
    }

    /**
     * Test that ProjectPost extends the base Post model.
     */
    public function testExtendsPost(): void
    {
        $this->assertTrue(is_subclass_of(ProjectPost::class, Post::class));
    }
}
