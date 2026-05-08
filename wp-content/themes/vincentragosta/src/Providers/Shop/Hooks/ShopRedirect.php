<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * 301-redirect any /shop URL on vincentragosta.io to itzenzo.tv.
 *
 * The WordPress shop UI was retired when the storefront moved to the
 * headless Next.js frontend at itzenzo.tv. ShopProvider still owns the
 * product CPT, REST endpoints, and ACF fields (the "backend") but
 * renders no public shop pages. Old emails, social posts, and inbound
 * links to /shop/ would otherwise hit a 404 — this hook funnels them
 * to the live storefront instead.
 *
 * Matches /shop, /shop/, and /shop/anything (case-insensitive).
 */
class ShopRedirect implements Hook
{
    private const TARGET_URL = 'https://itzenzo.tv';

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybeRedirect']);
    }

    public function maybeRedirect(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '';

        if (! self::shouldRedirect($path)) {
            return;
        }

        // wp_redirect (not wp_safe_redirect) — itzenzo.tv is an external
        // host but the URL is a hardcoded constant, so the allowed-hosts
        // check would only get in the way.
        wp_redirect(self::TARGET_URL, 301);
        exit;
    }

    /**
     * Pure path matcher — extracted so it can be unit-tested without
     * involving WordPress superglobals or wp_redirect().
     */
    public static function shouldRedirect(string $path): bool
    {
        return (bool) preg_match('#^/shop(/|$)#i', $path);
    }
}
