<?php

namespace ChildTheme\Tests\Unit\Providers\Project;

use ChildTheme\Providers\Project\ProjectPost;
use ParentTheme\Models\Post;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ProjectPost model.
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

    /**
     * Test that categories method is defined.
     */
    public function testCategoriesMethodReturnsArray(): void
    {
        $reflection = new \ReflectionClass(ProjectPost::class);
        $method = $reflection->getMethod('categories');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());
    }

    /**
     * Test that categoryName method has correct return type.
     */
    public function testCategoryNameMethodReturnsNullableString(): void
    {
        $reflection = new \ReflectionClass(ProjectPost::class);
        $method = $reflection->getMethod('categoryName');

        $this->assertTrue($method->isPublic());
        $returnType = $method->getReturnType();
        $this->assertEquals('string', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());
    }

    /**
     * Test that categorySlug method has correct return type.
     */
    public function testCategorySlugMethodReturnsNullableString(): void
    {
        $reflection = new \ReflectionClass(ProjectPost::class);
        $method = $reflection->getMethod('categorySlug');

        $this->assertTrue($method->isPublic());
        $returnType = $method->getReturnType();
        $this->assertEquals('string', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());
    }

    /**
     * Test that categorySlugs method has correct return type.
     */
    public function testCategorySlugsMethodReturnsString(): void
    {
        $reflection = new \ReflectionClass(ProjectPost::class);
        $method = $reflection->getMethod('categorySlugs');

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
