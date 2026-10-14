<?php

namespace ChildTheme\Tests\Integration\Providers\Hooks;

use ChildTheme\Providers\Theme\Hooks\ContainerBlockStyles;
use Mythus\Contracts\Hook;
use Mythus\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the ContainerBlockStyles hook.
 */
class ContainerBlockStylesTest extends BaseTestCase
{
    private ContainerBlockStyles $hook;

    public function set_up(): void
    {
        parent::set_up();
        $this->hook = new ContainerBlockStyles();
    }

    public function testImplementsRegistrable(): void
    {
        $this->assertInstanceOf(Registrable::class, $this->hook);
    }

    public function testImplementsHook(): void
    {
        $this->assertInstanceOf(Hook::class, $this->hook);
    }

    public function testRegisterAddsInitAction(): void
    {
        $this->hook->register();

        $this->assertGreaterThan(
            0,
            has_action('init', [$this->hook, 'registerStyles'])
        );
    }

    public function testRegisterStylesMethodExists(): void
    {
        $this->assertTrue(method_exists($this->hook, 'registerStyles'));
    }

    public function testDarkStyleIsRegisteredForGroup(): void
    {
        $this->hook->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()
            ->get_registered_styles_for_block('core/group');

        $this->assertIsArray($styles);
        $this->assertArrayHasKey('dark', $styles);
        $this->assertEquals('Dark', $styles['dark']['label']);
    }

    public function testDarkStyleIsRegisteredForColumn(): void
    {
        $this->hook->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()
            ->get_registered_styles_for_block('core/column');

        $this->assertIsArray($styles);
        $this->assertArrayHasKey('dark', $styles);
        $this->assertEquals('Dark', $styles['dark']['label']);
    }

    public function testNumberedListStyleIsRegisteredForGroup(): void
    {
        $this->hook->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()
            ->get_registered_styles_for_block('core/group');

        $this->assertIsArray($styles);
        $this->assertArrayHasKey('numbered-list', $styles);
        $this->assertEquals('Numbered List', $styles['numbered-list']['label']);
    }

    public function testPanelAndStatStripStylesAreRegisteredForGroup(): void
    {
        $this->hook->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()
            ->get_registered_styles_for_block('core/group');

        $this->assertArrayHasKey('panel', $styles);
        $this->assertEquals('Panel', $styles['panel']['label']);
        $this->assertArrayHasKey('stat-strip', $styles);
        $this->assertEquals('Stat Strip', $styles['stat-strip']['label']);
    }

    public function testStatStripStyleIsRegisteredForColumns(): void
    {
        $this->hook->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()
            ->get_registered_styles_for_block('core/columns');

        $this->assertArrayHasKey('stat-strip', $styles);
    }

    public function testHomepageLayoutStylesAreRegistered(): void
    {
        $this->hook->registerStyles();
        $registry = \WP_Block_Styles_Registry::get_instance();

        $this->assertArrayHasKey('layers', $registry->get_registered_styles_for_block('core/group'));
        $this->assertArrayHasKey('testimonials', $registry->get_registered_styles_for_block('core/group'));
        $this->assertArrayHasKey('transmission', $registry->get_registered_styles_for_block('core/group'));
        $this->assertArrayHasKey('pipeline', $registry->get_registered_styles_for_block('core/group'));
        $this->assertArrayNotHasKey('pipeline', $registry->get_registered_styles_for_block('core/columns'));
    }
}
