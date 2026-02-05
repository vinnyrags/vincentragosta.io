<?php

namespace ParentTheme\Tests\Unit\Providers\Support\Feature;

use ParentTheme\Providers\Contracts\Registrable;
use ParentTheme\Providers\Support\Feature\FeatureManager;
use WorDBless\BaseTestCase;

/**
 * Concrete test feature for registerAll tests.
 */
class StubFeatureA implements Registrable
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
    }
}

class StubFeatureB implements Registrable
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
    }
}

class StubFeatureDisabled implements Registrable
{
    public static bool $registered = false;

    public function register(): void
    {
        self::$registered = true;
    }
}

/**
 * Unit tests for the FeatureManager class.
 */
class FeatureManagerTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        StubFeatureA::$registered = false;
        StubFeatureB::$registered = false;
        StubFeatureDisabled::$registered = false;
    }

    /**
     * Test normalize converts indexed entries to [class => true].
     */
    public function testNormalizeIndexedEntries(): void
    {
        $result = FeatureManager::normalize([
            'App\Features\FeatureA',
            'App\Features\FeatureB',
        ]);

        $this->assertSame([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => true,
        ], $result);
    }

    /**
     * Test normalize preserves associative false entries.
     */
    public function testNormalizeAssociativeFalseEntries(): void
    {
        $result = FeatureManager::normalize([
            'App\Features\FeatureA' => false,
        ]);

        $this->assertSame([
            'App\Features\FeatureA' => false,
        ], $result);
    }

    /**
     * Test normalize handles mixed arrays.
     */
    public function testNormalizeMixedArray(): void
    {
        $result = FeatureManager::normalize([
            'App\Features\FeatureA',
            'App\Features\FeatureB' => false,
            'App\Features\FeatureC',
        ]);

        $this->assertSame([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => false,
            'App\Features\FeatureC' => true,
        ], $result);
    }

    /**
     * Test normalize with empty array.
     */
    public function testNormalizeEmptyArray(): void
    {
        $this->assertSame([], FeatureManager::normalize([]));
    }

    /**
     * Test getEnabled returns all features when none are disabled.
     */
    public function testGetEnabledReturnsAllWhenNoneDisabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => true,
        ]);

        $this->assertSame([
            'App\Features\FeatureA',
            'App\Features\FeatureB',
        ], $manager->getEnabled());
    }

    /**
     * Test getEnabled excludes features set to false.
     */
    public function testGetEnabledExcludesDisabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => false,
            'App\Features\FeatureC' => true,
        ]);

        $this->assertSame([
            'App\Features\FeatureA',
            'App\Features\FeatureC',
        ], array_values($manager->getEnabled()));
    }

    /**
     * Test isEnabled returns true for enabled features.
     */
    public function testIsEnabledReturnsTrueForEnabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
        ]);

        $this->assertTrue($manager->isEnabled('App\Features\FeatureA'));
    }

    /**
     * Test isEnabled returns false for disabled features.
     */
    public function testIsEnabledReturnsFalseForDisabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => false,
        ]);

        $this->assertFalse($manager->isEnabled('App\Features\FeatureA'));
    }

    /**
     * Test isEnabled returns false for unknown features.
     */
    public function testIsEnabledReturnsFalseForUnknown(): void
    {
        $manager = new FeatureManager([]);

        $this->assertFalse($manager->isEnabled('App\Features\Unknown'));
    }

    /**
     * Test getDisabled returns only the false entries.
     */
    public function testGetDisabledReturnsOnlyFalseEntries(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
            'App\Features\FeatureB' => false,
            'App\Features\FeatureC' => false,
        ]);

        $this->assertSame([
            'App\Features\FeatureB',
            'App\Features\FeatureC',
        ], array_values($manager->getDisabled()));
    }

    /**
     * Test getDisabled returns empty array when all enabled.
     */
    public function testGetDisabledReturnsEmptyWhenAllEnabled(): void
    {
        $manager = new FeatureManager([
            'App\Features\FeatureA' => true,
        ]);

        $this->assertSame([], $manager->getDisabled());
    }

    /**
     * Test registerAll instantiates and registers enabled features only.
     */
    public function testRegisterAllRegistersEnabledOnly(): void
    {
        $manager = new FeatureManager([
            StubFeatureA::class => true,
            StubFeatureB::class => true,
            StubFeatureDisabled::class => false,
        ]);

        $manager->registerAll();

        $this->assertTrue(StubFeatureA::$registered);
        $this->assertTrue(StubFeatureB::$registered);
        $this->assertFalse(StubFeatureDisabled::$registered);
    }

    /**
     * Test registerAll with empty features does nothing.
     */
    public function testRegisterAllWithEmptyFeatures(): void
    {
        $manager = new FeatureManager([]);
        $manager->registerAll();

        $this->assertTrue(true);
    }
}
