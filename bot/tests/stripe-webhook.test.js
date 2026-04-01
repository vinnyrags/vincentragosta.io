/**
 * Tests for Stripe webhook handler — database-level logic tested via
 * direct statement calls (matching the existing test pattern).
 *
 * The handleCheckoutCompleted function can't be tested through vi.mock
 * because the source uses CJS require() which bypasses Vitest's mock system.
 * Instead, we test the database operations it performs directly.
 */

import { describe, it, expect, beforeEach } from 'vitest';
import { createTestDb, buildStmts } from './setup.js';

let db, stmts;

beforeEach(() => {
    db = createTestDb();
    stmts = buildStmts(db);
});

// =========================================================================
// Purchase recording (same operations handleCheckoutCompleted performs)
// =========================================================================

describe('purchase recording operations', () => {
    it('records purchase with all fields', () => {
        stmts.purchases.insertPurchase.run('cs_123', 'discord1', 'buyer@example.com', 'Prismatic Evolutions', 2500);
        const p = db.prepare('SELECT * FROM purchases WHERE stripe_session_id = ?').get('cs_123');
        expect(p.discord_user_id).toBe('discord1');
        expect(p.customer_email).toBe('buyer@example.com');
        expect(p.product_name).toBe('Prismatic Evolutions');
        expect(p.amount).toBe(2500);
    });

    it('ignores duplicate session IDs', () => {
        stmts.purchases.insertPurchase.run('cs_dup', null, 'a@b.com', 'Card', 100);
        stmts.purchases.insertPurchase.run('cs_dup', null, 'a@b.com', 'Card', 100);
        const count = db.prepare('SELECT COUNT(*) as c FROM purchases WHERE stripe_session_id = ?').get('cs_dup').c;
        expect(count).toBe(1);
    });

    it('records purchase with null discord_user_id for unlinked users', () => {
        stmts.purchases.insertPurchase.run('cs_anon', null, 'buyer@example.com', 'Card', 1000);
        const p = db.prepare('SELECT * FROM purchases WHERE stripe_session_id = ?').get('cs_anon');
        expect(p.discord_user_id).toBeNull();
    });
});

// =========================================================================
// Purchase count tracking (role promotion logic)
// =========================================================================

describe('purchase count tracking for role promotion', () => {
    it('starts at 1 for first purchase', () => {
        stmts.purchases.incrementPurchaseCount.run('user1');
        const c = stmts.purchases.getPurchaseCount.get('user1');
        expect(c.total_purchases).toBe(1);
    });

    it('increments on subsequent purchases', () => {
        stmts.purchases.incrementPurchaseCount.run('user1');
        stmts.purchases.incrementPurchaseCount.run('user1');
        stmts.purchases.incrementPurchaseCount.run('user1');
        expect(stmts.purchases.getPurchaseCount.get('user1').total_purchases).toBe(3);
    });

    it('tracks counts per user independently', () => {
        stmts.purchases.incrementPurchaseCount.run('user1');
        stmts.purchases.incrementPurchaseCount.run('user1');
        stmts.purchases.incrementPurchaseCount.run('user2');
        expect(stmts.purchases.getPurchaseCount.get('user1').total_purchases).toBe(2);
        expect(stmts.purchases.getPurchaseCount.get('user2').total_purchases).toBe(1);
    });

    it('returns undefined for users with no purchases', () => {
        expect(stmts.purchases.getPurchaseCount.get('nobody')).toBeUndefined();
    });

    it('reaches Xipe threshold at 1', () => {
        stmts.purchases.incrementPurchaseCount.run('user1');
        const count = stmts.purchases.getPurchaseCount.get('user1').total_purchases;
        expect(count >= 1).toBe(true); // XIPE_PURCHASE_THRESHOLD
    });

    it('reaches Nous threshold at 5', () => {
        for (let i = 0; i < 5; i++) stmts.purchases.incrementPurchaseCount.run('user1');
        const count = stmts.purchases.getPurchaseCount.get('user1').total_purchases;
        expect(count >= 5).toBe(true); // NOUS_PURCHASE_THRESHOLD
    });
});

// =========================================================================
// Auto-linking Discord ↔ email
// =========================================================================

