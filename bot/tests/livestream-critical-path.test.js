/**
 * Livestream Critical Path — Integration Test
 *
 * Simulates an entire card night from start to finish:
 *
 *   1. !queue open          — open pre-order queue
 *   2. Stripe purchases     — pre-orders land in queue
 *   3. !queue               — verify queue state mid-stream
 *   4. !live                — start livestream session
 *   5. Stripe purchases     — live purchases → queue + livestream buyers
 *   6. !duckrace            — verify duck race roster
 *   7. !offline             — end session, close queue, DM shipping, open new queue
 *   8. !duckrace winner     — declare duck race winner
 *   9. !queue history       — verify archive
 *
 * Also covers the manual mid-stream queue close scenario.
 *
 * All Discord interactions are mocked — nothing hits a real server.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createTestDb, buildStmts } from './setup.js';
import { createMockMessage, createMockMention } from './mocks.js';

// =========================================================================
// Module mocks — prevent real Discord/Stripe/fetch connections
// =========================================================================

const ROLE_AKIVILI = '1488046525065072670';
const ROLE_NANOOK = '1488046525899739148';

const mockGetMember = vi.fn().mockImplementation((userId) =>
    Promise.resolve({
        id: userId,
        user: { tag: `user#${userId}` },
        createDM: vi.fn().mockResolvedValue({
            send: vi.fn().mockResolvedValue({}),
        }),
        roles: {
            cache: { has: vi.fn().mockReturnValue(false) },
            add: vi.fn().mockResolvedValue(true),
        },
    })
);

const mockSendEmbed = vi.fn().mockResolvedValue(null);
const mockSendToChannel = vi.fn().mockResolvedValue(null);
const mockAddRole = vi.fn().mockResolvedValue(false);

vi.mock('../discord.js', () => ({
    client: { channels: { cache: { get: vi.fn() } } },
    getChannel: vi.fn(),
    getGuild: vi.fn().mockReturnValue({ ownerId: 'owner1', members: { fetch: vi.fn() } }),
    sendToChannel: (...args) => mockSendToChannel(...args),
    sendEmbed: (...args) => mockSendEmbed(...args),
    getMember: (...args) => mockGetMember(...args),
    hasRole: vi.fn().mockReturnValue(false),
    addRole: (...args) => mockAddRole(...args),
}));

vi.mock('../config.js', () => ({
    default: {
        CHANNELS: {
            ANNOUNCEMENTS: '1',
            ORDER_FEED: '2',
            DEALS: '3',
            PACK_BATTLES: '4',
            AND_IN_THE_BACK: '5',
            CARD_NIGHT_QUEUE: '6',
            CARD_SHOP: '7',
            OPS: '8',
            BOT_COMMANDS: '9',
            COMMUNITY_GOALS: '10',
            ANALYTICS: '11',
        },
        ROLES: {
            AKIVILI: '1488046525065072670',
            NANOOK: '1488046525899739148',
            LONG: 'r3',
            AHA: 'r4',
            XIPE: 'r5',
        },
        SHOP_URL: 'https://example.com/shop',
        SITE_URL: 'https://example.com',
        LIVESTREAM_SECRET: 'test-secret',
        STRIPE_SECRET_KEY: 'sk_test_123',
        GUILD_ID: 'guild123',
        LOW_STOCK_THRESHOLD: 3,
        LONG_PURCHASE_THRESHOLD: 5,
        XIPE_PURCHASE_THRESHOLD: 1,
        SHIPPING: {
            COUNTRIES: ['US', 'CA'],
            DOMESTIC: 1000,
            INTERNATIONAL: 2500,
        },
        CARD_RESERVATION_MS: 15 * 60 * 1000,
    },
}));

vi.mock('../db.js', () => ({
    db: null,
    purchases: {},
    battles: {},
    queues: {},
    livestream: {},
    cardListings: {},
    ducks: {},
    goals: {},
    analytics: {},
    shipping: {},
    discordLinks: {},
    coupons: {},
}));

vi.mock('../community-goals.js', () => ({
    addRevenue: vi.fn().mockResolvedValue(undefined),
    initCommunityGoals: vi.fn().mockResolvedValue(undefined),
    CYCLE_GOAL: 250000,
    MILESTONE_INCREMENT: 500000,
}));

vi.mock('../commands/card-shop.js', () => ({
    clearExpiryTimer: vi.fn(),
    updateListingEmbed: vi.fn().mockResolvedValue(undefined),
}));

// Swap in fresh in-memory DB before each test
const dbModule = await import('../db.js');
let db, stmts;

beforeEach(() => {
    db = createTestDb();
    stmts = buildStmts(db);
    dbModule.db = db;
    Object.assign(dbModule.purchases, stmts.purchases);
    Object.assign(dbModule.battles, stmts.battles);
    Object.assign(dbModule.queues, stmts.queues);
    Object.assign(dbModule.livestream, stmts.livestream);
    Object.assign(dbModule.cardListings, stmts.cardListings);
    Object.assign(dbModule.goals, stmts.goals);
    Object.assign(dbModule.analytics, stmts.analytics);
    Object.assign(dbModule.shipping, stmts.shipping);
    Object.assign(dbModule.discordLinks, stmts.discordLinks);
    vi.clearAllMocks();

    // Mock fetch (used by toggleLivestreamMode for WordPress REST API)
    global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({}),
    });
});

// Import command handlers (after mocks are in place)
const { handleQueue, handleDuckRace, addToQueue } = await import('../commands/queue.js');
const { handleLive, handleOffline, addLivestreamBuyer } = await import('../commands/live.js');
const { handleCheckoutCompleted } = await import('../webhooks/stripe.js');

// =========================================================================
// Helpers
// =========================================================================

function adminMsg(overrides = {}) {
    return createMockMessage({ roles: [ROLE_AKIVILI], ...overrides });
}

function userMsg(overrides = {}) {
    return createMockMessage({ roles: [], ...overrides });
}

/**
 * Simulate a Stripe checkout session for a card product purchase.
 */
