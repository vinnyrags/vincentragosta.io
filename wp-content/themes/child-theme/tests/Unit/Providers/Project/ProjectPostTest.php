<?php

namespace ChildTheme\Tests\Unit\Providers\Project;

use ChildTheme\Providers\Project\ProjectPost;
use ParentTheme\Providers\Project\ProjectPost as BaseProjectPost;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the child ProjectPost model.
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
     * Test that ProjectPost extends the parent ProjectPost.
     */
    public function testExtendsParentProjectPost(): void
    {
        $this->assertTrue(is_subclass_of(ProjectPost::class, BaseProjectPost::class));
    }

    /**
     * Test that sortYear method exists and has correct signature.
     */
    public function testSortYearMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProjectPost::class);
        $method = $reflection->getMethod('sortYear');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }

    /**
     * Test that relatedProjects method has correct return type.
     */
    public function testRelatedProjectsMethodReturnsArray(): void
    {
        $reflection = new \ReflectionClass(ProjectPost::class);
        $method = $reflection->getMethod('relatedProjects');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());
    }
}
