<?php

namespace ChildTheme\Tests\Unit\Providers\Project;

use ChildTheme\Providers\Project\ProjectPost;
use ChildTheme\Providers\Project\ProjectRepository;
use ParentTheme\Providers\Project\ProjectRepository as BaseProjectRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for the child ProjectRepository.
 */
class ProjectRepositoryTest extends TestCase
{
    private ProjectRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProjectRepository();
    }

    /**
     * Test that ProjectRepository extends the parent ProjectRepository.
     */
    public function testExtendsParentProjectRepository(): void
    {
        $this->assertInstanceOf(BaseProjectRepository::class, $this->repository);
    }

    /**
     * Test that model is set to child ProjectPost.
     */
    public function testModelIsChildProjectPost(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        $this->assertEquals(ProjectPost::class, $property->getValue($this->repository));
    }

    /**
     * Test that allByYear method exists and has correct signature.
     */
    public function testAllByYearMethodSignature(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('allByYear');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());
        $this->assertCount(0, $method->getParameters());
    }

    /**
     * Test that latestByYear method exists and has correct signature.
     */
    public function testLatestByYearMethodSignature(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('latestByYear');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());

        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('limit', $params[0]->getName());
        $this->assertTrue($params[0]->isOptional());
        $this->assertEquals(6, $params[0]->getDefaultValue());
    }

    /**
     * Test that inherited featured method is accessible.
     */
    public function testInheritedFeaturedMethodAccessible(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $this->assertTrue($reflection->hasMethod('featured'));
        $this->assertTrue($reflection->getMethod('featured')->isPublic());
    }

    /**
     * Test that inherited inCategory method is accessible.
     */
    public function testInheritedInCategoryMethodAccessible(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $this->assertTrue($reflection->hasMethod('inCategory'));
        $this->assertTrue($reflection->getMethod('inCategory')->isPublic());
    }

    /**
     * Test that inherited relatedRandom method is accessible.
     */
    public function testInheritedRelatedRandomMethodAccessible(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $this->assertTrue($reflection->hasMethod('relatedRandom'));
        $this->assertTrue($reflection->getMethod('relatedRandom')->isPublic());
    }
}
