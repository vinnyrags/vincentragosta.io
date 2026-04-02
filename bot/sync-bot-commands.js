/**
 * Sync #bot-commands channel with the canonical command reference.
 *
 * On startup, compares existing bot messages in #bot-commands to the
 * content defined in bot-commands.js. Edits messages that have changed,
 * posts missing ones, and deletes extras. Only touches messages authored
 * by the bot itself.
 */

import { client } from './discord.js';
import config from './config.js';
import commandMessages from './bot-commands.js';

async function syncBotCommands() {
    const channel = client.channels.cache.get(config.CHANNELS.BOT_COMMANDS);
    if (!channel) {
        console.error('Bot commands channel not found — skipping sync');
        return;
    }

    try {
        // Fetch all messages in the channel (up to 100 — well within our count)
        const fetched = await channel.messages.fetch({ limit: 100 });

        // Filter to only bot's own messages, sorted oldest-first
        const botMessages = [...fetched.values()]
            .filter((m) => m.author.id === client.user.id)
            .sort((a, b) => a.createdTimestamp - b.createdTimestamp);

        let edited = 0;
        let posted = 0;
        let deleted = 0;

        // Update or post each message in order
        for (let i = 0; i < commandMessages.length; i++) {
            const desired = commandMessages[i];

            if (i < botMessages.length) {
                // Existing message — edit if content differs
                if (botMessages[i].content !== desired) {
                    await botMessages[i].edit(desired);
                    edited++;
                }
            } else {
                // No existing message at this position — post new
                await channel.send(desired);
                posted++;
            }
        }

        // Delete any extra bot messages beyond the expected count
        for (let i = commandMessages.length; i < botMessages.length; i++) {
            await botMessages[i].delete();
            deleted++;
        }

        const changes = edited + posted + deleted;
        if (changes > 0) {
            console.log(`Bot commands synced: ${edited} edited, ${posted} posted, ${deleted} deleted`);
        } else {
            console.log('Bot commands up to date');
        }
    } catch (e) {
        console.error('Failed to sync bot commands:', e.message);
    }
}

export { syncBotCommands };
