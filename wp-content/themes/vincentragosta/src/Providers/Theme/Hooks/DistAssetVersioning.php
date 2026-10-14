<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;

/**
 * Cache-busts this theme's built assets that WordPress versions by its own
 * release number.
 *
 * Assets enqueued through Mythus's AssetManager carry `?ver=<filemtime>`, but
 * block assets registered from block.json (`viewScript`, `style`) get
 * `?ver=<WordPress version>`, which only changes on a core upgrade. Behind
 * Cloudflare that serves a stale file after every deploy — the projects
 * archive ran a 13-day-old projects-view.js against new markup on staging
 * (2026-10-13). This rewrites `ver` to the file's mtime for any script or
 * style served from this theme's dist/ that still carries the core version.
 */
class DistAssetVersioning implements Hook
{
    public function register(): void
    {
        add_filter('script_loader_src', [$this, 'versionSrc'], 10, 2);
        add_filter('style_loader_src', [$this, 'versionSrc'], 10, 2);
    }

    /**
     * @param string|false $src
     * @return string|false
     */
    public function versionSrc(string|false $src, string $handle = ''): string|false
    {
        if (!is_string($src) || $src === '') {
            return $src;
        }

        $distUri = $this->distUri();
        if (!str_starts_with($src, $distUri)) {
            return $src;
        }

        $query = (string) parse_url($src, PHP_URL_QUERY);
        parse_str($query, $params);
        $version = $params['ver'] ?? null;

        if ($version !== null && $version !== $this->coreVersion()) {
            return $src;
        }

        $relative = (string) strtok(substr($src, strlen($distUri)), '?');
        $file     = $this->distPath() . $relative;

        if ($relative === '' || str_contains($relative, '..') || !is_file($file)) {
            return $src;
        }

        return add_query_arg('ver', (string) filemtime($file), remove_query_arg('ver', $src));
    }

    protected function distUri(): string
    {
        return trailingslashit(get_stylesheet_directory_uri()) . 'dist/';
    }

    protected function distPath(): string
    {
        return trailingslashit(get_stylesheet_directory()) . 'dist/';
    }

    protected function coreVersion(): string
    {
        return (string) get_bloginfo('version');
    }
}
