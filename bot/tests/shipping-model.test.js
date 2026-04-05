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
        stmts.shipping.record.run('user@test.com', 'discord123', 1000, 'checkout', null);

        const records = stmts.shipping.getThisWeek.all();
        expect(records).toHaveLength(1);
        expect(records[0].customer_email).toBe('user@test.com');
        expect(records[0].amount).toBe(1000);
        expect(records[0].source).toBe('checkout');
    });

    it('records multiple payments from different sources', () => {
        stmts.shipping.record.run('user1@test.com', 'discord1', 1000, 'checkout', null);
        stmts.shipping.record.run('user2@test.com', 'discord2', 2500, 'livestream', null);
        stmts.shipping.record.run('user3@test.com', null, 1000, 'ad-hoc', null);

        const records = stmts.shipping.getThisWeek.all();
        expect(records).toHaveLength(3);
    });

    it('allows null discord_user_id', () => {
        stmts.shipping.record.run('user@test.com', null, 1000, 'checkout', null);

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
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout', null);

        const result = stmts.shipping.hasShippingThisWeek.get('user@test.com');
        expect(result).toBeTruthy();
    });

    it('is email-specific — different emails are independent', () => {
        stmts.shipping.record.run('user1@test.com', 'discord1', 1000, 'checkout', null);

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
        stmts.shipping.record.run('user@test.com', 'discord1', 2500, 'livestream', null);

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
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout', null);

        // Later this week, they buy via Discord button — should be covered
        expect(stmts.shipping.hasShippingThisWeek.get('user@test.com')).toBeTruthy();
    });

    it('domestic buyer who paid via livestream is covered for the week', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'livestream', null);

        expect(stmts.shipping.hasShippingThisWeek.get('user@test.com')).toBeTruthy();
    });

    it('international buyer who paid via checkout is covered for the month', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');
        stmts.discordLinks.setCountry.run('CA', 'discord1');
        stmts.shipping.record.run('user@test.com', 'discord1', 2500, 'checkout', null);

        expect(stmts.shipping.hasShippingThisMonth.get('user@test.com')).toBeTruthy();
    });

    it('different payment sources all count toward coverage', () => {
        // Checkout payment
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout', null);

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
        stmts.shipping.record.run('user1@test.com', 'user1', 1000, 'livestream', null);

        // Both tables should reflect payment
        const livestreamPaid = stmts.livestream.hasShippingThisWeek.get('user1@test.com');
        const shippingPaid = stmts.shipping.hasShippingThisWeek.get('user1@test.com');

        expect(livestreamPaid).toBeTruthy();
        expect(shippingPaid).toBeTruthy();
    });
});

// =========================================================================
// buildShippingOptions — dual-option for unflagged buyers
// =========================================================================

describe('buildShippingOptions logic', () => {
    it('no discordUserId → both options', () => {
        // No user identified at all — offer both
        const domestic = stmts.discordLinks.getCountry.get('nonexistent');
        expect(domestic).toBeUndefined();
        // Simulates what buildShippingOptions does: no ID → both rates
    });

    it('unflagged user (no country set) → both options available', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');

        // No country set — getCountry returns null
        const row = stmts.discordLinks.getCountry.get('discord1');
        expect(row.country).toBeNull();
    });

    it('user flagged as CA → only international rate applies', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');
        stmts.discordLinks.setCountry.run('CA', 'discord1');

        const row = stmts.discordLinks.getCountry.get('discord1');
        expect(row.country).toBe('CA');
        expect(row.country !== 'US').toBe(true);
    });

    it('user flagged as US → only domestic rate applies', () => {
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');
        stmts.discordLinks.setCountry.run('US', 'discord1');

        const row = stmts.discordLinks.getCountry.get('discord1');
        expect(row.country).toBe('US');
    });

    it('auto-flag from webhook then subsequent lookup returns correct country', () => {
        // Simulate: buyer links, makes purchase, webhook auto-flags
        stmts.purchases.linkDiscord.run('discord1', 'user@test.com');
        stmts.discordLinks.setCountry.run('CA', 'discord1');

        // Next checkout should see them as international
        const row = stmts.discordLinks.getCountry.get('discord1');
        expect(row.country).toBe('CA');

        // And getCountryByEmail should also work
        const byEmail = stmts.discordLinks.getCountryByEmail.get('user@test.com');
        expect(byEmail.country).toBe('CA');
    });
});

