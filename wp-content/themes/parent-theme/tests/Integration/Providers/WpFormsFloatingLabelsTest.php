<?php

namespace ParentTheme\Tests\Integration\Providers;

use ParentTheme\Providers\Theme\Features\WpFormsFloatingLabels;
use ParentTheme\Providers\Contracts\Feature;
use ParentTheme\Providers\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the WpFormsFloatingLabels feature.
 */
class WpFormsFloatingLabelsTest extends BaseTestCase
{
    private WpFormsFloatingLabels $feature;

    public function set_up(): void
    {
        parent::set_up();
        $this->feature = new WpFormsFloatingLabels();
    }

    public function testImplementsRegistrable(): void
    {
        $this->assertInstanceOf(Registrable::class, $this->feature);
    }

    public function testImplementsFeature(): void
    {
        $this->assertInstanceOf(Feature::class, $this->feature);
    }

    public function testRegisterAddsEnqueueAction(): void
    {
        $this->feature->register();

        $this->assertGreaterThan(
            0,
            has_action('wp_enqueue_scripts', [$this->feature, 'enqueueAssets'])
        );
    }

    public function testRegisterAddsEditorEnqueueAction(): void
    {
        $this->feature->register();

        $this->assertGreaterThan(
            0,
            has_action('enqueue_block_assets', [$this->feature, 'enqueueEditorAssets'])
        );
    }
}
