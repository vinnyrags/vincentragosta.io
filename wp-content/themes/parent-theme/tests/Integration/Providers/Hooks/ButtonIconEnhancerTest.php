<?php

namespace ParentTheme\Tests\Integration\Providers\Hooks;

use ParentTheme\Providers\Theme\Hooks\ButtonIconEnhancer;
use ParentTheme\Tests\Support\HasContainer;
use Mythus\Contracts\Hook;
use Mythus\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the ButtonIconEnhancer hook.
 */
class ButtonIconEnhancerTest extends BaseTestCase
{
    use HasContainer;

    private ButtonIconEnhancer $feature;

    public function set_up(): void
    {
        parent::set_up();
        $container = $this->buildTestContainer();
        $this->feature = $container->get(ButtonIconEnhancer::class);
    }

    /**
     * Test that ButtonIconEnhancer implements Registrable.
     */
    public function testImplementsRegistrable(): void
    {
        $this->assertInstanceOf(Registrable::class, $this->feature);
    }

    /**
     * Test that ButtonIconEnhancer implements Hook (always-active).
     */
    public function testImplementsHook(): void
    {
        $this->assertInstanceOf(Hook::class, $this->feature);
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

    /**
     * Test that render returns unmodified content when icon doesn't exist.
     *
     * Note: Positive icon injection tests require the actual theme directory
     * to be accessible via get_stylesheet_directory(). In test environment,
     * icons won't be found, so we verify the graceful fallback behavior.
     */
    public function testRenderReturnsUnmodifiedContentForNonexistentIcon(): void
    {
        $content = '<div class="wp-block-button"><a class="wp-block-button__link">Click me</a></div>';
        $block = [
            'blockName' => 'core/button',
            'attrs' => [
                'selectedIcon' => 'definitely-nonexistent-icon-xyz',
                'iconPosition' => 'right',
            ],
        ];

        $result = $this->feature->render($content, $block);

        $this->assertEquals($content, $result);
    }

    /**
     * Test that render handles malformed HTML gracefully when icon not found.
     */
    public function testRenderHandlesMalformedHtmlGracefully(): void
    {
        $content = '<div class="wp-block-button"><a class="wp-block-button__link">Click me</div>';
        $block = [
            'blockName' => 'core/button',
            'attrs' => [
                'selectedIcon' => 'nonexistent-icon',
                'iconPosition' => 'right',
            ],
        ];

        $result = $this->feature->render($content, $block);

        // When icon not found, should return original content unchanged
        $this->assertEquals($content, $result);
    }

    /**
     * Test that shouldEnhance correctly identifies button blocks with icons.
     */
    public function testShouldEnhanceReturnsTrueForValidButtonWithIcon(): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $method = $reflection->getMethod('shouldEnhance');
        $method->setAccessible(true);

        $block = [
            'blockName' => 'core/button',
            'attrs' => ['selectedIcon' => 'arrow'],
        ];

        $this->assertTrue($method->invoke($this->feature, $block));
    }

    /**
     * Test that shouldEnhance returns false for non-button blocks.
     */
    public function testShouldEnhanceReturnsFalseForNonButtonBlock(): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $method = $reflection->getMethod('shouldEnhance');
        $method->setAccessible(true);

        $block = [
            'blockName' => 'core/paragraph',
            'attrs' => ['selectedIcon' => 'arrow'],
        ];

        $this->assertFalse($method->invoke($this->feature, $block));
    }

    /**
     * Test that shouldEnhance returns false when no icon selected.
     */
    public function testShouldEnhanceReturnsFalseWithoutIcon(): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $method = $reflection->getMethod('shouldEnhance');
        $method->setAccessible(true);

        $block = [
            'blockName' => 'core/button',
            'attrs' => [],
        ];

        $this->assertFalse($method->invoke($this->feature, $block));
    }

    /**
     * Test that shouldEnhance returns false when selectedIcon is empty.
     */
    public function testShouldEnhanceReturnsFalseWithEmptyIcon(): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $method = $reflection->getMethod('shouldEnhance');
        $method->setAccessible(true);

        $block = [
            'blockName' => 'core/button',
            'attrs' => ['selectedIcon' => ''],
        ];

        $this->assertFalse($method->invoke($this->feature, $block));
    }

    /**
     * Test that shouldEnhance returns false when blockName is missing.
     */
    public function testShouldEnhanceReturnsFalseWithoutBlockName(): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $method = $reflection->getMethod('shouldEnhance');
        $method->setAccessible(true);

        $block = [
            'attrs' => ['selectedIcon' => 'arrow'],
        ];

        $this->assertFalse($method->invoke($this->feature, $block));
    }

    /**
     * Test that createDom handles valid HTML.
     */
    public function testCreateDomReturnsDocumentForValidHtml(): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $method = $reflection->getMethod('createDom');
        $method->setAccessible(true);

        $result = $method->invoke($this->feature, '<div>test</div>');

        $this->assertInstanceOf(\DOMDocument::class, $result);
    }

    /**
     * Test that getInnerHtml extracts content from wrapper.
     */
    public function testGetInnerHtmlExtractsContent(): void
    {
        $reflection = new \ReflectionClass($this->feature);
        $createDom = $reflection->getMethod('createDom');
        $createDom->setAccessible(true);
        $getInnerHtml = $reflection->getMethod('getInnerHtml');
        $getInnerHtml->setAccessible(true);

        $dom = $createDom->invoke($this->feature, '<p>Hello World</p>');
        $result = $getInnerHtml->invoke($this->feature, $dom);

        $this->assertStringContainsString('Hello World', $result);
        $this->assertStringContainsString('<p>', $result);
    }
}