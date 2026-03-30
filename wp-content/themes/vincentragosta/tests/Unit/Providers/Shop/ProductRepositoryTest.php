<?php

namespace ChildTheme\Tests\Unit\Providers\Shop;

use ChildTheme\Providers\Shop\ProductPost;
use ChildTheme\Providers\Shop\ProductRepository;
use IX\Repositories\Repository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ProductRepository.
 */
class ProductRepositoryTest extends TestCase
{
    public function testExtendsBaseRepository(): void
    {
        $this->assertTrue(is_subclass_of(ProductRepository::class, Repository::class));
    }

    public function testModelProperty(): void
    {
        $reflection = new \ReflectionClass(ProductRepository::class);
        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        $repo = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals(ProductPost::class, $property->getValue($repo));
    }

    public function testInStockMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductRepository::class);
        $method = $reflection->getMethod('inStock');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());
    }

    public function testByCardTypeMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductRepository::class);
        $method = $reflection->getMethod('byCardType');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());
    }

    public function testAllByPriceMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductRepository::class);
        $method = $reflection->getMethod('allByPrice');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('array', (string) $method->getReturnType());
    }

    public function testFindByPriceIdMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductRepository::class);
        $method = $reflection->getMethod('findByPriceId');

        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('string', (string) $params[0]->getType());
    }
}