// =========================================================================
// stripe_session_id on shipping_payments
// =========================================================================

describe('shipping_payments stripe_session_id', () => {
    it('records stripe_session_id when provided', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout', 'cs_test_123');

        const record = stmts.shipping.getByEmailThisWeek.get('user@test.com');
        expect(record.stripe_session_id).toBe('cs_test_123');
    });

    it('stores null stripe_session_id when not provided', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout', null);

        const record = stmts.shipping.getByEmailThisWeek.get('user@test.com');
        expect(record.stripe_session_id).toBeNull();
    });
});

// =========================================================================
// getByEmailThisWeek / getByEmailThisMonth
// =========================================================================

describe('getByEmailThisWeek', () => {
    it('returns the full record', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout', 'cs_test_456');

        const record = stmts.shipping.getByEmailThisWeek.get('user@test.com');
        expect(record).toBeDefined();
        expect(record.customer_email).toBe('user@test.com');
        expect(record.amount).toBe(1000);
        expect(record.stripe_session_id).toBe('cs_test_456');
    });

    it('returns undefined when no record exists', () => {
        const record = stmts.shipping.getByEmailThisWeek.get('nobody@test.com');
        expect(record).toBeUndefined();
    });
});

describe('getByEmailThisMonth', () => {
    it('returns the full record', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 2500, 'checkout', 'cs_test_789');

        const record = stmts.shipping.getByEmailThisMonth.get('user@test.com');
        expect(record).toBeDefined();
        expect(record.amount).toBe(2500);
        expect(record.stripe_session_id).toBe('cs_test_789');
    });
});

// =========================================================================
// deleteById
// =========================================================================

describe('deleteById', () => {
    it('removes a shipping record', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 1000, 'checkout', null);

        const record = stmts.shipping.getByEmailThisWeek.get('user@test.com');
        expect(record).toBeDefined();

        stmts.shipping.deleteById.run(record.id);

        const after = stmts.shipping.getByEmailThisWeek.get('user@test.com');
        expect(after).toBeUndefined();
    });

    it('does not affect other records', () => {
        stmts.shipping.record.run('user1@test.com', 'discord1', 1000, 'checkout', null);
        stmts.shipping.record.run('user2@test.com', 'discord2', 1000, 'checkout', null);

        const record1 = stmts.shipping.getByEmailThisWeek.get('user1@test.com');
        stmts.shipping.deleteById.run(record1.id);

        expect(stmts.shipping.getByEmailThisWeek.get('user1@test.com')).toBeUndefined();
        expect(stmts.shipping.getByEmailThisWeek.get('user2@test.com')).toBeDefined();
    });
});

// =========================================================================
// Waiver record (amount=0, source='waiver')
// =========================================================================

describe('waiver records', () => {
    it('waiver with amount=0 counts as shipping covered', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 0, 'waiver', null);

        // hasShippingThisWeek checks for ANY row, not amount > 0
        expect(stmts.shipping.hasShippingThisWeek.get('user@test.com')).toBeTruthy();
    });

    it('waiver with amount=0 counts for monthly coverage', () => {
        stmts.shipping.record.run('user@test.com', 'discord1', 0, 'waiver', null);

        expect(stmts.shipping.hasShippingThisMonth.get('user@test.com')).toBeTruthy();
    });
});

// =========================================================================
// Purchase lookup statements
// =========================================================================