function fakeCheckoutSession({
    sessionId = `cs_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`,
    email = 'buyer@example.com',
    discordUserId = null,
    products = [{ name: 'Prismatic Evolutions ETB', quantity: 1 }],
    live = false,
    amount = 3999,
} = {}) {
    // Pre-link discord so the webhook can find the user
    if (discordUserId) {
        stmts.purchases.linkDiscord.run(discordUserId, email);
    }

    return {
        id: sessionId,
        customer_details: { email },
        customer_email: email,
        amount_total: amount,
        amount_subtotal: amount,
        metadata: {
            line_items: JSON.stringify(products.map((p) => ({
                name: p.name,
                quantity: p.quantity || 1,
                stock_remaining: p.stock ?? 10,
            }))),
            live: live ? '1' : '0',
        },
        custom_fields: [],
    };
}

// =========================================================================
// Full Card Night — Happy Path
// =========================================================================

describe('full card night critical path', () => {
    it('runs the entire livestream flow from queue open to offline', async () => {
        // ── Step 1: Open the queue ──────────────────────────────────
        const openMsg = adminMsg({ content: '!queue open' });
        await handleQueue(openMsg, ['open']);

        const queue = stmts.queues.getActiveQueue.get();
        expect(queue).toBeTruthy();
        expect(queue.status).toBe('open');
        expect(openMsg.channel.send).toHaveBeenCalled();

        // ── Step 2: Pre-orders arrive via Stripe ────────────────────
        await handleCheckoutCompleted(fakeCheckoutSession({
            sessionId: 'cs_preorder_1',
            email: 'alice@example.com',
            discordUserId: 'alice_discord',
            products: [{ name: 'Prismatic Evolutions ETB', quantity: 1 }],
        }));

        await handleCheckoutCompleted(fakeCheckoutSession({
            sessionId: 'cs_preorder_2',
            email: 'bob@example.com',
            discordUserId: 'bob_discord',
            products: [
                { name: 'Prismatic Evolutions ETB', quantity: 1 },
                { name: 'Surging Sparks Booster', quantity: 2 },
            ],
        }));

        // Verify queue has entries from pre-orders
        let entries = stmts.queues.getEntries.all(queue.id);
        expect(entries).toHaveLength(3); // 1 + 2 items
        let uniqueBuyers = stmts.queues.getUniqueBuyers.all(queue.id);
        expect(uniqueBuyers).toHaveLength(2); // alice + bob

        // ── Step 3: Check queue status ──────────────────────────────
        const showMsg = adminMsg({ content: '!queue' });
        await handleQueue(showMsg, []);
        expect(showMsg.channel.send).toHaveBeenCalled();

        // ── Step 4: Go live ─────────────────────────────────────────
        const liveMsg = adminMsg({ content: '!live' });
        await handleLive(liveMsg);

        // Livestream session created
        const session = stmts.livestream.getActiveSession.get();
        expect(session).toBeTruthy();
        expect(session.status).toBe('active');

        // Queue stays open
        expect(stmts.queues.getActiveQueue.get().id).toBe(queue.id);

        // WordPress toggle called
        expect(global.fetch).toHaveBeenCalledWith(
            expect.stringContaining('/wp-json/shop/v1/livestream'),
            expect.objectContaining({ method: 'POST' })
        );

        // Pre-order summary posted (queue had entries)
        expect(mockSendEmbed).toHaveBeenCalledWith('ANNOUNCEMENTS', expect.objectContaining({
            title: expect.stringContaining('Pre-Orders'),
        }));

        // Going-live announcement posted
        expect(mockSendToChannel).toHaveBeenCalledWith('ANNOUNCEMENTS', expect.objectContaining({
            embeds: expect.any(Array),
        }));

        // Confirm posted in channel
        expect(liveMsg.channel.send).toHaveBeenCalledWith(expect.stringContaining('Live session'));

        // ── Step 5: Live purchases arrive ───────────────────────────
        vi.clearAllMocks();

        await handleCheckoutCompleted(fakeCheckoutSession({
            sessionId: 'cs_live_1',
            email: 'alice@example.com',
            discordUserId: 'alice_discord',
            products: [{ name: 'Journey Together Booster', quantity: 1 }],
            live: true,
        }));

        await handleCheckoutCompleted(fakeCheckoutSession({
            sessionId: 'cs_live_2',
            email: 'charlie@example.com',
            discordUserId: 'charlie_discord',
            products: [{ name: 'Prismatic Evolutions ETB', quantity: 1 }],
            live: true,
        }));

        // Queue now has pre-order + live entries
        entries = stmts.queues.getEntries.all(queue.id);
        expect(entries).toHaveLength(5); // 3 pre-order + 2 live
        uniqueBuyers = stmts.queues.getUniqueBuyers.all(queue.id);
        expect(uniqueBuyers).toHaveLength(3); // alice, bob, charlie

        // Livestream buyers tracked (only live=1 purchases)
        const buyers = stmts.livestream.getBuyers.all(session.id);
        expect(buyers).toHaveLength(2); // alice + charlie (bob was pre-order only)

        // ── Step 6: Check duck race roster ──────────────────────────
        const duckMsg = adminMsg({ content: '!duckrace' });
        await handleDuckRace(duckMsg, []);
        expect(duckMsg.channel.send).toHaveBeenCalledWith(expect.objectContaining({
            embeds: expect.arrayContaining([
                expect.objectContaining({
                    data: expect.objectContaining({
                        title: expect.stringContaining('Duck Race'),
                    }),
                }),
            ]),
        }));

        // ── Step 7: Go offline ──────────────────────────────────────
        vi.clearAllMocks();

        const offlineMsg = adminMsg({ content: '!offline' });
        await handleOffline(offlineMsg);

        // Session ended
        expect(stmts.livestream.getActiveSession.get()).toBeUndefined();

        // Old queue closed
        const closedQueue = stmts.queues.getQueueById.get(queue.id);
        expect(closedQueue.status).toBe('closed');

        // New queue opened for next week's pre-orders
        const newQueue = stmts.queues.getActiveQueue.get();
        expect(newQueue).toBeTruthy();
        expect(newQueue.id).not.toBe(queue.id);

        // WordPress toggle called (disable live mode)
        expect(global.fetch).toHaveBeenCalledWith(
            expect.stringContaining('/wp-json/shop/v1/livestream'),
            expect.objectContaining({
                method: 'POST',
                body: expect.stringContaining('"active":false'),
            })
        );

        // Shipping DMs sent to livestream buyers
        expect(mockGetMember).toHaveBeenCalledWith('alice_discord');
        expect(mockGetMember).toHaveBeenCalledWith('charlie_discord');

        // Queue archived to #card-night-queue
        expect(mockSendEmbed).toHaveBeenCalledWith('CARD_NIGHT_QUEUE', expect.objectContaining({
            title: expect.stringContaining(`Queue #${queue.id}`),
        }));

        // Stream-ended announcement
        expect(mockSendEmbed).toHaveBeenCalledWith('ANNOUNCEMENTS', expect.objectContaining({
            title: expect.stringContaining("Stream's Over"),
        }));

        // Confirm in channel
        expect(offlineMsg.channel.send).toHaveBeenCalledWith(expect.stringContaining('ended'));

        // ── Step 8: Declare duck race winner ────────────────────────
        vi.clearAllMocks();

        const winnerMsg = adminMsg({ content: '!duckrace winner @alice' });
        const aliceMention = createMockMention('alice_discord');
        winnerMsg.mentions.users.first = vi.fn().mockReturnValue(aliceMention);
        await handleDuckRace(winnerMsg, ['winner', '@alice']);

        // Queue marked complete with winner
        const completedQueue = stmts.queues.getQueueById.get(queue.id);
        expect(completedQueue.status).toBe('complete');
        expect(completedQueue.duck_race_winner_id).toBe('alice_discord');

        // Winner announced in channel + announcements + and-in-the-back
        expect(winnerMsg.channel.send).toHaveBeenCalled();
        expect(mockSendEmbed).toHaveBeenCalledWith('ANNOUNCEMENTS', expect.objectContaining({
            title: expect.stringContaining('Duck Race Winner'),
        }));
        expect(mockSendEmbed).toHaveBeenCalledWith('AND_IN_THE_BACK', expect.objectContaining({
            title: expect.stringContaining('Duck Race Winner'),
        }));

        // ── Step 9: Verify queue history ────────────────────────────
        vi.clearAllMocks();

        const historyMsg = adminMsg({ content: '!queue history' });
        await handleQueue(historyMsg, ['history']);
        expect(historyMsg.channel.send).toHaveBeenCalled();

        const recentQueues = stmts.queues.getRecentQueues.all(5);
        expect(recentQueues).toHaveLength(1); // the closed/completed queue
        expect(recentQueues[0].duck_race_winner_id).toBe('alice_discord');
    });
});

