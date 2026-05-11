<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Support;

use WP_Error;
use WP_REST_Request;

/**
 * Terms-of-service acceptance validation + audit-data assembly for
 * checkout endpoints.
 *
 * Every checkout endpoint (CreateCheckoutEndpoint, BundleCheckoutEndpoint,
 * PullBoxCheckoutEndpoint) must call TouAcceptance::validate($request)
 * before creating the Stripe session. The validator:
 *
 *   1. Reads `terms_version` from the request body
 *   2. Rejects with a 400 if missing or doesn't match CURRENT_VERSION
 *   3. Returns an array of audit fields ready to be merged into the
 *      Stripe session `metadata` array (which also propagates to the
 *      PaymentIntent — disputes are filed against the PI)
 *
 * The audit fields land on Stripe (server-stored, ~7-year retention,
 * surfaced directly in Stripe's dispute portal). We deliberately do
 * NOT store a parallel record in a WP table — Stripe IS the source of
 * truth for the transaction, and a separate table would be duplicated
 * data that drifts over time and adds admin burden.
 *
 * CURRENT_VERSION must stay in sync with `TERMS_VERSION` in the
 * frontend `itzenzo.tv/src/lib/terms.ts`. When you bump the legal
 * page version, bump this constant too. (A unit test pins the format
 * but the actual sync is operator-visible — a buyer who sends an
 * outdated version gets a 400 with a "please re-accept" message.)
 */
class TouAcceptance
{
    /**
     * The current terms version. Bump this AND the frontend
     * TERMS_VERSION constant whenever /legal/terms materially changes.
     */
    public const CURRENT_VERSION = '1.1';

    /**
     * Stripe metadata value limit (per key). Strings longer than this
     * are truncated to avoid the entire session create failing. User-
     * agent strings are the only realistic offender; everything else is
     * already well under 500 chars.
     */
    private const STRIPE_METADATA_VALUE_LIMIT = 500;

    /**
     * Validate a checkout request's ToS acceptance and return the
     * audit fields to attach to the Stripe session metadata.
     *
     * On failure returns WP_Error (which endpoints should return
     * directly — this short-circuits the checkout). On success returns
     * a string-string array safe to merge into `$metadata`.
     */
    public static function validate(WP_REST_Request $request): WP_Error|array
    {
        $version = trim((string) $request->get_param('terms_version'));

        if ($version === '') {
            return new WP_Error(
                'terms_not_accepted',
                'Please accept the Terms of Service & Refund Policy before checking out.',
                ['status' => 400]
            );
        }

        if ($version !== self::CURRENT_VERSION) {
            // The buyer is on a stale page (cached HTML from before a
            // terms update). Refresh + re-accept. Return-current keeps
            // the client able to display the right "please accept the
            // current version" message.
            return new WP_Error(
                'terms_version_outdated',
                sprintf(
                    'The Terms of Service have been updated since you opened the page. Refresh and re-accept the current version (v%s) to continue.',
                    self::CURRENT_VERSION
                ),
                [
                    'status'          => 400,
                    'current_version' => self::CURRENT_VERSION,
                    'submitted'       => $version,
                ]
            );
        }

        return [
            'terms_version'     => self::CURRENT_VERSION,
            'terms_accepted_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'terms_accepted_ip' => self::clientIp(),
            'terms_accepted_ua' => self::truncate(
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                self::STRIPE_METADATA_VALUE_LIMIT
            ),
        ];
    }

    /**
     * Pull the real client IP from $_SERVER, accounting for nginx's
     * X-Forwarded-For. The shop sits behind nginx on the DO droplet;
     * REMOTE_ADDR would be 127.0.0.1 on every request without this.
     *
     * Takes the FIRST entry in the XFF chain — that's the original
     * client, before any proxies appended themselves. Trusted because
     * the only thing between the buyer and nginx is Cloudflare /
     * the carrier; the chain inside nginx → PHP-FPM is on localhost.
     */
    private static function clientIp(): string
    {
        $xff = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP)) {
            return $remote;
        }

        return 'unknown';
    }

    private static function truncate(string $value, int $limit): string
    {
        if ($value === '') {
            return 'unknown';
        }
        return strlen($value) > $limit ? substr($value, 0, $limit) : $value;
    }
}