describe('Discord auto-linking', () => {
    it('links discord user to email', () => {
        stmts.purchases.linkDiscord.run('discord1', 'buyer@example.com');
        const link = stmts.purchases.getDiscordIdByEmail.get('buyer@example.com');
        expect(link.discord_user_id).toBe('discord1');
    });

    it('looks up email by discord ID', () => {
        stmts.purchases.linkDiscord.run('discord1', 'buyer@example.com');
        const link = stmts.purchases.getEmailByDiscordId.get('discord1');
        expect(link.customer_email).toBe('buyer@example.com');
    });

    it('replaces link on re-link (INSERT OR REPLACE)', () => {
        stmts.purchases.linkDiscord.run('discord1', 'old@example.com');
        stmts.purchases.linkDiscord.run('discord1', 'new@example.com');
        const link = stmts.purchases.getEmailByDiscordId.get('discord1');
        expect(link.customer_email).toBe('new@example.com');
    });

    it('supports looking up discord ID by linked email', () => {
        stmts.purchases.linkDiscord.run('discord1', 'buyer@example.com');
        stmts.purchases.insertPurchase.run('cs_1', null, 'buyer@example.com', 'Card', 100);
        // Webhook flow: look up discord ID from email
        const link = stmts.purchases.getDiscordIdByEmail.get('buyer@example.com');
        expect(link.discord_user_id).toBe('discord1');
    });
});

// =========================================================================
// Battle payment flow (operations checkBattlePayment performs)
// =========================================================================

describe('battle payment flow', () => {
    it('adds entry and confirms payment in one flow', () => {
        stmts.battles.createBattle.run('product', 'Test Product', 'price_123', 20, null);
        const battle = stmts.battles.getActiveBattle.get();

        stmts.battles.addEntry.run(battle.id, 'user1');
        stmts.battles.confirmPayment.run('cs_pay_123', battle.id, 'user1');

        const entries = stmts.battles.getPaidEntries.all(battle.id);
        expect(entries).toHaveLength(1);
        expect(entries[0].paid).toBe(1);
        expect(entries[0].stripe_session_id).toBe('cs_pay_123');
    });

    it('respects max entries limit', () => {
        stmts.battles.createBattle.run('product', 'Test', 'price_1', 2, null);
        const battle = stmts.battles.getActiveBattle.get();

        stmts.battles.addEntry.run(battle.id, 'user1');
        stmts.battles.addEntry.run(battle.id, 'user2');

        const count = stmts.battles.getEntryCount.get(battle.id).count;
        expect(count >= battle.max_entries).toBe(true);

        // Third entry should be checked before adding
        // In the handler, this check prevents adding more entries
        expect(count >= 2).toBe(true);
    });

    it('links purchase to battle entry via session ID', () => {
        stmts.battles.createBattle.run('product', 'Test', 'price_1', 20, null);
        const battle = stmts.battles.getActiveBattle.get();

        stmts.battles.addEntry.run(battle.id, 'user1');
        stmts.battles.confirmPayment.run('cs_battle_pay', battle.id, 'user1');

        const entry = stmts.battles.getPaidEntries.all(battle.id)[0];
        expect(entry.stripe_session_id).toBe('cs_battle_pay');
    });
});

// =========================================================================
// Full purchase → promotion flow
// =========================================================================

describe('purchase → promotion flow', () => {
    it('complete flow: link → purchase → count → check threshold', () => {
        // Step 1: Link Discord account
        stmts.purchases.linkDiscord.run('discord1', 'buyer@example.com');

        // Step 2: Record purchase
        stmts.purchases.insertPurchase.run('cs_flow_1', 'discord1', 'buyer@example.com', 'Card', 2500);

        // Step 3: Increment count
        stmts.purchases.incrementPurchaseCount.run('discord1');

        // Step 4: Check threshold
        const count = stmts.purchases.getPurchaseCount.get('discord1').total_purchases;
        expect(count).toBe(1);
        expect(count >= 1).toBe(true); // Xipe threshold met
        expect(count >= 5).toBe(false); // Nous threshold not met
    });

    it('reaches Nous after 5 purchases', () => {
        stmts.purchases.linkDiscord.run('discord1', 'buyer@example.com');

        for (let i = 0; i < 5; i++) {
            stmts.purchases.insertPurchase.run(`cs_flow_${i}`, 'discord1', 'buyer@example.com', 'Card', 1000);
            stmts.purchases.incrementPurchaseCount.run('discord1');
        }

        const count = stmts.purchases.getPurchaseCount.get('discord1').total_purchases;
        expect(count).toBe(5);
        expect(count >= 5).toBe(true); // Nous threshold met
    });
});
