/**
 * Stripe Webhook Handler
 *
 * Handles:
 * - Order notifications → #order-feed
 * - Low-stock alerts → #deals
 * - Pack battle payment verification
 * - Queue auto-entries (card products → active queue)
 * - Role promotion (Xipe at 1+, Nous at 5+)
 */

const config = require('../config');
const { purchases, battles } = require('../db');
const { sendEmbed, getMember, addRole, hasRole } = require('../discord');
const { addToQueue } = require('../commands/queue');
const { addLivestreamBuyer } = require('../commands/live');

/**
 * Process a completed checkout session.
 */
async function handleCheckoutCompleted(session) {
    const customerEmail = session.customer_details?.email || session.customer_email;
    const lineItems = session.metadata?.line_items
        ? JSON.parse(session.metadata.line_items)
        : [];
    const totalAmount = session.amount_total;

    // Auto-link Discord username from Stripe checkout custom field
    const discordUsername = session.custom_fields
        ?.find((f) => f.key === 'discord_username')
        ?.text?.value?.trim();

    if (discordUsername && customerEmail) {
        await autoLinkDiscord(discordUsername, customerEmail);
    }

    // Try to find linked Discord user
    const link = purchases.getDiscordIdByEmail.get(customerEmail);
    const discordUserId = link?.discord_user_id || null;

    // Process each line item
    for (const item of lineItems) {
        const productName = item.name || 'Unknown Product';
        const quantity = item.quantity || 1;
        const stock = item.stock_remaining;

        // Record purchase
        purchases.insertPurchase.run(
            session.id,
            discordUserId,
            customerEmail,
            productName,
            totalAmount
        );

        // Post order notification in #order-feed
        await sendEmbed('ORDER_FEED', {
            title: '🛒 New Order!',
            description: `Someone just picked up **${productName}**${quantity > 1 ? ` (×${quantity})` : ''}!`,
            color: 0x2ecc71,
            footer: new Date().toLocaleString('en-US', { timeZone: 'America/New_York' }),
        });

        // Low-stock alert in #deals
        if (stock !== undefined && stock <= config.LOW_STOCK_THRESHOLD && stock > 0) {
            await sendEmbed('DEALS', {
                title: '⚠️ Low Stock Alert',
                description: `**${productName}** — only **${stock}** left in stock!`,
                color: 0xe74c3c,
            });
        }

        // Sold out alert
        if (stock !== undefined && stock === 0) {
            await sendEmbed('DEALS', {
                title: '🚫 Sold Out',
                description: `**${productName}** is now sold out!`,
                color: 0x95a5a6,
            });
        }
    }

    // Add card product purchases to the active queue
    for (const item of lineItems) {
        const productName = item.name || 'Unknown Product';
        const quantity = item.quantity || 1;
        const added = addToQueue(discordUserId, customerEmail, productName, quantity, session.id);
        if (added) {
            console.log(`Queue entry: ${productName} (×${quantity}) for ${discordUserId || customerEmail}`);
        }
    }

    // Track livestream buyers (purchases with live=1 metadata)
    if (session.metadata?.live === '1' && customerEmail) {
        const tracked = addLivestreamBuyer(discordUserId, customerEmail);
        if (tracked) {
            console.log(`Livestream buyer tracked: ${discordUserId || customerEmail}`);
        }
    }

    // Role promotion
    if (discordUserId) {
        purchases.incrementPurchaseCount.run(discordUserId);
        await checkRolePromotion(discordUserId);
    }

    // Check if this payment is for an active pack battle
    await checkBattlePayment(session, discordUserId);
}

/**
 * Auto-link a Discord username from Stripe checkout to their Discord user ID.
 * Searches guild members by username and saves the mapping.
 */
async function autoLinkDiscord(discordUsername, customerEmail) {
    const { getGuild } = require('../discord');
    const guild = getGuild();
    if (!guild) return;

    try {
        // Search for the member — try exact match on username or displayName
        const members = await guild.members.fetch({ query: discordUsername, limit: 5 });
        const match = members.find(
            (m) =>
                m.user.username.toLowerCase() === discordUsername.toLowerCase()
                || m.displayName.toLowerCase() === discordUsername.toLowerCase()
        );

        if (match) {
            purchases.linkDiscord.run(match.id, customerEmail);
            console.log(`Auto-linked ${match.user.tag} (${match.id}) via checkout field`);
        } else {
            console.log(`Could not find Discord member: "${discordUsername}"`);
        }
    } catch (e) {
        console.error('Auto-link error:', e.message);
    }
}

