<?php

namespace ParentTheme\Tests\Unit\Providers\Support\Acf;

use ParentTheme\Providers\Support\Acf\AcfManager;
use WorDBless\BaseTestCase;

/**
 * Unit tests for the AcfManager class.
 */
class AcfManagerTest extends BaseTestCase
{
    private string $tempDir;

    public function set_up(): void
    {
        parent::set_up();
        $this->tempDir = sys_get_temp_dir() . '/acf-manager-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    public function tear_down(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir . '/acf-json')) {
            @rmdir($this->tempDir . '/acf-json');
        }
        @rmdir($this->tempDir);
        parent::tear_down();
    }

    /**
     * Test hasAcfJson returns false when directory doesn't exist.
     */
    public function testHasAcfJsonReturnsFalseWithoutDirectory(): void
    {
        $manager = new AcfManager($this->tempDir);

        $this->assertFalse($manager->hasAcfJson());
    }

    /**
     * Test hasAcfJson returns true when directory exists.
     */
    public function testHasAcfJsonReturnsTrueWithDirectory(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $this->assertTrue($manager->hasAcfJson());
    }

    /**
     * Test getAcfJsonPath returns correct path.
     */
    public function testGetAcfJsonPathReturnsCorrectPath(): void
    {
        $manager = new AcfManager($this->tempDir);

        $this->assertEquals($this->tempDir . '/acf-json', $manager->getAcfJsonPath());
    }

    /**
     * Test initializeHooks does nothing without acf-json directory.
     */
    public function testInitializeHooksSkipsWithoutDirectory(): void
    {
        $manager = new AcfManager($this->tempDir);

        $manager->initializeHooks();

        $this->assertFalse(has_filter('acf/settings/load_json', [$manager, 'addLoadPath']));
    }

    /**
     * Test initializeHooks registers load filter with acf-json directory.
     */
    public function testInitializeHooksRegistersLoadFilter(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $manager->initializeHooks();

        $this->assertNotFalse(has_filter('acf/settings/load_json', [$manager, 'addLoadPath']));
    }

    /**
     * Test addLoadPath appends the acf-json path to existing paths.
     */
    public function testAddLoadPathAppendsPath(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $paths = ['/existing/path'];
        $result = $manager->addLoadPath($paths);

        $this->assertCount(2, $result);
        $this->assertEquals('/existing/path', $result[0]);
        $this->assertEquals($this->tempDir . '/acf-json', $result[1]);
    }

    /**
     * Test addLoadPath works with empty paths array.
     */
    public function testAddLoadPathWorksWithEmptyArray(): void
    {
        $manager = new AcfManager($this->tempDir);

        $result = $manager->addLoadPath([]);

        $this->assertCount(1, $result);
        $this->assertEquals($this->tempDir . '/acf-json', $result[0]);
    }

    /**
     * Test registerSavePath does nothing without acf-json directory.
     */
    public function testRegisterSavePathSkipsWithoutDirectory(): void
    {
        $manager = new AcfManager($this->tempDir);

        $manager->registerSavePath();

        $this->assertFalse(has_filter('acf/settings/save_json'));
    }

    /**
     * Test registerSavePath registers save filter with acf-json directory.
     */
    public function testRegisterSavePathRegistersFilter(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $manager->registerSavePath();

        $this->assertNotFalse(has_filter('acf/settings/save_json'));
    }

    /**
     * Test registerSavePath filter returns correct path.
     */
    public function testRegisterSavePathReturnsCorrectPath(): void
    {
        mkdir($this->tempDir . '/acf-json', 0777, true);
        $manager = new AcfManager($this->tempDir);

        $manager->registerSavePath();

        $result = apply_filters('acf/settings/save_json', '/default/path');
        $this->assertEquals($this->tempDir . '/acf-json', $result);
    }
}
