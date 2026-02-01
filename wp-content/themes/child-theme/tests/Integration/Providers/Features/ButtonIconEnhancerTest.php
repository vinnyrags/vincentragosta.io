<?php

namespace ChildTheme\Tests\Integration\Providers\Features;

use ChildTheme\Providers\ThemeService\Features\ButtonIconEnhancer;
use ParentTheme\Providers\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the ButtonIconEnhancer feature.
 */
class ButtonIconEnhancerTest extends BaseTestCase
{
    private ButtonIconEnhancer $feature;

    public function set_up(): void
    {
        parent::set_up();
        $this->feature = new ButtonIconEnhancer();
    }

    /**
     * Test that ButtonIconEnhancer implements Registrable.
     */
    public function testImplementsRegistrable(): void
    {
        $this->assertInstanceOf(Registrable::class, $this->feature);
    }

    /**
     * Test that register method adds the render filter.
     */
    public function testRegisterAddsRenderFilter(): void
    {
        $this->feature->register();

        $this->assertGreaterThan(
            0,
            has_filter('render_block_core/button', [$this->feature, 'render'])
        );
    }

    /**
     * Test that render method exists.
     */
    public function testRenderMethodExists(): void
    {
        $this->assertTrue(method_exists($this->feature, 'render'));
    }

    /**
     * Test that render returns unmodified content when no icon selected.
     */
    public function testRenderReturnsUnmodifiedContentWithoutIcon(): void
    {
        $content = '<div class="wp-block-button"><a class="wp-block-button__link">Click me</a></div>';
        $block = [
            'blockName' => 'core/button',
            'attrs' => [],
        ];

        $result = $this->feature->render($content, $block);

        $this->assertEquals($content, $result);
    }

    /**
     * Test that render returns unmodified content for non-button blocks.
     */
    public function testRenderReturnsUnmodifiedContentForNonButtonBlock(): void
    {
        $content = '<p>Some paragraph</p>';
        $block = [
            'blockName' => 'core/paragraph',
            'attrs' => ['selectedIcon' => 'arrow-right'],
        ];

        $result = $this->feature->render($content, $block);

        $this->assertEquals($content, $result);
    }

    /**
     * Test that render returns unmodified content when block name is not set.
     */
    public function testRenderReturnsUnmodifiedContentWithoutBlockName(): void
    {
        $content = '<div class="wp-block-button"><a class="wp-block-button__link">Click me</a></div>';
        $block = [
            'attrs' => ['selectedIcon' => 'arrow-right'],
        ];

        $result = $this->feature->render($content, $block);

        $this->assertEquals($content, $result);
    }

    /**
     * Test that render returns unmodified content when selectedIcon is empty.
     */
    public function testRenderReturnsUnmodifiedContentWithEmptyIcon(): void
    {
        $content = '<div class="wp-block-button"><a class="wp-block-button__link">Click me</a></div>';
        $block = [
            'blockName' => 'core/button',
            'attrs' => ['selectedIcon' => ''],
        ];

        $result = $this->feature->render($content, $block);

        $this->assertEquals($content, $result);
    }
}
