<?php

namespace ParentTheme\Tests\Integration\Providers;

use ParentTheme\Providers\ThemeService\Features\DisableComments;
use ParentTheme\Providers\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the DisableComments feature.
 */
class DisableCommentsTest extends BaseTestCase
{
    private DisableComments $feature;

    public function set_up(): void
    {
        parent::set_up();
        $this->feature = new DisableComments();
    }

    /**
     * Test that DisableComments implements Registrable.
     */
    public function testImplementsRegistrable(): void
    {
        $this->assertInstanceOf(Registrable::class, $this->feature);
    }

    /**
     * Test that register method adds WordPress hooks.
     */
    public function testRegisterAddsHooks(): void
    {
        $this->feature->register();

        // Check that actions were added
        $this->assertGreaterThan(
            0,
            has_action('init', [$this->feature, 'removePostTypeSupport'])
        );

        $this->assertGreaterThan(
            0,
            has_action('admin_menu', [$this->feature, 'removeAdminMenu'])
        );

        $this->assertGreaterThan(
            0,
            has_action('admin_init', [$this->feature, 'redirectAdminPage'])
        );

        $this->assertGreaterThan(
            0,
            has_action('wp_before_admin_bar_render', [$this->feature, 'removeFromAdminBar'])
        );
    }

    /**
     * Test that register adds comments_open filter.
     */
    public function testRegisterAddsCommentsOpenFilter(): void
    {
        $this->feature->register();

        $this->assertGreaterThan(
            0,
            has_filter('comments_open', '__return_false')
        );
    }

    /**
     * Test that register adds pings_open filter.
     */
    public function testRegisterAddsPingsOpenFilter(): void
    {
        $this->feature->register();

        $this->assertGreaterThan(
            0,
            has_filter('pings_open', '__return_false')
        );
    }

    /**
     * Test that register adds comments_array filter.
     */
    public function testRegisterAddsCommentsArrayFilter(): void
    {
        $this->feature->register();

        $this->assertGreaterThan(
            0,
            has_filter('comments_array', '__return_empty_array')
        );
    }

    /**
     * Test that removePostTypeSupport method exists.
     */
    public function testRemovePostTypeSupportMethodExists(): void
    {
        $this->assertTrue(method_exists($this->feature, 'removePostTypeSupport'));
    }

    /**
     * Test that removeAdminMenu method exists.
     */
    public function testRemoveAdminMenuMethodExists(): void
    {
        $this->assertTrue(method_exists($this->feature, 'removeAdminMenu'));
    }

    /**
     * Test that redirectAdminPage method exists.
     */
    public function testRedirectAdminPageMethodExists(): void
    {
        $this->assertTrue(method_exists($this->feature, 'redirectAdminPage'));
    }

    /**
     * Test that removeFromAdminBar method exists.
     */
    public function testRemoveFromAdminBarMethodExists(): void
    {
        $this->assertTrue(method_exists($this->feature, 'removeFromAdminBar'));
    }
}
