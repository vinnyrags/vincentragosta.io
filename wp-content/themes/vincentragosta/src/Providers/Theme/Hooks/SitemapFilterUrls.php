<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;

/**
 * Keeps filter views out of the Yoast sitemap.
 *
 * TagTermLinkRewrite and CategoryTermLinkRewrite turn term links into filtered views
 * (/nous-signal/?tag=x, /projects/?category=x) whose canonical is the unfiltered page.
 * Listing them in the sitemap contradicts that canonical, which Search Console reports as
 * "Alternate page with proper canonical tag" and "Duplicate, Google chose different
 * canonical". Real term archives (e.g. /category/anime/) keep their entries.
 */
class SitemapFilterUrls implements Hook
{
    public function register(): void
    {
        add_filter('wpseo_sitemap_exclude_taxonomy', [$this, 'excludeTaxonomy'], 10, 2);
        add_filter('wpseo_sitemap_entry', [$this, 'dropFilterUrl'], 10, 3);
    }

    /**
     * Every tag link is a filter view, so the tag sitemap would be empty: drop it whole.
     *
     * @param bool   $exclude  Whether Yoast already excludes it.
     * @param string $taxonomy The taxonomy slug.
     */
    public function excludeTaxonomy(bool $exclude, string $taxonomy): bool
    {
        return $exclude || $taxonomy === 'post_tag';
    }

    /**
     * Drop any sitemap entry whose URL carries a query string — a filter view, not a page.
     *
     * @param array<string, mixed>|false $url    The entry; `loc` is its URL.
     * @param string                     $type   'post', 'term' or 'user'.
     * @param mixed                      $object The post, term or user.
     * @return array<string, mixed>|false
     */
    public function dropFilterUrl(array|false $url, string $type, mixed $object): array|false
    {
        if ($url === false || !isset($url['loc']) || !is_string($url['loc'])) {
            return $url;
        }

        return str_contains($url['loc'], '?') ? false : $url;
    }
}
