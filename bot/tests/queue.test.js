import { describe, it, expect, beforeEach } from 'vitest';
import { createTestDb, buildStmts } from './setup.js';

let db, stmts;

beforeEach(() => {
    db = createTestDb();
    stmts = buildStmts(db);
});

describe('queue lifecycle', () => {
    it('creates a queue', () => {
        const result = stmts.queues.createQueue.run();
        expect(result.lastInsertRowid).toBe(1);

        const queue = stmts.queues.getActiveQueue.get();
        expect(queue).toBeTruthy();
        expect(queue.status).toBe('open');
    });

    it('closes a queue', () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();

        stmts.queues.closeQueue.run(queue.id);

        const active = stmts.queues.getActiveQueue.get();
        expect(active).toBeUndefined();

        const closed = stmts.queues.getQueueById.get(queue.id);
        expect(closed.status).toBe('closed');
        expect(closed.closed_at).toBeTruthy();
    });

    it('returns an active queue when multiple exist', () => {
        stmts.queues.createQueue.run();
        stmts.queues.createQueue.run();

        // Both are open — getActiveQueue returns one of them
        const active = stmts.queues.getActiveQueue.get();
        expect(active).toBeTruthy();
        expect(active.status).toBe('open');
    });
});

describe('queue entries', () => {
    it('adds entries', () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();

        stmts.queues.addEntry.run(queue.id, 'user1', 'user1@test.com', 'Pokemon Pack', 1, 'session_1');
        stmts.queues.addEntry.run(queue.id, 'user2', 'user2@test.com', 'Pokemon Pack', 2, 'session_2');

        const entries = stmts.queues.getEntries.all(queue.id);
        expect(entries).toHaveLength(2);
        expect(entries[0].product_name).toBe('Pokemon Pack');
        expect(entries[1].quantity).toBe(2);
    });

    it('allows same user to have multiple entries', () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();

        stmts.queues.addEntry.run(queue.id, 'user1', 'user1@test.com', 'Pack A', 1, 'session_1');
        stmts.queues.addEntry.run(queue.id, 'user1', 'user1@test.com', 'Pack B', 1, 'session_2');

        const entries = stmts.queues.getEntries.all(queue.id);
        expect(entries).toHaveLength(2);
    });

    it('counts unique buyers correctly', () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();

        stmts.queues.addEntry.run(queue.id, 'user1', 'u1@test.com', 'Pack A', 1, 's1');
        stmts.queues.addEntry.run(queue.id, 'user1', 'u1@test.com', 'Pack B', 1, 's2');
        stmts.queues.addEntry.run(queue.id, 'user2', 'u2@test.com', 'Pack A', 1, 's3');
        stmts.queues.addEntry.run(queue.id, 'user3', 'u3@test.com', 'Pack A', 1, 's4');

        const buyers = stmts.queues.getUniqueBuyers.all(queue.id);
        expect(buyers).toHaveLength(3);
    });

    it('unique buyers = duck race entries (one per buyer regardless of items)', () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();

        // User1 buys 5 items
        for (let i = 0; i < 5; i++) {
            stmts.queues.addEntry.run(queue.id, 'user1', 'u1@test.com', `Pack ${i}`, 1, `s${i}`);
        }
        // User2 buys 1 item
        stmts.queues.addEntry.run(queue.id, 'user2', 'u2@test.com', 'Pack X', 1, 's5');

        const buyers = stmts.queues.getUniqueBuyers.all(queue.id);
        expect(buyers).toHaveLength(2); // 2 duck race entries, not 6
    });

    it('entries without discord user id are not counted as unique buyers', () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();

        stmts.queues.addEntry.run(queue.id, null, 'anon@test.com', 'Pack A', 1, 's1');
        stmts.queues.addEntry.run(queue.id, 'user1', 'u1@test.com', 'Pack B', 1, 's2');

        const buyers = stmts.queues.getUniqueBuyers.all(queue.id);
        expect(buyers).toHaveLength(1); // only linked user
    });
});

describe('duck race winner', () => {
    it('sets duck race winner', () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();

        stmts.queues.addEntry.run(queue.id, 'user1', 'u1@test.com', 'Pack', 1, 's1');
        stmts.queues.setDuckRaceWinner.run('user1', queue.id);

        const updated = stmts.queues.getQueueById.get(queue.id);
        expect(updated.duck_race_winner_id).toBe('user1');
        expect(updated.status).toBe('complete');
    });
});

describe('queue history', () => {
    it('returns recent closed queues', () => {
        // Create and close 3 queues
        for (let i = 0; i < 3; i++) {
            stmts.queues.createQueue.run();
            const q = stmts.queues.getActiveQueue.get();
            stmts.queues.closeQueue.run(q.id);
        }

        const recent = stmts.queues.getRecentQueues.all(5);
        expect(recent).toHaveLength(3);
    });

    it('respects limit', () => {
        for (let i = 0; i < 10; i++) {
            stmts.queues.createQueue.run();
            const q = stmts.queues.getActiveQueue.get();
            stmts.queues.closeQueue.run(q.id);
        }

        const recent = stmts.queues.getRecentQueues.all(3);
        expect(recent).toHaveLength(3);
    });

    it('does not include open queues', () => {
        stmts.queues.createQueue.run();
        // Don't close it

        const recent = stmts.queues.getRecentQueues.all(5);
        expect(recent).toHaveLength(0);
    });
});