// =========================================================================
// Permission Guards
// =========================================================================

describe('permission guards across livestream flow', () => {
    it('non-admin cannot open queue', async () => {
        const msg = userMsg({ content: '!queue open' });
        await handleQueue(msg, ['open']);
        expect(msg.reply).toHaveBeenCalledWith(expect.stringContaining('moderators'));
        expect(stmts.queues.getActiveQueue.get()).toBeUndefined();
    });

    it('non-admin cannot go live', async () => {
        const msg = userMsg({ content: '!live' });
        await handleLive(msg);
        expect(msg.reply).toHaveBeenCalledWith(expect.stringContaining('moderators'));
        expect(stmts.livestream.getActiveSession.get()).toBeUndefined();
    });

    it('non-admin cannot go offline', async () => {
        stmts.livestream.startSession.run();
        const msg = userMsg({ content: '!offline' });
        await handleOffline(msg);
        expect(msg.reply).toHaveBeenCalledWith(expect.stringContaining('moderators'));
        // Session still active
        expect(stmts.livestream.getActiveSession.get()).toBeTruthy();
    });

    it('non-admin cannot close queue', async () => {
        stmts.queues.createQueue.run();
        const msg = userMsg({ content: '!queue close' });
        await handleQueue(msg, ['close']);
        expect(msg.reply).toHaveBeenCalledWith(expect.stringContaining('moderators'));
        expect(stmts.queues.getActiveQueue.get()).toBeTruthy();
    });
});

