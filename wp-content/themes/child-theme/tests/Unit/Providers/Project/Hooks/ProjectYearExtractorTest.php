<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Unit\Providers\Project\Hooks;

use ChildTheme\Providers\Project\Hooks\ProjectYearExtractor;
use Mythus\Contracts\Hook;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ProjectYearExtractor hook.
 */
class ProjectYearExtractorTest extends TestCase
{
    /**
     * Test that ProjectYearExtractor implements the Hook interface.
     */
    public function testImplementsHookInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(ProjectYearExtractor::class, Hook::class),
        );
    }

    /**
     * Test extracting a simple year.
     */
    public function testExtractYearSimple(): void
    {
        $content = <<<HTML
        <p class="is-style-muted has-10-font-size"><strong>YEAR</strong></p>
        <p class="has-30-font-size">2022</p>
        HTML;

        $this->assertEquals('2022', ProjectYearExtractor::extractYear($content));
    }

    /**
     * Test extracting the maximum from multi-year content.
     */
    public function testExtractYearMultiYear(): void
    {
        $content = <<<HTML
        <p class="is-style-muted has-10-font-size"><strong>YEAR</strong></p>
        <p class="has-30-font-size">2018 & 2025</p>
        HTML;

        $this->assertEquals('2025', ProjectYearExtractor::extractYear($content));
    }

    /**
     * Test extracting year with HTML entities.
     */
    public function testExtractYearWithHtmlEntities(): void
    {
        $content = <<<HTML
        <p class="is-style-muted has-10-font-size"><strong>YEAR</strong></p>
        <p class="has-30-font-size">2018 &amp; 2025</p>
        HTML;

        $this->assertEquals('2025', ProjectYearExtractor::extractYear($content));
    }

    /**
     * Test that extractYear returns null when no YEAR label exists.
     */
    public function testExtractYearMissingLabel(): void
    {
        $content = '<p class="has-30-font-size">2022</p>';

        $this->assertNull(ProjectYearExtractor::extractYear($content));
    }

    /**
     * Test that extractYear returns null for empty content.
     */
    public function testExtractYearEmptyContent(): void
    {
        $this->assertNull(ProjectYearExtractor::extractYear(''));
    }

    /**
     * Test that extractYear returns null when value has no year digits.
     */
    public function testExtractYearNoDigits(): void
    {
        $content = <<<HTML
        <p class="is-style-muted has-10-font-size"><strong>YEAR</strong></p>
        <p class="has-30-font-size">Ongoing</p>
        HTML;

        $this->assertNull(ProjectYearExtractor::extractYear($content));
    }

    /**
     * Test that register method adds the save_post_project action.
     */
    public function testRegisterAddsSavePostAction(): void
    {
        $extractor = new ProjectYearExtractor();
        $extractor->register();

        $this->assertIsInt(has_action('save_post_project', [$extractor, 'onSave']));
    }

    /**
     * Test that register method adds the admin_init action.
     */
    public function testRegisterAddsAdminInitAction(): void
    {
        $extractor = new ProjectYearExtractor();
        $extractor->register();

        $this->assertIsInt(has_action('admin_init', [$extractor, 'maybeBackfill']));
    }

    /**
     * Test extraction with real project content structure.
     */
    public function testExtractYearFromFullContent(): void
    {
        $content = <<<HTML
        <!-- wp:group -->
        <div class="wp-block-group">
            <p class="is-style-muted has-10-font-size"><strong>CLIENT</strong></p>
            <p class="has-30-font-size">Acme Corp</p>
            <p class="is-style-muted has-10-font-size"><strong>YEAR</strong></p>
            <p class="has-30-font-size">2024</p>
        </div>
        <!-- /wp:group -->
        HTML;

        $this->assertEquals('2024', ProjectYearExtractor::extractYear($content));
    }

    /**
     * Test META_KEY constant value.
     */
    public function testMetaKeyConstant(): void
    {
        $this->assertEquals('project_year', ProjectYearExtractor::META_KEY);
    }
}
