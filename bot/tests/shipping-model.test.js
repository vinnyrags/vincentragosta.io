/**
 * Tests for the unified shipping model — shipping_payments table,
 * shipping helpers, and international user management.
 */

import { describe, it, expect, beforeEach } from 'vitest';
import { createTestDb, buildStmts } from './setup.js';

let db, stmts;

beforeEach(() => {
    db = createTestDb();
    stmts = buildStmts(db);
});

// =========================================================================
// shipping_payments table
// =========================================================================

describe('shipping_payments table', () => {
    it('records a shipping payment', () => {
        stmts.shipping.record.run('user@test.com', 'discord123', 1000, 'checkout');

        const records = stmts.shipping.getThisWeek.all();
        expect(records).toHaveLength(1);
        expect(records[0].customer_email).toBe('user@test.com');
        expect(records[0].amount).toBe(1000);
        expect(records[0].source).toBe('checkout');
    });

    it('records multiple payments from different sources', () => {
        stmts.shipping.record.run('user1@test.com', 'discord1', 1000, 'checkout');
        stmts.shipping.record.run('user2@test.com', 'discord2', 2500, 'livestream');
        stmts.shipping.record.run('user3@test.com', null, 1000, 'ad-hoc');

        const records = stmts.shipping.getThisWeek.all();
        expect(records).toHaveLength(3);
    });

    it('allows null discord_user_id', () => {
        stmts.shipping.record.run('user@test.com', null, 1000, 'checkout');

        const records = stmts.shipping.getThisWeek.all();
        expect(records).toHaveLength(1);
        expect(records[0].discord_user_id).toBeNull();
    });
});

// =========================================================================
// hasShippingThisWeek / hasShippingThisMonth
// =========================================================================

describe('hasShippingThisWeek', () => {
    it('returns undefined when no shipping paid', () => {
        const result = stmts.shipping.hasShippingThisWeek.get('user@test.com');
        expect(result).toBeUndefined();
    });

    it('returns truthy after shipping is recorded', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout');

        const result = stmts.shipping.hasShippingThisWeek.get('user@test.com');
        expect(result).toBeTruthy();
    });

    it('is email-specific — different emails are independent', () => {
        stmts.shipping.record.run('user1@test.com', 'discord1', 1000, 'checkout');

        expect(stmts.shipping.hasShippingThisWeek.get('user1@test.com')).toBeTruthy();
        expect(stmts.shipping.hasShippingThisWeek.get('user2@test.com')).toBeUndefined();
    });
});

describe('hasShippingThisMonth', () => {
    it('returns undefined when no shipping paid', () => {
        const result = stmts.shipping.hasShippingThisMonth.get('user@test.com');
        expect(result).toBeUndefined();
    });

    it('returns truthy after shipping is recorded', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 2500, 'livestream');

        const result = stmts.shipping.hasShippingThisMonth.get('user@test.com');
        expect(result).toBeTruthy();
    });
});

// =========================================================================
// discord_links — country management
// =========================================================================

describe('discord_links country management', () => {
    it('sets and gets country', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');
        stmts.discordLinks.setCountry.run('CA', 'discord1');

        const row = stmts.discordLinks.getCountry.get('discord1');
        expect(row.country).toBe('CA');
    });

    it('returns null country for new links', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');

        const row = stmts.discordLinks.getCountry.get('discord1');
        expect(row.country).toBeNull();
    });

    it('can revert to US', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');
        stmts.discordLinks.setCountry.run('CA', 'discord1');
        stmts.discordLinks.setCountry.run('US', 'discord1');

        const row = stmts.discordLinks.getCountry.get('discord1');
        expect(row.country).toBe('US');
    });

    it('getCountryByEmail looks up via email', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');
        stmts.discordLinks.setCountry.run('CA', 'discord1');

        const row = stmts.discordLinks.getCountryByEmail.get('user@test.com');
        expect(row.country).toBe('CA');
    });

    it('getInternationalUsers returns only non-US users', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user1@test.com');
        stmts.purchases.linkDiscord.run('discord2', 'user2@test.com');
        stmts.purchases.linkDiscord.run('discord3', 'user3@test.com');

        stmts.discordLinks.setCountry.run('CA', 'discord1');
        stmts.discordLinks.setCountry.run('US', 'discord2');
        // discord3 has null country

        const intl = stmts.discordLinks.getInternationalUsers.all();
        expect(intl).toHaveLength(1);
        expect(intl[0].discord_user_id).toBe('discord1');
    });

    it('getInternationalUsers returns empty when none flagged', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');

        const intl = stmts.discordLinks.getInternationalUsers.all();
        expect(intl).toHaveLength(0);
    });
});

// =========================================================================
// Double-charge prevention scenarios
// =========================================================================

describe('double-charge prevention', () => {
    it('domestic buyer who paid via checkout is covered for the week', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout');

        // Later this week, they buy via Discord button — should be covered
        expect(stmts.shipping.hasShippingThisWeek.get('user@test.com')).toBeTruthy();
    });

    it('domestic buyer who paid via livestream is covered for the week', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'livestream');

        expect(stmts.shipping.hasShippingThisWeek.get('user@test.com')).toBeTruthy();
    });

    it('international buyer who paid via checkout is covered for the month', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');
        stmts.discordLinks.setCountry.run('CA', 'discord1');
        stmts.shipping.record.run('user@test.com', 'discord1', 2500, 'checkout');

        expect(stmts.shipping.hasShippingThisMonth.get('user@test.com')).toBeTruthy();
    });

    it('different payment sources all count toward coverage', () => {
        // Checkout payment
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout');

        // Should still be covered even though it was from a checkout, not livestream
        expect(stmts.shipping.hasShippingThisWeek.get('user@test.com')).toBeTruthy();
    });
});

// =========================================================================
// Integration: livestream + shipping_payments
// =========================================================================

describe('livestream + unified shipping integration', () => {
    it('livestream shipping records in both tables', () => {
        // Start livestream
        stmts.livestream.startSession.run();
        const session = stmts.livestream.getActiveSession.get();
        stmts.livestream.addBuyer.run(session.id, 'user1', 'user1@test.com');

        // Mark shipping paid in livestream table
        stmts.livestream.markShippingPaid.run(session.id, 'user1@test.com');

        // Also record in unified shipping_payments
        stmts.shipping.record.run('user1@test.com', 'user1', 1000, 'livestream');

        // Both tables should reflect payment
        const livestreamPaid = stmts.livestream.hasShippingThisWeek.get('user1@test.com');
        const shippingPaid = stmts.shipping.hasShippingThisWeek.get('user1@test.com');

        expect(livestreamPaid).toBeTruthy();
        expect(shippingPaid).toBeTruthy();
    });
});
