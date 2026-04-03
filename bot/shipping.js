/**
 * Centralized Shipping Logic
 *
 * All shipping rate lookups, double-charge prevention, and recording
 * flow through this module. Every checkout endpoint and command uses
 * these helpers instead of ad-hoc checks.
 */

import config from './config.js';
import { purchases, shipping, discordLinks } from './db.js';

/**
 * Check if a Discord user is flagged as international.
 */
function isInternational(discordUserId) {
    const row = discordLinks.getCountry.get(discordUserId);
    return row?.country && row.country !== 'US';
}

/**
 * Check if a buyer (by email) is international.
 */
function isInternationalByEmail(email) {
    const row = discordLinks.getCountryByEmail.get(email);
    return row?.country && row.country !== 'US';
}

/**
 * Get the correct shipping rate for a Discord user (in cents).
 */
function getShippingRate(discordUserId) {
    return isInternational(discordUserId)
        ? config.SHIPPING.INTERNATIONAL
        : config.SHIPPING.DOMESTIC;
}

/**
 * Get the shipping rate by email (in cents).
 */
function getShippingRateByEmail(email) {
    return isInternationalByEmail(email)
        ? config.SHIPPING.INTERNATIONAL
        : config.SHIPPING.DOMESTIC;
}

/**
 * Check if a buyer has already paid shipping for the current period.
 *
 * Domestic: checks this week (Mon–Sun).
 * International: checks this month.
 */
function hasShippingCovered(email) {
    const intl = isInternationalByEmail(email);

    if (intl) {
        return !!shipping.hasShippingThisMonth.get(email);
    }

    return !!shipping.hasShippingThisWeek.get(email);
}

/**
 * Check if a Discord user has shipping covered (looks up email first).
 */
function hasShippingCoveredByDiscordId(discordUserId) {
    const link = purchases.getEmailByDiscordId.get(discordUserId);
    if (!link) return false;

    return hasShippingCovered(link.customer_email);
}

/**
 * Record a shipping payment in the unified shipping_payments table.
 */
function recordShipping(email, discordUserId, amount, source) {
    shipping.record.run(email, discordUserId || null, amount, source);
}

/**
 * Get a display label for a shipping rate.
 */
function formatShippingRate(amountCents) {
    return `$${(amountCents / 100).toFixed(2)}`;
}

/**
 * Get the shipping label for a Discord user.
 */
function getShippingLabel(discordUserId) {
    const intl = isInternational(discordUserId);
    const rate = intl ? config.SHIPPING.INTERNATIONAL : config.SHIPPING.DOMESTIC;
    const label = intl ? 'International Shipping' : 'Standard Shipping (US)';
    return { rate, label, isInternational: intl };
}

export {
    isInternational,
    isInternationalByEmail,
    getShippingRate,
    getShippingRateByEmail,
    hasShippingCovered,
    hasShippingCoveredByDiscordId,
    recordShipping,
    formatShippingRate,
    getShippingLabel,
};