// =========================================================================
// Guard Rails — prevent double-starts, close without open, etc.
// =========================================================================

describe('guard rails', () => {
    it('cannot open queue when one is already open', async () => {
        stmts.queues.createQueue.run();
        const msg = adminMsg();
        await handleQueue(msg, ['open']);
        expect(msg.reply).toHaveBeenCalledWith(expect.stringContaining('already'));
    });

    it('cannot go live when already live', async () => {
        stmts.livestream.startSession.run();
        const msg = adminMsg();
        await handleLive(msg);
        expect(msg.reply).toHaveBeenCalledWith(expect.stringContaining('Already live'));
    });

    it('cannot go offline when not live', async () => {
        const msg = adminMsg();
        await handleOffline(msg);
        expect(msg.reply).toHaveBeenCalledWith(expect.stringContaining('No active'));
    });

    it('cannot close queue when none is open', async () => {
        const msg = adminMsg();
        await handleQueue(msg, ['close']);
        expect(msg.reply).toHaveBeenCalledWith(expect.stringContaining('No open queue'));
    });

    it('duckrace winner must be in roster', async () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();
        stmts.queues.addEntry.run(queue.id, 'alice', 'a@e.com', 'Card', 1, 'cs_1');
        stmts.queues.closeQueue.run(queue.id);

        const msg = adminMsg();
        const notInRoster = createMockMention('intruder_discord');
        msg.mentions.users.first = vi.fn().mockReturnValue(notInRoster);
        await handleDuckRace(msg, ['winner', '@intruder']);

        expect(msg.reply).toHaveBeenCalledWith(expect.stringContaining('not in the duck race roster'));
    });
});

