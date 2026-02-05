<?php

namespace ParentTheme\Tests\Unit\Providers\Support\Block;

use WorDBless\BaseTestCase;
use ParentTheme\Providers\Support\Block\BlockManager;

/**
 * Unit tests for the BlockManager class.
 */
class BlockManagerTest extends BaseTestCase
{
    /**
     * Test constructor stores blocks path.
     */
    public function testGetBlocksPathReturnsConstructorValue(): void
    {
        $manager = new BlockManager('/path/to/blocks', 'https://example.com/blocks', '/path/to/dist', 'https://example.com/dist');

        $this->assertEquals('/path/to/blocks', $manager->getBlocksPath());
    }

    /**
     * Test constructor stores blocks URI.
     */
    public function testGetBlocksUriReturnsConstructorValue(): void
    {
        $manager = new BlockManager('/path/to/blocks', 'https://example.com/blocks', '/path/to/dist', 'https://example.com/dist');

        $this->assertEquals('https://example.com/blocks', $manager->getBlocksUri());
    }

    /**
     * Test getBlocks returns empty array by default.
     */
    public function testGetBlocksReturnsEmptyArrayByDefault(): void
    {
        $manager = new BlockManager('/path/to/blocks', 'https://example.com/blocks', '/path/to/dist', 'https://example.com/dist');

        $this->assertEmpty($manager->getBlocks());
    }

    /**
     * Test getBlocks returns the blocks passed to constructor.
     */
    public function testGetBlocksReturnsConstructorBlocks(): void
    {
        $blocks = ['my-block', 'another-block'];
        $manager = new BlockManager('/path/to/blocks', 'https://example.com/blocks', '/path/to/dist', 'https://example.com/dist', $blocks);

        $this->assertEquals($blocks, $manager->getBlocks());
    }

    /**
     * Test registerBlocks skips blocks without block.json.
     */
    public function testRegisterBlocksSkipsWithoutBlockJson(): void
    {
        $blocksPath = sys_get_temp_dir() . '/block-manager-test-blocks';
        @mkdir($blocksPath . '/my-block', 0777, true);

        $manager = new BlockManager($blocksPath, 'https://example.com/blocks', '/path/to/dist', 'https://example.com/dist', ['my-block']);
        $manager->registerBlocks();

        // No block.json means register_block_type was not called — no error thrown
        $this->assertTrue(true);

        // Clean up
        @rmdir($blocksPath . '/my-block');
        @rmdir($blocksPath);
    }

    /**
     * Test enqueueEditorScript skips when file doesn't exist.
     */
    public function testEnqueueEditorScriptSkipsWhenFileMissing(): void
    {
        $manager = new BlockManager('/path/to/blocks', 'https://example.com/blocks', '/nonexistent/dist', 'https://example.com/dist');

        $manager->enqueueEditorScript('test-editor-script', 'editor.js');

        $this->assertFalse(wp_script_is('test-editor-script', 'enqueued'));
    }

    /**
     * Test enqueueEditorScript enqueues with default dependencies when file exists.
     */
    public function testEnqueueEditorScriptEnqueuesWithDefaultDeps(): void
    {
        $distPath = sys_get_temp_dir() . '/block-manager-test-dist';
        @mkdir($distPath . '/js', 0777, true);
        file_put_contents($distPath . '/js/editor.js', '// editor');

        $manager = new BlockManager('/path/to/blocks', 'https://example.com/blocks', $distPath, 'https://example.com/dist');
        $manager->enqueueEditorScript('test-editor-default-deps', 'editor.js');

        $this->assertTrue(wp_script_is('test-editor-default-deps', 'enqueued'));

        $deps = wp_scripts()->query('test-editor-default-deps')->deps;
        $this->assertContains('wp-blocks', $deps);
        $this->assertContains('wp-element', $deps);
        $this->assertContains('wp-block-editor', $deps);

        // Clean up
        @unlink($distPath . '/js/editor.js');
        @rmdir($distPath . '/js');
        @rmdir($distPath);
    }

    /**
     * Test enqueueEditorScript merges custom deps with defaults.
     */
    public function testEnqueueEditorScriptMergesCustomDeps(): void
    {
        $distPath = sys_get_temp_dir() . '/block-manager-test-dist';
        @mkdir($distPath . '/js', 0777, true);
        file_put_contents($distPath . '/js/editor.js', '// editor');

        $manager = new BlockManager('/path/to/blocks', 'https://example.com/blocks', $distPath, 'https://example.com/dist');
        $manager->enqueueEditorScript('test-editor-custom-deps', 'editor.js', ['custom-dep']);

        $this->assertTrue(wp_script_is('test-editor-custom-deps', 'enqueued'));

        $deps = wp_scripts()->query('test-editor-custom-deps')->deps;
        $this->assertContains('custom-dep', $deps);
        $this->assertContains('wp-blocks', $deps);
        $this->assertContains('wp-element', $deps);

        // Clean up
        @unlink($distPath . '/js/editor.js');
        @rmdir($distPath . '/js');
        @rmdir($distPath);
    }

    /**
     * Test initializeHooks does nothing when blocks array is empty.
     */
    public function testInitializeHooksSkipsWhenNoBlocks(): void
    {
        $manager = new BlockManager('/path/to/blocks', 'https://example.com/blocks', '/path/to/dist', 'https://example.com/dist');
        $provider = new \stdClass();

        $manager->initializeHooks($provider);

        $this->assertFalse(has_action('init', [$manager, 'registerBlocks']));
    }

    /**
     * Test initializeHooks registers hooks when blocks are provided.
     */
    public function testInitializeHooksRegistersHooksWithBlocks(): void
    {
        $manager = new BlockManager('/path/to/blocks', 'https://example.com/blocks', '/path/to/dist', 'https://example.com/dist', ['my-block']);
        $provider = new \stdClass();

        $manager->initializeHooks($provider);

        $this->assertNotFalse(has_action('init', [$manager, 'registerBlocks']));
    }
}
