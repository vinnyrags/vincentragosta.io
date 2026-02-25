<?php

namespace ChildTheme\Tests\Integration\Providers\Hooks;

use ChildTheme\Providers\Theme\Hooks\ParagraphBlockStyles;
use ParentTheme\Providers\Contracts\Hook;
use ParentTheme\Providers\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the ParagraphBlockStyles hook.
 */
class ParagraphBlockStylesTest extends BaseTestCase
{
    private ParagraphBlockStyles $hook;

    public function set_up(): void
    {
        parent::set_up();
        $this->hook = new ParagraphBlockStyles();
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

    public function testMutedStyleIsRegistered(): void
    {
        $this->hook->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()
            ->get_registered_styles_for_block('core/paragraph');

        $this->assertIsArray($styles);
        $this->assertArrayHasKey('muted', $styles);
        $this->assertEquals('Muted', $styles['muted']['label']);
    }
}