// =========================================================================
// Manual Mid-Stream Queue Close
// =========================================================================

describe('manual mid-stream queue close', () => {
    it('closing queue mid-stream stops new entries but does not break offline', async () => {
        // Setup: queue open, go live
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();
        stmts.livestream.startSession.run();
        const session = stmts.livestream.getActiveSession.get();

        // Pre-order arrives
        addToQueue('alice_discord', 'alice@example.com', 'Pack A', 1, 'cs_pre');
        addLivestreamBuyer('alice_discord', 'alice@example.com');

        expect(stmts.queues.getEntries.all(queue.id)).toHaveLength(1);

        // ── Manually close queue mid-stream ─────────────────────────
        const closeMsg = adminMsg({ content: '!queue close' });
        await handleQueue(closeMsg, ['close']);

        expect(stmts.queues.getActiveQueue.get()).toBeUndefined();
        expect(stmts.queues.getQueueById.get(queue.id).status).toBe('closed');

        // ── Purchases during closed queue are silently dropped ──────
        const added = addToQueue('bob_discord', 'bob@example.com', 'Pack B', 1, 'cs_dropped');
        expect(added).toBe(false);

        // But livestream buyer tracking still works (independent of queue)
        const tracked = addLivestreamBuyer('bob_discord', 'bob@example.com');
        expect(tracked).toBe(true);
        expect(stmts.livestream.getBuyers.all(session.id)).toHaveLength(2);

        // ── Offline still works cleanly ─────────────────────────────
        const offlineMsg = adminMsg({ content: '!offline' });
        await handleOffline(offlineMsg);

        // Session ended
        expect(stmts.livestream.getActiveSession.get()).toBeUndefined();

        // Shipping DMs sent to both buyers (alice and bob)
        expect(mockGetMember).toHaveBeenCalledWith('alice_discord');
        expect(mockGetMember).toHaveBeenCalledWith('bob_discord');

        // New queue opened
        const newQueue = stmts.queues.getActiveQueue.get();
        expect(newQueue).toBeTruthy();
        expect(newQueue.id).not.toBe(queue.id);

        // Offline doesn't crash when queue was already closed
        expect(offlineMsg.channel.send).toHaveBeenCalledWith(expect.stringContaining('ended'));
    });

    it('can reopen queue after manual close and resume taking orders', async () => {
        stmts.queues.createQueue.run();
        const firstQueue = stmts.queues.getActiveQueue.get();
        stmts.livestream.startSession.run();

        // Close mid-stream
        const closeMsg = adminMsg();
        await handleQueue(closeMsg, ['close']);
        expect(stmts.queues.getActiveQueue.get()).toBeUndefined();

        // Reopen
        const reopenMsg = adminMsg();
        await handleQueue(reopenMsg, ['open']);
        const secondQueue = stmts.queues.getActiveQueue.get();
        expect(secondQueue).toBeTruthy();
        expect(secondQueue.id).not.toBe(firstQueue.id);

        // New orders go into the new queue
        const added = addToQueue('charlie', 'c@e.com', 'Card', 1, 'cs_new');
        expect(added).toBe(true);
        expect(stmts.queues.getEntries.all(secondQueue.id)).toHaveLength(1);
        expect(stmts.queues.getEntries.all(firstQueue.id)).toHaveLength(0);
    });
});

