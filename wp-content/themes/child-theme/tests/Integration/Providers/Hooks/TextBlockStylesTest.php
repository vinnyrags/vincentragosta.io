<?php

namespace ChildTheme\Tests\Integration\Providers\Hooks;

use ChildTheme\Providers\Theme\Hooks\TextBlockStyles;
use Mythus\Contracts\Hook;
use Mythus\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the TextBlockStyles hook.
 */
class TextBlockStylesTest extends BaseTestCase
{
    private TextBlockStyles $hook;

    public function set_up(): void
    {
        parent::set_up();
        $this->hook = new TextBlockStyles();
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

    public function testMutedStyleIsRegisteredForParagraph(): void
    {
        $this->hook->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()
            ->get_registered_styles_for_block('core/paragraph');

        $this->assertIsArray($styles);
        $this->assertArrayHasKey('muted', $styles);
        $this->assertEquals('Muted', $styles['muted']['label']);
    }

    public function testMutedStyleIsRegisteredForList(): void
    {
        $this->hook->registerStyles();

        $styles = \WP_Block_Styles_Registry::get_instance()
            ->get_registered_styles_for_block('core/list');

        $this->assertIsArray($styles);
        $this->assertArrayHasKey('muted', $styles);
        $this->assertEquals('Muted', $styles['muted']['label']);
    }
}
