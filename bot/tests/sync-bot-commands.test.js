/**
 * Tests for bot-commands sync — verifies edit/post/delete logic.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

const BOT_USER_ID = 'bot123';

// Mock channel with configurable existing messages
function createMockChannel(existingMessages = []) {
    const messages = existingMessages.map((content, i) => ({
        id: `msg_${i}`,
        content,
        author: { id: BOT_USER_ID },
        createdTimestamp: i,
        edit: vi.fn().mockResolvedValue({}),
        delete: vi.fn().mockResolvedValue({}),
    }));

    return {
        messages: {
            fetch: vi.fn().mockResolvedValue(new Map(messages.map((m) => [m.id, m]))),
        },
        send: vi.fn().mockResolvedValue({ id: 'new_msg' }),
        _messages: messages,
    };
}

let mockChannel;

vi.mock('../discord.js', () => ({
    client: {
        user: { id: BOT_USER_ID },
        channels: {
            cache: {
                get: vi.fn(),
            },
        },
    },
}));

vi.mock('../config.js', () => ({
    default: {
        CHANNELS: { BOT_COMMANDS: 'bot-commands-id' },
    },
}));

// Default: 2 simple messages
vi.mock('../bot-commands.js', () => ({
    default: ['Message 1', 'Message 2'],
}));

const { client } = await import('../discord.js');
const { syncBotCommands } = await import('../sync-bot-commands.js');

beforeEach(() => {
    vi.clearAllMocks();
});

describe('syncBotCommands', () => {
    it('does nothing when content matches', async () => {
        mockChannel = createMockChannel(['Message 1', 'Message 2']);
        client.channels.cache.get.mockReturnValue(mockChannel);

        await syncBotCommands();

        // No edits, no posts, no deletes
        for (const msg of mockChannel._messages) {
            expect(msg.edit).not.toHaveBeenCalled();
            expect(msg.delete).not.toHaveBeenCalled();
        }
        expect(mockChannel.send).not.toHaveBeenCalled();
    });

    it('edits messages that have changed', async () => {
        mockChannel = createMockChannel(['Old Message 1', 'Message 2']);
        client.channels.cache.get.mockReturnValue(mockChannel);

        await syncBotCommands();

        expect(mockChannel._messages[0].edit).toHaveBeenCalledWith('Message 1');
        expect(mockChannel._messages[1].edit).not.toHaveBeenCalled();
    });

    it('posts missing messages', async () => {
        mockChannel = createMockChannel(['Message 1']);
        client.channels.cache.get.mockReturnValue(mockChannel);

        await syncBotCommands();

        expect(mockChannel.send).toHaveBeenCalledWith('Message 2');
    });

    it('deletes extra messages', async () => {
        mockChannel = createMockChannel(['Message 1', 'Message 2', 'Extra message']);
        client.channels.cache.get.mockReturnValue(mockChannel);

        await syncBotCommands();

        expect(mockChannel._messages[2].delete).toHaveBeenCalled();
    });

    it('handles empty channel (posts all messages)', async () => {
        mockChannel = createMockChannel([]);
        client.channels.cache.get.mockReturnValue(mockChannel);

        await syncBotCommands();

        expect(mockChannel.send).toHaveBeenCalledTimes(2);
        expect(mockChannel.send).toHaveBeenCalledWith('Message 1');
        expect(mockChannel.send).toHaveBeenCalledWith('Message 2');
    });

    it('skips sync when channel not found', async () => {
        client.channels.cache.get.mockReturnValue(undefined);

        // Should not throw
        await syncBotCommands();
    });

    it('handles API errors gracefully', async () => {
        mockChannel = createMockChannel([]);
        mockChannel.messages.fetch.mockRejectedValue(new Error('API error'));
        client.channels.cache.get.mockReturnValue(mockChannel);

        // Should not throw
        await syncBotCommands();
    });
});