// =========================================================================
// Stripe Webhook → Queue + Livestream Integration
// =========================================================================

describe('stripe webhook integration during livestream', () => {
    it('live purchase lands in both queue and livestream buyers', async () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();
        stmts.livestream.startSession.run();
        const session = stmts.livestream.getActiveSession.get();

        await handleCheckoutCompleted(fakeCheckoutSession({
            sessionId: 'cs_both_1',
            email: 'buyer@example.com',
            discordUserId: 'buyer_discord',
            products: [{ name: 'ETB', quantity: 1 }],
            live: true,
        }));

        // In queue
        const queueEntries = stmts.queues.getEntries.all(queue.id);
        expect(queueEntries).toHaveLength(1);
        expect(queueEntries[0].product_name).toBe('ETB');

        // In livestream buyers
        const buyers = stmts.livestream.getBuyers.all(session.id);
        expect(buyers).toHaveLength(1);
        expect(buyers[0].customer_email).toBe('buyer@example.com');
    });

    it('non-live purchase only lands in queue (not livestream buyers)', async () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();
        stmts.livestream.startSession.run();
        const session = stmts.livestream.getActiveSession.get();

        await handleCheckoutCompleted(fakeCheckoutSession({
            sessionId: 'cs_preorder_only',
            email: 'preorder@example.com',
            discordUserId: 'pre_discord',
            products: [{ name: 'Pre-order Pack', quantity: 1 }],
            live: false,
        }));

        expect(stmts.queues.getEntries.all(queue.id)).toHaveLength(1);
        expect(stmts.livestream.getBuyers.all(session.id)).toHaveLength(0);
    });

    it('purchase without active queue is silently dropped from queue', async () => {
        // No queue open, but livestream is active
        stmts.livestream.startSession.run();
        const session = stmts.livestream.getActiveSession.get();

        await handleCheckoutCompleted(fakeCheckoutSession({
            sessionId: 'cs_no_queue',
            email: 'buyer@example.com',
            discordUserId: 'buyer1',
            products: [{ name: 'Pack', quantity: 1 }],
            live: true,
        }));

        // No queue entries (no active queue)
        const allQueues = db.prepare('SELECT * FROM queue_entries').all();
        expect(allQueues).toHaveLength(0);

        // But livestream buyer is still tracked
        expect(stmts.livestream.getBuyers.all(session.id)).toHaveLength(1);
    });

    it('multi-item purchase creates multiple queue entries but one duck race entry', async () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();

        await handleCheckoutCompleted(fakeCheckoutSession({
            sessionId: 'cs_multi',
            email: 'collector@example.com',
            discordUserId: 'collector_discord',
            products: [
                { name: 'ETB', quantity: 1 },
                { name: 'Booster Box', quantity: 1 },
                { name: 'Elite Trainer', quantity: 3 },
            ],
        }));

        const entries = stmts.queues.getEntries.all(queue.id);
        expect(entries).toHaveLength(3); // one entry per line item

        const uniqueBuyers = stmts.queues.getUniqueBuyers.all(queue.id);
        expect(uniqueBuyers).toHaveLength(1); // one duck race entry
    });

    it('shipping payment does not add to queue or livestream buyers', async () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();
        stmts.livestream.startSession.run();
        const session = stmts.livestream.getActiveSession.get();
        stmts.livestream.addBuyer.run(session.id, 'buyer1', 'buyer@example.com');

        await handleCheckoutCompleted({
            id: 'cs_shipping',
            amount_total: 1000,
            metadata: {
                source: 'livestream-shipping',
                livestream_session_id: String(session.id),
                customer_email: 'buyer@example.com',
            },
            customer_details: { email: 'buyer@example.com' },
        });

        // Queue unchanged
        expect(stmts.queues.getEntries.all(queue.id)).toHaveLength(0);

        // Buyer now marked as shipping paid
        const unpaid = stmts.livestream.getBuyers.all(session.id);
        expect(unpaid).toHaveLength(0);
    });
});

