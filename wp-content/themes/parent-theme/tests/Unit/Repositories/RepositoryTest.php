<?php

namespace ParentTheme\Tests\Unit\Repositories;

use ParentTheme\Models\Post;
use ParentTheme\Repositories\Repository;
use ParentTheme\Repositories\RepositoryInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for the Repository class.
 */
class RepositoryTest extends TestCase
{
    private Repository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new Repository();
    }

    /**
     * Test that Repository implements RepositoryInterface.
     */
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(RepositoryInterface::class, $this->repository);
    }

    /**
     * Test that Repository has model property.
     */
    public function testHasModelProperty(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        $this->assertEquals(Post::class, $property->getValue($this->repository));
    }

    /**
     * Test that Repository has excludeCurrentPost property.
     */
    public function testHasExcludeCurrentPostProperty(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('excludeCurrentPost');
        $property->setAccessible(true);

        $this->assertTrue($property->getValue($this->repository));
    }

    /**
     * Test that Repository has defaultArgs property.
     */
    public function testHasDefaultArgsProperty(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('defaultArgs');
        $property->setAccessible(true);

        $defaults = $property->getValue($this->repository);
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('post_status', $defaults);
        $this->assertEquals('publish', $defaults['post_status']);
    }

    /**
     * Test that postType() returns the model's POST_TYPE.
     */
    public function testPostTypeReturnsModelPostType(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('postType');
        $method->setAccessible(true);

        $this->assertEquals('post', $method->invoke($this->repository));
    }

    /**
     * Test that find() method exists.
     */
    public function testHasFindMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'find'));
    }

    /**
     * Test that findBySlug() method exists.
     */
    public function testHasFindBySlugMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'findBySlug'));
    }

    /**
     * Test that findOne() method exists.
     */
    public function testHasFindOneMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'findOne'));
    }

    /**
     * Test that all() method exists.
     */
    public function testHasAllMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'all'));
    }

    /**
     * Test that latest() method exists.
     */
    public function testHasLatestMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'latest'));
    }

    /**
     * Test that findMany() method exists.
     */
    public function testHasFindManyMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'findMany'));
    }

    /**
     * Test that findMany() returns empty array for empty IDs.
     */
    public function testFindManyReturnsEmptyArrayForEmptyIds(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('findMany');

        $result = $method->invoke($this->repository, []);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that byAuthor() method exists.
     */
    public function testHasByAuthorMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'byAuthor'));
    }

    /**
     * Test that drafts() method exists.
     */
    public function testHasDraftsMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'drafts'));
    }

    /**
     * Test that whereMetaEquals() method exists.
     */
    public function testHasWhereMetaEqualsMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'whereMetaEquals'));
    }

    /**
     * Test that whereTerm() method exists.
     */
    public function testHasWhereTermMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'whereTerm'));
    }

    /**
     * Test that whereTermIds() method exists.
     */
    public function testHasWhereTermIdsMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'whereTermIds'));
    }

    /**
     * Test that count() method exists.
     */
    public function testHasCountMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'count'));
    }

    /**
     * Test that exists() method exists.
     */
    public function testHasExistsMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'exists'));
    }

    /**
     * Test that save() method exists.
     */
    public function testHasSaveMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'save'));
    }

    /**
     * Test that delete() method exists.
     */
    public function testHasDeleteMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'delete'));
    }

    /**
     * Test that query() method exists.
     */
    public function testHasQueryMethod(): void
    {
        $this->assertTrue(method_exists(Repository::class, 'query'));
    }

    /**
     * Test buildArgs() merges defaults with provided args.
     */
    public function testBuildArgsMergesDefaults(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('buildArgs');
        $method->setAccessible(true);

        $args = $method->invoke($this->repository, ['posts_per_page' => 5]);

        $this->assertEquals('publish', $args['post_status']);
        $this->assertEquals('post', $args['post_type']);
        $this->assertEquals(5, $args['posts_per_page']);
    }

    /**
     * Test buildArgs() allows overriding defaults.
     */
    public function testBuildArgsAllowsOverridingDefaults(): void
    {
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('buildArgs');
        $method->setAccessible(true);

        $args = $method->invoke($this->repository, ['post_status' => 'draft']);

        $this->assertEquals('draft', $args['post_status']);
    }
}
