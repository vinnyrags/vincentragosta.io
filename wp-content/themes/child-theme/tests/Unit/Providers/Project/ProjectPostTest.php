<?php

namespace ChildTheme\Tests\Unit\Providers\Project;

use ChildTheme\Providers\Project\ProjectPost;
use ChildTheme\Tests\Mocks\MockProjectPost;
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

    // ===================
    // ACF field accessor tests
    // ===================

    /**
     * Test client() returns field value.
     */
    public function testClientReturnsFieldValue(): void
    {
        $post = MockProjectPost::create();
        $post->setMockMeta(['client' => 'Acme Corp']);

        $this->assertEquals('Acme Corp', $post->client());
    }

    /**
     * Test client() returns empty string when not set.
     */
    public function testClientReturnsEmptyWhenNotSet(): void
    {
        $post = MockProjectPost::create();

        $this->assertEquals('', $post->client());
    }

    /**
     * Test role() returns field value.
     */
    public function testRoleReturnsFieldValue(): void
    {
        $post = MockProjectPost::create();
        $post->setMockMeta(['role' => 'Lead Developer']);

        $this->assertEquals('Lead Developer', $post->role());
    }

    /**
     * Test year() returns field value.
     */
    public function testYearReturnsFieldValue(): void
    {
        $post = MockProjectPost::create();
        $post->setMockMeta(['year' => '2023-2024']);

        $this->assertEquals('2023-2024', $post->year());
    }

    /**
     * Test technologies() returns raw string.
     */
    public function testTechnologiesReturnsRawString(): void
    {
        $post = MockProjectPost::create();
        $post->setMockMeta(['technologies' => 'React, TypeScript, Node.js']);

        $this->assertEquals('React, TypeScript, Node.js', $post->technologies());
    }

    /**
     * Test technologyList() splits comma-separated string.
     */
    public function testTechnologyListSplitsString(): void
    {
        $post = MockProjectPost::create();
        $post->setMockMeta(['technologies' => 'React, TypeScript, Node.js']);

        $result = $post->technologyList();

        $this->assertCount(3, $result);
        $this->assertEquals('React', $result[0]);
        $this->assertEquals('TypeScript', $result[1]);
        $this->assertEquals('Node.js', $result[2]);
    }

    /**
     * Test technologyList() trims whitespace from items.
     */
    public function testTechnologyListTrimsWhitespace(): void
    {
        $post = MockProjectPost::create();
        $post->setMockMeta(['technologies' => '  React  ,  TypeScript  ']);

        $result = $post->technologyList();

        $this->assertEquals('React', $result[0]);
        $this->assertEquals('TypeScript', $result[1]);
    }

    /**
     * Test technologyList() returns empty array when not set.
     */
    public function testTechnologyListReturnsEmptyArrayWhenNotSet(): void
    {
        $post = MockProjectPost::create();

        $this->assertEquals([], $post->technologyList());
    }

    /**
     * Test externalUrl() returns field value.
     */
    public function testExternalUrlReturnsFieldValue(): void
    {
        $post = MockProjectPost::create();
        $post->setMockMeta(['external_url' => 'https://example.com']);

        $this->assertEquals('https://example.com', $post->externalUrl());
    }

    /**
     * Test hasProjectDetails() returns true when any field is set.
     */
    public function testHasProjectDetailsReturnsTrueWithClient(): void
    {
        $post = MockProjectPost::create();
        $post->setMockMeta(['client' => 'Acme']);

        $this->assertTrue($post->hasProjectDetails());
    }

    /**
     * Test hasProjectDetails() returns true with only technologies.
     */
    public function testHasProjectDetailsReturnsTrueWithTechnologies(): void
    {
        $post = MockProjectPost::create();
        $post->setMockMeta(['technologies' => 'React']);

        $this->assertTrue($post->hasProjectDetails());
    }

    /**
     * Test hasProjectDetails() returns false when no fields set.
     */
    public function testHasProjectDetailsReturnsFalseWhenEmpty(): void
    {
        $post = MockProjectPost::create();

        $this->assertFalse($post->hasProjectDetails());
    }

    /**
     * Test all accessor methods have correct return types.
     */
    public function testAccessorMethodReturnTypes(): void
    {
        $reflection = new \ReflectionClass(ProjectPost::class);
        $stringMethods = ['client', 'role', 'year', 'technologies', 'externalUrl'];

        foreach ($stringMethods as $method) {
            $returnType = $reflection->getMethod($method)->getReturnType();
            $this->assertEquals('string', $returnType->getName(), "$method should return string");
        }

        $this->assertEquals('array', (string) $reflection->getMethod('technologyList')->getReturnType());
        $this->assertEquals('bool', (string) $reflection->getMethod('hasProjectDetails')->getReturnType());
        $this->assertEquals('array', (string) $reflection->getMethod('relatedProjects')->getReturnType());
    }
}