/**
 * Check and apply role promotions based on purchase count.
 * Lan (0) → Xipe (1+) → Nous (5+)
 */
async function checkRolePromotion(discordUserId) {
    const row = purchases.getPurchaseCount.get(discordUserId);
    if (!row) return;

    const count = row.total_purchases;
    const member = await getMember(discordUserId);
    if (!member) return;

    // Promote to Xipe at 1+ purchases
    if (count >= config.XIPE_PURCHASE_THRESHOLD) {
        const added = await addRole(member, config.ROLES.XIPE);
        if (added) {
            console.log(`Promoted ${member.user.tag} to Xipe (${count} purchases)`);
        }
    }

    // Promote to Nous at 5+ purchases
    if (count >= config.NOUS_PURCHASE_THRESHOLD) {
        if (!hasRole(member, config.ROLES.NOUS)) {
            await addRole(member, config.ROLES.NOUS);
            console.log(`Promoted ${member.user.tag} to Nous (${count} purchases)`);

            // Announce promotion
            await sendEmbed('ANNOUNCEMENTS', {
                title: '🎓 New Nous Member!',
                description: `<@${discordUserId}> has been promoted to **Nous** (Erudition) for making ${count} purchases! Welcome to the inner circle.`,
                color: 0x3498db,
            });
        }
    }
}

/**
 * Check if a payment is a pack battle purchase and auto-enter the buyer.
 * Purchase = entry. No reaction needed.
 */
async function checkBattlePayment(session, discordUserId) {
    // Only process pack-battle purchases
    if (session.metadata?.source !== 'pack-battle') return;

    const battle = battles.getActiveBattle.get();
    if (!battle) return;

    // Check if battle is full
    const entryCount = battles.getEntryCount.get(battle.id).count;
    if (entryCount >= battle.max_entries) {
        console.log(`Battle #${battle.id} is full — payment from ${discordUserId || 'unknown'} not added`);
        return;
    }

    // Add entry and mark as paid in one step
    const odiscordUserId = discordUserId || `unknown-${session.id}`;
    battles.addEntry.run(battle.id, odiscordUserId);
    battles.confirmPayment.run(session.id, battle.id, odiscordUserId);

    const paidEntries = battles.getPaidEntries.all(battle.id);

    // Update the battle message embed
    const { client } = require('../discord');
    try {
        const channel = client.channels.cache.get(config.CHANNELS.PACK_BATTLES);
        if (channel && battle.channel_message_id) {
            const msg = await channel.messages.fetch(battle.channel_message_id);
            const checkoutUrl = `${config.SHOP_URL.replace(/\/shop$/, '')}/bot/battle/checkout/${battle.id}`;

            const { EmbedBuilder } = require('discord.js');
            const embed = new EmbedBuilder()
                .setTitle(`⚔️ Pack Battle — ${battle.product_name}`)
                .setDescription(`🟢 OPEN — Buy your pack to enter!\n\n🛒 **[Buy your pack here](${checkoutUrl})**`)
                .setColor(0x2ecc71)
                .addFields(
                    { name: 'Entries', value: `${paidEntries.length}/${battle.max_entries}`, inline: true },
                );

            if (paidEntries.length > 0) {
                const roster = paidEntries.map((e, i) => `${i + 1}. <@${e.discord_user_id}>`).join('\n');
                embed.addFields({ name: 'Roster', value: roster });
            }

            embed.setFooter({ text: `Battle #${battle.id} • Purchase = entry. No other action needed.` });
            await msg.edit({ embeds: [embed] });
        }
    } catch (e) {
        console.error('Failed to update battle message:', e.message);
    }

    // Notify in channel
    const { sendToChannel } = require('../discord');
    await sendToChannel('PACK_BATTLES', `⚔️ <@${odiscordUserId}> is in! (${paidEntries.length}/${battle.max_entries})`);
}

module.exports = { handleCheckoutCompleted };
