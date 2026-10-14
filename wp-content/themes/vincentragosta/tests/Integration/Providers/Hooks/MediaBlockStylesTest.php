<?php

namespace ChildTheme\Tests\Integration\Providers\Hooks;

use ChildTheme\Providers\Theme\Hooks\MediaBlockStyles;
use Mythus\Contracts\Hook;
use Mythus\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the MediaBlockStyles hook.
 */
class MediaBlockStylesTest extends BaseTestCase
{
    private MediaBlockStyles $feature;

    public function set_up(): void
    {
        parent::set_up();
        $this->feature = new MediaBlockStyles();
    }

    /**
     * Test that MediaBlockStyles implements Registrable.
     */
    public function testImplementsRegistrable(): void
    {
        $this->assertInstanceOf(Registrable::class, $this->feature);
    }

    /**
     * Test that MediaBlockStyles implements Hook (always-active).
     */
    public function testImplementsHook(): void
    {
        $this->assertInstanceOf(Hook::class, $this->feature);
    }

    /**
     * Test that register method adds init action.
     */
    public function testRegisterAddsInitAction(): void
    {
        $this->feature->register();

        $this->assertGreaterThan(
            0,
            has_action('init', [$this->feature, 'registerStyles'])
        );
    }

    /**
     * Test that registerStyles method exists.
     */
    public function testRegisterStylesMethodExists(): void
    {
        $this->assertTrue(method_exists($this->feature, 'registerStyles'));
    }

    /**
     * Test that the animated block style is registered after calling registerStyles.
     */
    public function testAnimatedStyleIsRegistered(): void
    {
        $this->feature->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block('core/cover');

        $this->assertIsArray($styles);
        $this->assertArrayHasKey('animated', $styles);
        $this->assertEquals('Animated Background', $styles['animated']['label']);
    }

    /**
     * Test that the paper style is registered for core/image.
     */
    public function testPaperStyleIsRegisteredForImage(): void
    {
        $this->feature->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block('core/image');

        $this->assertArrayHasKey('paper', $styles);
        $this->assertEquals('Paper', $styles['paper']['label']);
    }
}