// =========================================================================
// Shipping Scenarios During Offline
// =========================================================================

describe('shipping scenarios during offline', () => {
    it('repeat buyer within same week is marked as already covered', async () => {
        // Session 1 — buyer pays shipping (recorded in unified shipping_payments)
        stmts.livestream.startSession.run();
        const s1 = stmts.livestream.getActiveSession.get();
        stmts.livestream.addBuyer.run(s1.id, 'repeat_buyer', 'repeat@example.com');
        stmts.livestream.markShippingPaid.run(s1.id, 'repeat@example.com');
        stmts.shipping.record.run('repeat@example.com', 'repeat_buyer', 1000, 'livestream');
        stmts.livestream.endSession.run(s1.id);

        // Session 2 — same buyer buys again
        stmts.queues.createQueue.run();
        stmts.livestream.startSession.run();
        const s2 = stmts.livestream.getActiveSession.get();
        stmts.livestream.addBuyer.run(s2.id, 'repeat_buyer', 'repeat@example.com');

        // Unified shipping_payments should detect the earlier payment
        const paidThisWeek = stmts.shipping.hasShippingThisWeek.get('repeat@example.com');
        expect(paidThisWeek).toBeTruthy();

        // Go offline — buyer should be in alreadyCovered list
        const offlineMsg = adminMsg();
        await handleOffline(offlineMsg);

        // Confirm message mentions "already covered"
        expect(offlineMsg.channel.send).toHaveBeenCalledWith(
            expect.stringContaining('already covered')
        );
    });

    it('unlinked buyer (no discord ID) gets shipping posted to announcements', async () => {
        stmts.queues.createQueue.run();
        stmts.livestream.startSession.run();
        const session = stmts.livestream.getActiveSession.get();

        // Buyer with no discord ID
        stmts.livestream.addBuyer.run(session.id, null, 'anonymous@example.com');

        const offlineMsg = adminMsg();
        await handleOffline(offlineMsg);

        // getMember not called for null discord ID — fallback to channel post
        // (the handler checks buyer.discord_user_id before calling getMember)
        expect(mockSendToChannel).toHaveBeenCalledWith('ANNOUNCEMENTS', expect.objectContaining({
            content: 'anonymous@example.com',
        }));
    });

    it('offline with no buyers sends no DMs', async () => {
        stmts.queues.createQueue.run();
        stmts.livestream.startSession.run();

        const offlineMsg = adminMsg();
        await handleOffline(offlineMsg);

        // No shipping DMs attempted
        expect(mockGetMember).not.toHaveBeenCalled();

        // But session still ended and new queue opened
        expect(stmts.livestream.getActiveSession.get()).toBeUndefined();
        expect(stmts.queues.getActiveQueue.get()).toBeTruthy();
    });
});

