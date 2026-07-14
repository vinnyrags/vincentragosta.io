<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\ShopProvider;
use Mythus\Hooks\CacheInvalidation;
use Mythus\Support\Cache\RevalidationWebhookDriver;

/**
 * Auto-revalidates the headless itzenzo.tv storefront when a card or product
 * changes in WordPress, so the catalog reflects an edit within seconds instead
 * of waiting on itzenzo's ISR window — today the only trigger is the manual
 * `make revalidate`.
 *
 * Fires on the platform's content-changed event (save / scheduled-publish /
 * delete / trash) for the `card` and `product` post types only, and POSTs the
 * affected Next paths to itzenzo's /api/revalidate. Non-blocking and INERT until
 * ITZENZO_REVALIDATION_SECRET is defined (per-env, wp-config-env.php) — mirrors
 * ActivityWebhook. The secret must match itzenzo's own REVALIDATION_SECRET.
 *
 * Note: stock/price edits go through update_post_meta (no save_post), so they
 * are still covered by the sync scripts' `make revalidate`; this hook covers
 * CMS editor saves. A later slice can add an explicit producer for meta-only
 * writes.
 */
final class ItzenzoCacheInvalidation extends CacheInvalidation
{
    /** @return string[] */
    protected function postTypes(): array
    {
        return ['card', 'product'];
    }

    /** @return \Mythus\Contracts\CacheDriver[] */
    protected function drivers(): array
    {
        $secret = defined('ITZENZO_REVALIDATION_SECRET') ? (string) ITZENZO_REVALIDATION_SECRET : '';

        return [
            new RevalidationWebhookDriver(
                endpoint: ShopProvider::frontendUrl() . '/api/revalidate',
                secret: $secret,
                postTypePathMap: [
                    'card'    => ['/', '/cards', '/collection', '/livestream-shop'],
                    'product' => ['/', '/livestream-shop'],
                ],
            ),
        ];
    }
}
