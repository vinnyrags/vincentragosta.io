/**
 * Tests for community goals — DB operations, cycle resets, and milestone detection.
 */

import { describe, it, expect, beforeEach } from 'vitest';
import { createTestDb, buildStmts } from './setup.js';

describe('community goals DB operations', () => {
    let db;
    let stmts;

    beforeEach(() => {
        db = createTestDb();
        stmts = buildStmts(db);
    });

    it('initializes with default values', () => {
        const goal = stmts.goals.get.get();
        expect(goal.cycle).toBe(1);
        expect(goal.cycle_revenue).toBe(0);
        expect(goal.lifetime_revenue).toBe(0);
        expect(goal.channel_message_id).toBeNull();
    });

    it('adds revenue to both cycle and lifetime', () => {
        stmts.goals.addRevenue.run(5000, 5000); // $50
        const goal = stmts.goals.get.get();
        expect(goal.cycle_revenue).toBe(5000);
        expect(goal.lifetime_revenue).toBe(5000);
    });

    it('accumulates revenue across multiple purchases', () => {
        stmts.goals.addRevenue.run(5000, 5000);
        stmts.goals.addRevenue.run(3000, 3000);
        stmts.goals.addRevenue.run(2000, 2000);
        const goal = stmts.goals.get.get();
        expect(goal.cycle_revenue).toBe(10000); // $100
        expect(goal.lifetime_revenue).toBe(10000);
    });

    it('resets cycle and carries over remainder', () => {
        const CYCLE_GOAL = 250000; // $2,500
        stmts.goals.addRevenue.run(260000, 260000); // $2,600
        stmts.goals.resetCycle.run(CYCLE_GOAL);

        const goal = stmts.goals.get.get();
        expect(goal.cycle).toBe(2);
        expect(goal.cycle_revenue).toBe(10000); // $100 carried over
        expect(goal.lifetime_revenue).toBe(260000); // lifetime unchanged
    });

    it('handles multiple cycle resets', () => {
        const CYCLE_GOAL = 250000;

        // Simulate enough revenue for 3 cycles
        stmts.goals.addRevenue.run(760000, 760000); // $7,600

        // Reset 3 times
        stmts.goals.resetCycle.run(CYCLE_GOAL);
        stmts.goals.resetCycle.run(CYCLE_GOAL);
        stmts.goals.resetCycle.run(CYCLE_GOAL);

        const goal = stmts.goals.get.get();
        expect(goal.cycle).toBe(4);
        expect(goal.cycle_revenue).toBe(10000); // $100 remainder
        expect(goal.lifetime_revenue).toBe(760000);
    });

    it('stores and retrieves channel message ID', () => {
        stmts.goals.setMessageId.run('msg_123');
        const goal = stmts.goals.get.get();
        expect(goal.channel_message_id).toBe('msg_123');
    });

    it('enforces single row constraint', () => {
        // Try to insert a second row — should fail
        expect(() => {
            db.prepare('INSERT INTO community_goals (id) VALUES (2)').run();
        }).toThrow();
    });
});
