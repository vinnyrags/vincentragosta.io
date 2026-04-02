/**
 * Nous — Discord bot for itzenzoTTV.
 *
 * Features:
 *  - Order notifications (Stripe → #order-feed)
 *  - Low-stock alerts (Stripe → #deals)
 *  - Going-live / stream-ended (Twitch → #announcements)
 *  - Livestream mode (!live / !offline — master switches for stream sessions)
 *  - Pack battle system (!battle commands + Stripe payment verification)
 *  - Queue system (!queue open/close + auto-entries from Stripe purchases)
 *  - Duck race (!duckrace — one entry per unique buyer in queue)
 *  - Account linking (!link → email mapping for role promotion)
 *  - Role promotion (Xipe at 1+ purchases, Long at 5+)
 *  - New product alerts (POST /alerts/products)
 *  - Pack battle results + duck race winners cross-posted to #and-in-the-back
 *  - Queue archives posted to #card-night-queue
 *
 * Usage:
 *   node bot/index.js
 *   npm start (from bot/ directory)
 */

import config from './config.js';
import { client } from './discord.js';
import { startServer } from './server.js';
import { handleLive, handleOffline } from './commands/live.js';
import { handleBattle } from './commands/battle.js';
import { handleQueue, handleDuckRace } from './commands/queue.js';
import { handleLink } from './commands/link.js';
import { handleSell, handleList, handleSold } from './commands/card-shop.js';
import { handleShipping } from './commands/shipping.js';
import { handleHype } from './commands/hype.js';
const PREFIX = '!';

// =========================================================================
// Message handler — route commands
// =========================================================================

client.on('messageCreate', async (message) => {
    // Ignore bots and DMs
    if (message.author.bot) return;
    if (!message.guild) return;

    // Only respond to messages with the prefix
    if (!message.content.startsWith(PREFIX)) return;

    const args = message.content.slice(PREFIX.length).trim().split(/\s+/);
    const command = args.shift().toLowerCase();

    try {
        switch (command) {
            case 'live':
                await handleLive(message);
                break;
            case 'offline':
                await handleOffline(message);
                break;
            case 'battle':
                await handleBattle(message, args);
                break;
            case 'queue':
                await handleQueue(message, args);
                break;
            case 'duckrace':
                await handleDuckRace(message, args);
                break;
            case 'link':
                await handleLink(message, args);
                break;
            case 'sell':
                await handleSell(message, args);
                break;
            case 'list':
                await handleList(message, args);
                break;
            case 'sold':
                await handleSold(message, args);
                break;
            case 'shipping':
                await handleShipping(message, args);
                break;
            case 'hype':
                await handleHype(message, args);
                break;
            default:
                // Unknown command — silently ignore
                break;
        }
    } catch (e) {
        console.error(`Error handling command !${command}:`, e.message);
        try {
            await message.reply('Something went wrong. Try again or ping a mod.');
        } catch { /* can't reply */ }
    }
});

// =========================================================================
// Ready
// =========================================================================

client.once('ready', () => {
    console.log(`Nous online as ${client.user.tag}`);
    console.log(`Guilds: ${client.guilds.cache.map((g) => g.name).join(', ')}`);

    // Start webhook server
    startServer();
});

// =========================================================================
// Error handling
// =========================================================================

client.on('error', (e) => console.error('Discord client error:', e.message));
process.on('unhandledRejection', (e) => console.error('Unhandled rejection:', e));

// =========================================================================
// Login
// =========================================================================

client.login(config.DISCORD_BOT_TOKEN);
