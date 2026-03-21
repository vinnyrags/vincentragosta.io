<?php

namespace IX\Tests\Integration\Providers;

use IX\Providers\Theme\Features\ScrollReveal;
use Mythus\Contracts\Feature;
use Mythus\Contracts\Registrable;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the ScrollReveal feature.
 */
class ScrollRevealTest extends BaseTestCase
{
    private ScrollReveal $feature;

    public function set_up(): void
    {
        parent::set_up();
        $this->feature = new ScrollReveal();
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
}
