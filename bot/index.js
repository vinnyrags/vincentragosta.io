/**
 * itzenzoBot — Discord bot for itzenzoTTV.
 *
 * Features:
 *  - Order notifications (Stripe → #order-feed)
 *  - Low-stock alerts (Stripe → #deals)
 *  - Going-live / stream-ended (Twitch → #announcements)
 *  - Pack battle system (!battle commands + Stripe payment verification)
 *  - Duck race entry tracking (Stripe → purchase mapping)
 *  - Age verification (!verify → DM → Ena role)
 *  - Account linking (!link → email mapping for role promotion)
 *  - Role promotion (Lan → Xipe → Nous based on purchase count)
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
const { handleBattle, handleBattleReaction } = require('./commands/battle');
const { handleVerify } = require('./commands/verify');
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
            case 'battle':
                await handleBattle(message, args);
                break;
            case 'verify':
                await handleVerify(message);
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
// Reaction handler — pack battle joins
// =========================================================================

client.on('messageReactionAdd', async (reaction, user) => {
    // Fetch partial reactions
    if (reaction.partial) {
        try { await reaction.fetch(); } catch { return; }
    }
    if (reaction.message.partial) {
        try { await reaction.message.fetch(); } catch { return; }
    }

    try {
        await handleBattleReaction(reaction, user);
    } catch (e) {
        console.error('Error handling reaction:', e.message);
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
