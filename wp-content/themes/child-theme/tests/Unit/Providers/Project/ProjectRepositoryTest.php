<?php

namespace ChildTheme\Tests\Unit\Providers\Project;

use ChildTheme\Providers\Project\ProjectPost;
use ChildTheme\Providers\Project\ProjectRepository;
use ParentTheme\Repositories\Repository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for the ProjectRepository.
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
     * Test that ProjectRepository extends the base Repository.
     */
    public function testExtendsRepository(): void
    {
        $this->assertInstanceOf(Repository::class, $this->repository);
    }

    /**
     * Test that model is set to ProjectPost.
     */
    public function testModelIsProjectPost(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        $this->assertEquals(ProjectPost::class, $property->getValue($this->repository));
    }

    /**
     * Test that featured method exists and has correct signature.
     */
    public function testFeaturedMethodSignature(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('featured');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());

        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('limit', $params[0]->getName());
        $this->assertTrue($params[0]->isOptional());
        $this->assertEquals(5, $params[0]->getDefaultValue());
    }

    /**
     * Test that inCategory method exists and has correct signature.
     */
    public function testInCategoryMethodSignature(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('inCategory');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('category', $params[0]->getName());
        $this->assertEquals('limit', $params[1]->getName());
        $this->assertTrue($params[1]->isOptional());
        $this->assertEquals(-1, $params[1]->getDefaultValue());
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
     * Test that relatedRandom method exists and has correct signature.
     */
    public function testRelatedRandomMethodSignature(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('relatedRandom');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('categorySlug', $params[0]->getName());
        $this->assertEquals('limit', $params[1]->getName());
        $this->assertTrue($params[1]->isOptional());
        $this->assertEquals(3, $params[1]->getDefaultValue());
    }
}
