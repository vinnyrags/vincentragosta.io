<?php

namespace ChildTheme\Tests\Integration\Providers\Hooks;

use ChildTheme\Providers\Theme\Hooks\SocialIconChoices;
use ChildTheme\Tests\Support\HasContainer;
use ParentTheme\Providers\Contracts\Hook;
use ParentTheme\Providers\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the SocialIconChoices hook.
 */
class SocialIconChoicesTest extends BaseTestCase
{
    use HasContainer;

    private SocialIconChoices $feature;

    public function set_up(): void
    {
        parent::set_up();
        $container = $this->buildTestContainer();
        $this->feature = $container->get(SocialIconChoices::class);
    }

    /**
     * Test that SocialIconChoices implements Registrable.
     */
    public function testImplementsRegistrable(): void
    {
        $this->assertInstanceOf(Registrable::class, $this->feature);
    }

    /**
     * Test that SocialIconChoices implements Hook (always-active).
     */
    public function testImplementsHook(): void
    {
        $this->assertInstanceOf(Hook::class, $this->feature);
    }

    /**
     * Test that register method adds the ACF filter.
     */
    public function testRegisterAddsAcfFilter(): void
    {
        $this->feature->register();

        $this->assertGreaterThan(
            0,
            has_filter('acf/load_field/key=field_social_icon', [$this->feature, 'populateChoices'])
        );
    }

    /**
     * Test that populateChoices returns field with choices key.
     */
    public function testPopulateChoicesReturnsFieldWithChoices(): void
    {
        $field = ['type' => 'select', 'choices' => []];
        $result = $this->feature->populateChoices($field);

        $this->assertArrayHasKey('choices', $result);
        $this->assertIsArray($result['choices']);
    }

    /**
     * Test that populateChoices preserves other field properties.
     */
    public function testPopulateChoicesPreservesFieldProperties(): void
    {
        $field = [
            'type' => 'select',
            'choices' => [],
            'required' => 1,
            'label' => 'Icon',
        ];
        $result = $this->feature->populateChoices($field);

        $this->assertEquals('select', $result['type']);
        $this->assertEquals(1, $result['required']);
        $this->assertEquals('Icon', $result['label']);
    }
}
