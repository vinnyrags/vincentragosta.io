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

    // Delete the command message immediately (contains email)
    try { await message.delete(); } catch { /* may not have perms */ }

    // Validate email exists in Stripe
    try {
        const stripe = require('stripe')(config.STRIPE_SECRET_KEY);
        const customers = await stripe.customers.list({ email, limit: 1 });

        if (!customers.data.length) {
            return message.channel.send(
                `❌ <@${message.author.id}> No purchases found for that email. Make sure you're using the same email you used at checkout.`
            );
        }
    } catch (e) {
        console.error('Stripe customer lookup error:', e.message);
        return message.channel.send(
            `⚠️ <@${message.author.id}> Could not verify email right now. Try again later.`
        );
    }

    purchases.linkDiscord.run(message.author.id, email);

    await message.channel.send(
        `✅ <@${message.author.id}> Your account has been linked. Purchases made with that email will count toward role upgrades.`
    );
}

module.exports = { handleLink };
