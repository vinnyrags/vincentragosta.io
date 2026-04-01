/**
 * Tests for Discord command handler guards and validation.
 *
 * These test the permission checks, input validation, and error handling
 * in the command handlers. State mutation (DB writes) is tested in the
 * dedicated database-layer tests (battle.test.js, queue.test.js, etc.).
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createTestDb, buildStmts } from './setup.js';
import { createMockMessage } from './mocks.js';

let db, stmts;

// Channel and role IDs matching config.js
const PACK_BATTLES = '1488041101326811158';
const ROLE_AKIVILI = '1488046525065072670';
const ROLE_NANOOK = '1488046525899739148';

// Mock discord module
vi.mock('../discord', () => ({
    client: { channels: { cache: { get: vi.fn() } } },
    getChannel: vi.fn(),
    getGuild: vi.fn(),
    sendToChannel: vi.fn().mockResolvedValue(null),
    sendEmbed: vi.fn().mockResolvedValue(null),
    getMember: vi.fn().mockResolvedValue(null),
    hasRole: vi.fn().mockReturnValue(false),
    addRole: vi.fn().mockResolvedValue(false),
}));

global.fetch = vi.fn().mockResolvedValue({
    ok: true,
    json: () => Promise.resolve({}),
});

// Modules that need test DB injection (vi.mock can't intercept CJS require)
const queueMod = require('../commands/queue');

beforeEach(() => {
    db = createTestDb();
    stmts = buildStmts(db);
    queueMod._setDeps({ testDb: { queues: stmts.queues } });
    vi.clearAllMocks();
    global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({}),
    });
});

describe('!battle permission guards', () => {
    it('rejects commands outside #pack-battles', async () => {
        const { handleBattle } = require('../commands/battle');
        const msg = createMockMessage({
            channelId: 'wrong_channel',
            roles: [ROLE_AKIVILI],
        });

        await handleBattle(msg, ['start', 'Test']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('only work in')
        );
    });

    it('rejects non-admin battle management', async () => {
        const { handleBattle } = require('../commands/battle');
        const msg = createMockMessage({
            channelId: PACK_BATTLES,
            roles: [],
        });

        await handleBattle(msg, ['start', 'Test']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only moderators')
        );
    });

    it('allows anyone to check status', async () => {
        const { handleBattle } = require('../commands/battle');
        const msg = createMockMessage({
            channelId: PACK_BATTLES,
            roles: [],
        });

        await handleBattle(msg, ['status']);
        // Should not get "only moderators" — should get "no active battle"
        expect(msg.reply).toHaveBeenCalledWith('No active battle right now.');
    });

    it('prevents closing when no active battle', async () => {
        const { handleBattle } = require('../commands/battle');
        const msg = createMockMessage({
            channelId: PACK_BATTLES,
            roles: [ROLE_AKIVILI],
        });

        await handleBattle(msg, ['close']);
        expect(msg.reply).toHaveBeenCalledWith('No active battle to close.');
    });

    it('prevents cancelling when no active battle', async () => {
        const { handleBattle } = require('../commands/battle');
        const msg = createMockMessage({
            channelId: PACK_BATTLES,
            roles: [ROLE_AKIVILI],
        });

        await handleBattle(msg, ['cancel']);
        expect(msg.reply).toHaveBeenCalledWith('No active battle to cancel.');
    });

    it('prevents winner without closed battle', async () => {
        const { handleBattle } = require('../commands/battle');
        const msg = createMockMessage({
            channelId: PACK_BATTLES,
            roles: [ROLE_AKIVILI],
        });

        await handleBattle(msg, ['winner', '@someone']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('No closed battle')
        );
    });

    it('!battle join rejects non-owner', async () => {
        const { handleBattle } = require('../commands/battle');
        const msg = createMockMessage({
            channelId: PACK_BATTLES,
            roles: [ROLE_NANOOK],
        });

        await handleBattle(msg, ['join']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only the server owner')
        );
    });

    it('!battle join rejects when no active battle', async () => {
        const { handleBattle } = require('../commands/battle');
        const msg = createMockMessage({
            channelId: PACK_BATTLES,
            roles: [ROLE_AKIVILI],
        });

        await handleBattle(msg, ['join']);
        expect(msg.reply).toHaveBeenCalledWith('No active battle to join.');
    });

    it('shows usage for unknown subcommand', async () => {
        const { handleBattle } = require('../commands/battle');
        const msg = createMockMessage({
            channelId: PACK_BATTLES,
            roles: [ROLE_AKIVILI],
        });

        await handleBattle(msg, ['invalid']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Usage:')
        );
    });
});

describe('!queue permission guards', () => {
    it('shows no queue message when none exists', async () => {
        const { handleQueue } = require('../commands/queue');
        const msg = createMockMessage();

        await handleQueue(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('No open queue')
        );
    });

    it('rejects non-mod from opening', async () => {
        const { handleQueue } = require('../commands/queue');
        const msg = createMockMessage({ roles: [] });

        await handleQueue(msg, ['open']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only moderators')
        );
    });

    it('rejects non-mod from closing', async () => {
        const { handleQueue } = require('../commands/queue');
        const msg = createMockMessage({ roles: [] });

        await handleQueue(msg, ['close']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only moderators')
        );
    });
});

describe('!duckrace permission guards', () => {
    it('rejects non-mod from declaring winner', async () => {
        const { handleDuckRace } = require('../commands/queue');
        const msg = createMockMessage({ roles: [] });

        await handleDuckRace(msg, ['winner', '@someone']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only moderators')
        );
    });
});

describe('!link input validation', () => {
    it('rejects missing email', async () => {
        const { handleLink } = require('../commands/link');
        const msg = createMockMessage();

        await handleLink(msg, []);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Usage:')
        );
    });

    it('rejects invalid email format', async () => {
        const { handleLink } = require('../commands/link');
        const msg = createMockMessage();

        await handleLink(msg, ['notanemail']);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Usage:')
        );
    });

    it('deletes the command message to protect email', async () => {
        const { handleLink } = require('../commands/link');
        const msg = createMockMessage({ authorId: 'newuser' });

        await handleLink(msg, ['test@example.com']);
        expect(msg.delete).toHaveBeenCalled();
    });
});

describe('!live and !offline permission guards', () => {
    it('!live rejects non-admin', async () => {
        const { handleLive } = require('../commands/live');
        const msg = createMockMessage({ roles: [] });

        await handleLive(msg);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only moderators')
        );
    });

    it('!offline rejects non-admin', async () => {
        const { handleOffline } = require('../commands/live');
        const msg = createMockMessage({ roles: [] });

        await handleOffline(msg);
        expect(msg.reply).toHaveBeenCalledWith(
            expect.stringContaining('Only moderators')
        );
    });
});
