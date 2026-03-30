/**
 * Account Linking — !link command.
 *
 * Links a Discord user to their email address for purchase tracking
 * and automatic role promotion.
 *
 * Usage: !link email@example.com
 */

const config = require('../config');
const { purchases } = require('../db');

async function handleLink(message, args) {
    const email = args[0]?.toLowerCase().trim();

    if (!email || !email.includes('@')) {
        return message.reply('Usage: `!link your@email.com` — link your Discord account to your shop email for automatic role upgrades.');
    }

    purchases.linkDiscord.run(message.author.id, email);

    // Delete the command message (contains email)
    try { await message.delete(); } catch { /* may not have perms */ }

    await message.channel.send(
        `✅ <@${message.author.id}> Your account has been linked. Purchases made with that email will count toward role upgrades.`
    );
}

module.exports = { handleLink };
