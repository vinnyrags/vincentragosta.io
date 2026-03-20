<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Unit\Providers\Project\Hooks;

use ChildTheme\Providers\Project\Hooks\CategoryTermLinkRewrite;
use Mythus\Contracts\Hook;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the CategoryTermLinkRewrite hook.
 */
class CategoryTermLinkRewriteTest extends TestCase
{
    /**
     * Test that CategoryTermLinkRewrite implements the Hook interface.
     */
    public function testImplementsHookInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(CategoryTermLinkRewrite::class, Hook::class),
        );
    }

    /**
     * Test that register adds the term_link filter.
     */
    public function testRegisterAddsTermLinkFilter(): void
    {
        $rewrite = new CategoryTermLinkRewrite();
        $rewrite->register();

        $this->assertIsInt(has_filter('term_link', [$rewrite, 'rewriteCategoryLink']));
    }

    /**
     * Test that non-category taxonomies pass through unchanged.
     */
    public function testNonCategoryTaxonomyPassesThrough(): void
    {
        $rewrite = new CategoryTermLinkRewrite();
        $term = new \WP_Term((object) ['slug' => 'test', 'term_id' => 1]);
        $originalLink = 'https://example.com/tag/test/';

        $result = $rewrite->rewriteCategoryLink($originalLink, $term, 'post_tag');

        $this->assertEquals($originalLink, $result);
    }
}
