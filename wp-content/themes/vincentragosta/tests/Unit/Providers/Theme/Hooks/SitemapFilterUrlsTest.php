<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Unit\Providers\Theme\Hooks;

use ChildTheme\Providers\Theme\Hooks\SitemapFilterUrls;
use Mythus\Contracts\Hook;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the SitemapFilterUrls hook.
 */
class SitemapFilterUrlsTest extends TestCase
{
    public function testImplementsHookInterface(): void
    {
        $this->assertTrue(is_subclass_of(SitemapFilterUrls::class, Hook::class));
    }

    public function testRegisterAddsBothYoastFilters(): void
    {
        $hook = new SitemapFilterUrls();
        $hook->register();

        $this->assertIsInt(has_filter('wpseo_sitemap_exclude_taxonomy', [$hook, 'excludeTaxonomy']));
        $this->assertIsInt(has_filter('wpseo_sitemap_entry', [$hook, 'dropFilterUrl']));
    }

    public function testExcludesTheTagTaxonomyOnly(): void
    {
        $hook = new SitemapFilterUrls();

        $this->assertTrue($hook->excludeTaxonomy(false, 'post_tag'));
        $this->assertFalse($hook->excludeTaxonomy(false, 'category'));
        $this->assertTrue($hook->excludeTaxonomy(true, 'category'), 'an existing exclusion is kept');
    }

    public function testDropsEntriesWithAQueryString(): void
    {
        $hook = new SitemapFilterUrls();

        $this->assertFalse($hook->dropFilterUrl(['loc' => 'https://example.com/projects/?category=agency'], 'term', null));
    }

    public function testKeepsRealArchivesAndPages(): void
    {
        $hook = new SitemapFilterUrls();
        $entry = ['loc' => 'https://example.com/category/anime/'];

        $this->assertSame($entry, $hook->dropFilterUrl($entry, 'term', null));
        $this->assertFalse($hook->dropFilterUrl(false, 'post', null), 'an already-dropped entry stays dropped');
    }
}
