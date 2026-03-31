/**
 * itzenzoBot — Discord bot for itzenzoTTV.
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
 *  - Role promotion (Xipe at 1+ purchases, Nous at 5+)
 *  - New product alerts (POST /alerts/products)
 *  - Pack battle results cross-posted to #pack-openings
 *
 * Usage:
 *   node bot/index.js
 *   npm start (from bot/ directory)
 */

const config = require('./config');
const { client } = require('./discord');
const { startServer } = require('./server');
const { handleLive, handleOffline } = require('./commands/live');
const { handleBattle } = require('./commands/battle');
const { handleQueue, handleDuckRace } = require('./commands/queue');
const { handleLink } = require('./commands/link');
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
    console.log(`itzenzoBot online as ${client.user.tag}`);
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
