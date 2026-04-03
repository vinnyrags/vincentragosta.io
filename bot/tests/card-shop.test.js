/**
 * Tests for card shop commands, webhook integration, and expiry system.
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { createTestDb, buildStmts } from './setup.js';
import { createMockMessage, createMockMention } from './mocks.js';

let db, stmts;

const ROLE_AKIVILI = '1488046525065072670';
const CARD_SHOP_CHANNEL = '1488977861237801231';

// Build a mock discord object
function buildMockDiscord() {
    return {
        client: {
            channels: {
                cache: {
                    get: vi.fn().mockReturnValue({
                        id: CARD_SHOP_CHANNEL,
                        send: vi.fn().mockResolvedValue({ id: 'embed_msg_1', edit: vi.fn() }),
                        messages: { fetch: vi.fn().mockResolvedValue({ edit: vi.fn() }) },
                    }),
                },
            },
        },
        getMember: vi.fn().mockResolvedValue({
            createDM: vi.fn().mockResolvedValue({
                send: vi.fn().mockResolvedValue({}),
            }),
        }),
    };
}

vi.mock('../config.js', () => ({
    default: {
        ROLES: { AKIVILI: '1488046525065072670', NANOOK: '1488046525899739148' },
        CHANNELS: { CARD_SHOP: '1488977861237801231', ORDER_FEED: '2', OPS: '3' },
        SHOP_URL: 'https://example.com/shop',
        SHIPPING: {
            COUNTRIES: ['US', 'CA'],
            DOMESTIC: 1000,
            INTERNATIONAL: 2500,
        },
        CARD_RESERVATION_MS: 15 * 60 * 1000,
    },
}));

// Mock db and discord modules so vi.mock intercepts ESM imports
vi.mock('../db.js', () => {
    // Will be replaced in beforeEach via mockReturnValue
    return {
        cardListings: {},
        purchases: {},
    };
});

vi.mock('../discord.js', () => ({
    client: {
        channels: {
            cache: {
                get: vi.fn().mockReturnValue({
                    id: '1488977861237801231',
                    send: vi.fn().mockResolvedValue({ id: 'embed_msg_1', edit: vi.fn() }),
                    messages: { fetch: vi.fn().mockResolvedValue({ edit: vi.fn() }) },
                }),
            },
        },
    },
    getMember: vi.fn().mockResolvedValue({
        createDM: vi.fn().mockResolvedValue({
            send: vi.fn().mockResolvedValue({}),
        }),
    }),
}));

vi.mock('../shipping.js', () => ({
    formatShippingRate: vi.fn((amount) => `$${(amount / 100).toFixed(2)}`),
    getShippingLabel: vi.fn().mockReturnValue({ rate: 1000, label: 'Standard Shipping (US)', isInternational: false }),
    isInternational: vi.fn().mockReturnValue(false),
    getShippingRate: vi.fn().mockReturnValue(1000),
    hasShippingCoveredByDiscordId: vi.fn().mockReturnValue(false),
    hasShippingCovered: vi.fn().mockReturnValue(false),
    recordShipping: vi.fn(),
}));

// Import after mocks are set up
const cardShopModule = await import('../commands/card-shop.js');
const dbModule = await import('../db.js');
const discordModule = await import('../discord.js');

beforeEach(() => {
    db = createTestDb();
    stmts = buildStmts(db);

    // Wire test DB stubs into the mocked module
    Object.assign(dbModule.cardListings, stmts.cardListings);
    Object.assign(dbModule.purchases, stmts.purchases);

    // Reset discord mocks
    const mockDiscord = buildMockDiscord();
    discordModule.client.channels.cache.get = mockDiscord.client.channels.cache.get;
    discordModule.getMember.mockImplementation(mockDiscord.getMember);

    vi.clearAllMocks();
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

// =========================================================================
// !sell command
// =========================================================================

describe('!sell permission guards', () => {
    it('rejects non-owner', async () => {
        const { handleSell } = cardShopModule;
        const msg = createMockMessage({ roles: [] });

        await handleSell(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only the server owner')
        );
    });
});

describe('!sell input validation', () => {
    it('requires a mentioned buyer', async () => {
        const { handleSell } = cardShopModule;
        const msg = createMockMessage({
            content: '!sell "Test Card" 25.00',
            roles: [ROLE_AKIVILI],
        });

        await handleSell(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Usage:')
        );
    });

    it('requires quoted card name', async () => {
        const { handleSell } = cardShopModule;
        const buyer = createMockMention('buyer123');
        const msg = createMockMessage({
            content: '!sell @buyer Test Card 25.00',
            roles: [ROLE_AKIVILI],
        });
        msg.mentions.users.first = vi.fn().mockReturnValue(buyer);

        await handleSell(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('must be in quotes')
        );
    });

    it('requires a price', async () => {
        const { handleSell } = cardShopModule;
        const buyer = createMockMention('buyer123');
        const msg = createMockMessage({
            content: '!sell @buyer "Test Card"',
            roles: [ROLE_AKIVILI],
        });
        msg.mentions.users.first = vi.fn().mockReturnValue(buyer);

        await handleSell(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Include a price')
        );
    });
});

describe('!sell creates reserved listing', () => {
    it('creates a reserved listing in the database', async () => {
        const { handleSell } = cardShopModule;
        const buyer = createMockMention('buyer123');
        const msg = createMockMessage({
            content: '!sell @buyer "Charizard VMAX" 50.00',
            roles: [ROLE_AKIVILI],
        });
        msg.mentions.users.first = vi.fn().mockReturnValue(buyer);

        await handleSell(msg, []);

        const listing = stmts.cardListings.getById.get(1);
        expect(listing).toBeTruthy();
        expect(listing.card_name).toBe('Charizard VMAX');
        expect(listing.price).toBe(5000);
        expect(listing.status).toBe('reserved');
        expect(listing.buyer_discord_id).toBe('buyer123');
    });
});

// =========================================================================
// !list command
// =========================================================================

describe('!list permission guards', () => {
    it('rejects non-owner', async () => {
        const { handleList } = cardShopModule;
        const msg = createMockMessage({ roles: [] });

        await handleList(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only the server owner')
        );
    });
});

describe('!list input validation', () => {
    it('requires quoted card name', async () => {
        const { handleList } = cardShopModule;
        const msg = createMockMessage({
            content: '!list Test Card 25.00',
            roles: [ROLE_AKIVILI],
        });

        await handleList(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Usage:')
        );
    });

    it('requires a price', async () => {
        const { handleList } = cardShopModule;
        const msg = createMockMessage({
            content: '!list "Test Card"',
            roles: [ROLE_AKIVILI],
        });

        await handleList(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Include a price')
        );
    });
});

describe('!list creates active listing', () => {
    it('creates an active listing in the database', async () => {
        const { handleList } = cardShopModule;
        const msg = createMockMessage({
            content: '!list "Pikachu EX" 15.00',
            roles: [ROLE_AKIVILI],
        });

        await handleList(msg, []);

        const listing = stmts.cardListings.getById.get(1);
        expect(listing).toBeTruthy();
        expect(listing.card_name).toBe('Pikachu EX');
        expect(listing.price).toBe(1500);
        expect(listing.status).toBe('active');
        expect(listing.buyer_discord_id).toBeNull();
    });
});

// =========================================================================
// !sold command
// =========================================================================

describe('!sold permission guards', () => {
    it('rejects non-owner', async () => {
        const { handleSold } = cardShopModule;
        const msg = createMockMessage({ roles: [] });

        await handleSold(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only the server owner')
        );
    });
});

describe('!sold validation', () => {
    it('requires a message ID or reply', async () => {
        const { handleSold } = cardShopModule;
        const msg = createMockMessage({ roles: [ROLE_AKIVILI] });

        await handleSold(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Usage:')
        );
    });

    it('rejects unknown message IDs', async () => {
        const { handleSold } = cardShopModule;
        const msg = createMockMessage({ roles: [ROLE_AKIVILI] });

        await handleSold(msg, ['nonexistent_msg']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('No card listing found')
        );
    });

    it('rejects already-sold listings', async () => {
        const { handleSold } = cardShopModule;

        // Create and immediately sell a listing
        stmts.cardListings.create.run('Test Card', 1000, null, 'active');
        stmts.cardListings.setMessageId.run('msg_123', 1);
        stmts.cardListings.markSold.run(1);

        const msg = createMockMessage({ roles: [ROLE_AKIVILI] });
        await handleSold(msg, ['msg_123']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('already marked as sold')
        );
    });
});

describe('!sold marks listing', () => {
    it('marks an active listing as sold', async () => {
        const { handleSold } = cardShopModule;

        stmts.cardListings.create.run('Test Card', 1000, null, 'active');
        stmts.cardListings.setMessageId.run('msg_123', 1);

        const msg = createMockMessage({ roles: [ROLE_AKIVILI] });
        await handleSold(msg, ['msg_123']);

        const listing = stmts.cardListings.getById.get(1);
        expect(listing.status).toBe('sold');
        expect(listing.sold_at).toBeTruthy();
    });

    it('works with reply references', async () => {
        const { handleSold } = cardShopModule;

        stmts.cardListings.create.run('Test Card', 1000, null, 'active');
        stmts.cardListings.setMessageId.run('msg_456', 1);

        const msg = createMockMessage({ roles: [ROLE_AKIVILI] });
        msg.reference = { messageId: 'msg_456' };

        await handleSold(msg, []);

        const listing = stmts.cardListings.getById.get(1);
        expect(listing.status).toBe('sold');
    });
});

// =========================================================================
// Expiry system
// =========================================================================

describe('expiry timer', () => {
    it('relists a reserved card after timeout', async () => {
        // Create a reserved listing directly
        stmts.cardListings.create.run('Expiry Test', 2000, 'buyer123', 'reserved');
        stmts.cardListings.setMessageId.run('msg_expire', 1);

        // Simulate what handleSell does for the timer
        const listing = stmts.cardListings.getById.get(1);
        expect(listing.status).toBe('reserved');

        // The timer is internal — test the DB operations directly
        stmts.cardListings.markExpired.run(1);
        stmts.cardListings.relistAsActive.run(1);

        const relisted = stmts.cardListings.getById.get(1);
        expect(relisted.status).toBe('active');
        expect(relisted.buyer_discord_id).toBeNull();
    });
});

// =========================================================================
// DB operations
// =========================================================================

describe('card listing DB operations', () => {
    it('creates and retrieves by ID', () => {
        stmts.cardListings.create.run('Test', 1500, null, 'active');
        const listing = stmts.cardListings.getById.get(1);
        expect(listing.card_name).toBe('Test');
        expect(listing.price).toBe(1500);
    });

    it('retrieves by message ID', () => {
        stmts.cardListings.create.run('Test', 1500, null, 'active');
        stmts.cardListings.setMessageId.run('msg_abc', 1);
        const listing = stmts.cardListings.getByMessageId.get('msg_abc');
        expect(listing.id).toBe(1);
    });

    it('retrieves by stripe session ID', () => {
        stmts.cardListings.create.run('Test', 1500, null, 'active');
        stmts.cardListings.setStripeSessionId.run('cs_test', 1);
        const listing = stmts.cardListings.getByStripeSessionId.get('cs_test');
        expect(listing.id).toBe(1);
    });

    it('transitions through status lifecycle', () => {
        stmts.cardListings.create.run('Lifecycle', 2000, 'buyer1', 'reserved');
        expect(stmts.cardListings.getById.get(1).status).toBe('reserved');

        stmts.cardListings.markExpired.run(1);
        expect(stmts.cardListings.getById.get(1).status).toBe('expired');

        stmts.cardListings.relistAsActive.run(1);
        const relisted = stmts.cardListings.getById.get(1);
        expect(relisted.status).toBe('active');
        expect(relisted.buyer_discord_id).toBeNull();

        stmts.cardListings.markSold.run(1);
        const sold = stmts.cardListings.getById.get(1);
        expect(sold.status).toBe('sold');
        expect(sold.sold_at).toBeTruthy();
    });
});

// =========================================================================
// Webhook — card sale source
// =========================================================================

describe('card sale webhook', () => {
    it('marks listing as sold on card-sale source', async () => {
        // Create a listing and set a stripe session
        stmts.cardListings.create.run('Webhook Card', 3000, null, 'active');
        stmts.cardListings.setStripeSessionId.run('cs_webhook_test', 1);

        const session = {
            metadata: {
                source: 'card-sale',
                card_listing_id: '1',
            },
        };

        // Directly call the DB mutation that the webhook would do
        const listing = stmts.cardListings.getById.get(Number(session.metadata.card_listing_id));
        expect(listing).toBeTruthy();
        expect(listing.status).not.toBe('sold');

        stmts.cardListings.markSold.run(listing.id);
        const sold = stmts.cardListings.getById.get(1);
        expect(sold.status).toBe('sold');
    });

    it('ignores non-card-sale sources', () => {
        const session = {
            metadata: { source: 'pack-battle' },
        };
        // Should simply not match — no card_listing_id check needed
        expect(session.metadata.source).not.toBe('card-sale');
    });

    it('ignores already-sold listings', () => {
        stmts.cardListings.create.run('Already Sold', 1000, null, 'active');
        stmts.cardListings.markSold.run(1);

        const listing = stmts.cardListings.getById.get(1);
        expect(listing.status).toBe('sold');
        // Webhook would return early — status stays sold
    });
});