// =========================================================================
// Edge Cases
// =========================================================================

describe('edge cases', () => {
    it('offline with no active queue still works', async () => {
        // Go live without a queue (unusual but possible)
        stmts.livestream.startSession.run();

        const offlineMsg = adminMsg();
        await handleOffline(offlineMsg);

        // Session ended
        expect(stmts.livestream.getActiveSession.get()).toBeUndefined();

        // New queue still opened
        expect(stmts.queues.getActiveQueue.get()).toBeTruthy();

        // No crash
        expect(offlineMsg.channel.send).toHaveBeenCalledWith(expect.stringContaining('ended'));
    });

    it('queue with zero entries still closes cleanly', async () => {
        stmts.queues.createQueue.run();
        stmts.livestream.startSession.run();

        const offlineMsg = adminMsg();
        await handleOffline(offlineMsg);

        const closedQueue = stmts.queues.getQueueById.get(1);
        expect(closedQueue.status).toBe('closed');
    });

    it('anyone can view queue and duck race (no admin required)', async () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();
        stmts.queues.addEntry.run(queue.id, 'user1', 'u@e.com', 'Card', 1, 'cs_1');

        // Non-admin can view queue
        const viewQueueMsg = userMsg();
        await handleQueue(viewQueueMsg, []);
        expect(viewQueueMsg.channel.send).toHaveBeenCalled();
        expect(viewQueueMsg.reply).not.toHaveBeenCalled();

        // Non-admin can view duck race
        const viewDuckMsg = userMsg();
        await handleDuckRace(viewDuckMsg, []);
        expect(viewDuckMsg.channel.send).toHaveBeenCalled();
    });

    it('live announcement includes pre-order count when queue has entries', async () => {
        stmts.queues.createQueue.run();
        const queue = stmts.queues.getActiveQueue.get();
        stmts.queues.addEntry.run(queue.id, 'u1', 'u@e.com', 'Card', 1, 'cs_1');
        stmts.queues.addEntry.run(queue.id, 'u2', 'u2@e.com', 'Card', 1, 'cs_2');

        const liveMsg = adminMsg();
        await handleLive(liveMsg);

        expect(mockSendEmbed).toHaveBeenCalledWith('ANNOUNCEMENTS', expect.objectContaining({
            title: expect.stringContaining('2 Pre-Orders'),
        }));
    });

    it('live announcement skips pre-order summary when queue is empty', async () => {
        stmts.queues.createQueue.run();

        const liveMsg = adminMsg();
        await handleLive(liveMsg);

        // sendEmbed should NOT be called with pre-order title
        const preOrderCalls = mockSendEmbed.mock.calls.filter(
            (call) => call[1]?.title?.includes('Pre-Orders')
        );
        expect(preOrderCalls).toHaveLength(0);
    });
});