describe('purchase lookup', () => {
    it('getRecentByDiscordId returns most recent purchase', () => {
        stmts.purchases.insertPurchase.run('cs_1', 'discord1', 'user@test.com', 'Product A', 2000);
        stmts.purchases.insertPurchase.run('cs_2', 'discord1', 'user@test.com', 'Product B', 3000);

        const recent = stmts.purchases.getRecentByDiscordId.get('discord1');
        expect(recent.stripe_session_id).toBe('cs_2');
        expect(recent.product_name).toBe('Product B');
    });

    it('getRecentByDiscordId returns undefined for unknown user', () => {
        const recent = stmts.purchases.getRecentByDiscordId.get('nonexistent');
        expect(recent).toBeUndefined();
    });

    it('getBySessionId returns the matching purchase', () => {
        stmts.purchases.insertPurchase.run('cs_abc', 'discord1', 'user@test.com', 'Product X', 5000);

        const purchase = stmts.purchases.getBySessionId.get('cs_abc');
        expect(purchase).toBeDefined();
        expect(purchase.product_name).toBe('Product X');
    });

    it('getBySessionId returns undefined for unknown session', () => {
        const purchase = stmts.purchases.getBySessionId.get('cs_nonexistent');
        expect(purchase).toBeUndefined();
    });
});

// =========================================================================
// WordPress shop → second purchase skips shipping (email capture flow)
// =========================================================================

describe('WordPress shop email capture: second purchase skips shipping', () => {
    it('domestic buyer: first purchase records shipping, second lookup shows covered', () => {
        // First purchase — webhook records shipping paid at checkout
        stmts.purchases.insertPurchase.run('cs_first', null, 'buyer@test.com', 'Pokemon Pack', 1999);
        stmts.shipping.record.run('buyer@test.com', null, 1000, 'checkout', 'cs_first');

        // Second purchase — shipping lookup check (what the /shipping/lookup endpoint does)
        const covered = stmts.shipping.hasShippingThisWeek.get('buyer@test.com');
        expect(covered).toBeTruthy();
    });

    it('international buyer: first purchase records shipping, second lookup shows covered', () => {
        // Link email and flag as international
        stmts.purchases.linkDiscord.run('discord1', 'intl@test.com');
        stmts.discordLinks.setCountry.run('CA', 'discord1');

        // First purchase with international shipping
        stmts.purchases.insertPurchase.run('cs_intl_first', 'discord1', 'intl@test.com', 'Anime Box', 3500);
        stmts.shipping.record.run('intl@test.com', 'discord1', 2500, 'checkout', 'cs_intl_first');

        // Second purchase — should be covered for the month
        const covered = stmts.shipping.hasShippingThisMonth.get('intl@test.com');
        expect(covered).toBeTruthy();
    });

    it('unlinked buyer: shipping coverage works by email alone (no Discord link needed)', () => {
        // Buyer who never linked Discord — just entered email in the modal
        stmts.shipping.record.run('nolink@test.com', null, 1000, 'checkout', 'cs_nolink');

        // Second purchase lookup — still covered even without Discord link
        const covered = stmts.shipping.hasShippingThisWeek.get('nolink@test.com');
        expect(covered).toBeTruthy();
    });

    it('coverage does not bleed across different emails', () => {
        stmts.shipping.record.run('alice@test.com', null, 1000, 'checkout', 'cs_alice');

        expect(stmts.shipping.hasShippingThisWeek.get('alice@test.com')).toBeTruthy();
        expect(stmts.shipping.hasShippingThisWeek.get('bob@test.com')).toBeUndefined();
    });

    it('full flow: purchase → shipping record → lookup returns covered with rate 0', () => {
        // Simulate the full flow as the shipping lookup endpoint would
        stmts.purchases.insertPurchase.run('cs_flow', null, 'flow@test.com', 'Product A', 2000);
        stmts.shipping.record.run('flow@test.com', null, 1000, 'checkout', 'cs_flow');

        // Replicate what GET /shipping/lookup does
        const covered = !!stmts.shipping.hasShippingThisWeek.get('flow@test.com');
        const rate = covered ? 0 : 1000;

        expect(covered).toBe(true);
        expect(rate).toBe(0);
    });

    it('first purchase with no shipping record: lookup shows not covered', () => {
        // Buyer exists but no shipping payment recorded (e.g., livestream mode)
        stmts.purchases.insertPurchase.run('cs_nosship', null, 'nosship@test.com', 'Product B', 1500);

        const covered = stmts.shipping.hasShippingThisWeek.get('nosship@test.com');
        expect(covered).toBeUndefined();
    });
});
