<?php

namespace ChildTheme\Tests\Integration\Providers\Features;

use ChildTheme\Providers\Theme\Features\CoverBlockStyles;
use ParentTheme\Providers\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the CoverBlockStyles feature.
 */
class CoverBlockStylesTest extends BaseTestCase
{
    private CoverBlockStyles $feature;

    public function set_up(): void
    {
        parent::set_up();
        $this->feature = new CoverBlockStyles();
    }

    /**
     * Test that CoverBlockStyles implements Registrable.
     */
    public function testImplementsRegistrable(): void
    {
        $this->assertInstanceOf(Registrable::class, $this->feature);
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
}
