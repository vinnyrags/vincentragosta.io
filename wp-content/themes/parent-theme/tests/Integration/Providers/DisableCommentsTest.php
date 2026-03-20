<?php

namespace ParentTheme\Tests\Integration\Providers;

use ParentTheme\Providers\Theme\Features\DisableComments;
use Mythus\Contracts\Feature;
use Mythus\Contracts\Registrable;
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
     * Test that DisableComments implements Feature (toggleable).
     */
    public function testImplementsFeature(): void
    {
        $this->assertInstanceOf(Feature::class, $this->feature);
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
     * Test that comments_open filter returns false after registration.
     */
    public function testCommentsOpenFilterReturnsFalse(): void
    {
        $this->feature->register();

        // Filter should return false regardless of the input
        $result = apply_filters('comments_open', true, 123);
        $this->assertFalse($result);

        $result = apply_filters('comments_open', true, 456);
        $this->assertFalse($result);
    }

    /**
     * Test that pings_open filter returns false after registration.
     */
    public function testPingsOpenFilterReturnsFalse(): void
    {
        $this->feature->register();

        $result = apply_filters('pings_open', true, 123);
        $this->assertFalse($result);
    }

    /**
     * Test that comments_array filter returns empty array after registration.
     */
    public function testCommentsArrayFilterReturnsEmptyArray(): void
    {
        $this->feature->register();

        $comments = [
            ['comment_ID' => 1, 'comment_content' => 'Test comment'],
            ['comment_ID' => 2, 'comment_content' => 'Another comment'],
        ];

        $result = apply_filters('comments_array', $comments, 123);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
