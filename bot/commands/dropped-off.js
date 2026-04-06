/**
 * Dropped Off Command — !dropped-off
 *
 * Notifies all buyers with unshipped orders that their packages are on the way.
 * Run this every Monday after dropping off packages at the post office.
 *
 * Flow:
 *   1. Query all purchases where shipped_at IS NULL and discord_user_id IS NOT NULL
 *   2. Group by user — one DM per person listing all their products
 *   3. Post a public "Orders Shipped" embed in #order-feed
 *   4. Post a detailed summary in #ops
 *   5. Stamp shipped_at on all processed rows
 *
 * Usage:
 *   !dropped-off
 */

import { EmbedBuilder } from 'discord.js';
import config from '../config.js';
import { purchases, discordLinks } from '../db.js';
import { getMember, sendEmbed, sendToChannel } from '../discord.js';

async function handleDroppedOff(message, args = []) {
    // Only owner can run this
    if (!message.member.roles.cache.has(config.ROLES.AKIVILI)) {
        return message.reply('Only the server owner can run this command.');
    }

    const isIntlMode = args[0]?.toLowerCase() === 'intl';

    // Get all unshipped purchases with linked Discord users
    let unshipped = purchases.getUnshipped.all();

    // Filter by domestic or international
    if (isIntlMode) {
        unshipped = unshipped.filter((row) => {
            const country = discordLinks.getCountry.get(row.discord_user_id);
            return country?.country && country.country !== 'US';
        });
    } else {
        unshipped = unshipped.filter((row) => {
            const country = discordLinks.getCountry.get(row.discord_user_id);
            return !country?.country || country.country === 'US';
        });
    }

    if (unshipped.length === 0) {
        return message.reply(`No unshipped ${isIntlMode ? 'international ' : ''}orders to notify.`);
    }

    // Group purchases by discord_user_id
    const byUser = new Map();
    for (const row of unshipped) {
        if (!byUser.has(row.discord_user_id)) {
            byUser.set(row.discord_user_id, []);
        }
        byUser.get(row.discord_user_id).push(row);
    }

    // Also count purchases without a linked Discord user (only for domestic mode)
    const skipped = isIntlMode ? [] : purchases.getUnshippedNoDiscord.all();

    let dmsSent = 0;
    let dmsFailed = 0;
    const failedUsers = [];

    // DM each unique buyer
    for (const [userId, userPurchases] of byUser) {
        // Build product list — aggregate quantities for duplicate product names
        const productCounts = new Map();
        for (const p of userPurchases) {
            const name = p.product_name || 'Unknown Product';
            productCounts.set(name, (productCounts.get(name) || 0) + 1);
        }

        const productList = [...productCounts.entries()]
            .map(([name, qty]) => qty > 1 ? `• ${name} (×${qty})` : `• ${name}`)
            .join('\n');

        const embed = new EmbedBuilder()
            .setTitle('📦 Your Order Is On Its Way!')
            .setDescription(
                `Hey! Your order from itzenzoTTV has been shipped:\n\n${productList}\n\n` +
                'If you need tracking info, just reach out to me here in the server.'
            )
            .setColor(0xceff00)
            .setFooter({ text: `Shipped ${new Date().toLocaleDateString('en-US', { timeZone: 'America/New_York' })}` });

        try {
            const member = await getMember(userId);
            if (member) {
                const dm = await member.createDM();
                await dm.send({ embeds: [embed] });
                dmsSent++;
            } else {
                dmsFailed++;
                failedUsers.push(userId);
            }
        } catch {
            dmsFailed++;
            failedUsers.push(userId);
        }
    }

    // Mark these purchases as shipped
    for (const row of unshipped) {
        purchases.markShipped.run();
    }

    // Post public notification in #order-feed
    const orderFeedTitle = isIntlMode ? '🌍 International Orders Shipped!' : '📬 Orders Shipped!';
    const orderFeedDesc = isIntlMode
        ? `International orders for this month have been shipped!\n\n` +
          `📦 **${unshipped.length} order${unshipped.length !== 1 ? 's' : ''} shipped to ${byUser.size} buyer${byUser.size !== 1 ? 's' : ''}** — Monthly batch\n\n` +
          'If you placed an international order, check your DMs for details.'
        : `All orders from this week have been dropped off and are on their way!\n\n` +
          `📦 **${unshipped.length + skipped.length} order${unshipped.length + skipped.length !== 1 ? 's' : ''} shipped to ${byUser.size + skipped.length} buyer${byUser.size + skipped.length !== 1 ? 's' : ''}**\n\n` +
          'If you placed an order, check your DMs for details.\n' +
          'Reach out if you need tracking info.';

    await sendEmbed('ORDER_FEED', {
        title: orderFeedTitle,
        description: orderFeedDesc,
        color: 0x3498db,
        footer: new Date().toLocaleDateString('en-US', { timeZone: 'America/New_York' }),
    });

    // Post detailed summary in #ops
    const opsLines = [
        `📬 **Dropped Off Summary**`,
        `• ${unshipped.length} orders across ${byUser.size} Discord-linked buyers`,
        `• ${dmsSent} DMs sent successfully`,
    ];

    if (dmsFailed > 0) {
        opsLines.push(`• ⚠️ ${dmsFailed} DMs failed: ${failedUsers.map((id) => `<@${id}>`).join(', ')}`);
    }

    if (skipped.length > 0) {
        opsLines.push(`• ${skipped.length} orders skipped (no linked Discord account)`);
    }

    await sendToChannel('OPS', opsLines.join('\n'));

    // Confirm in the command channel
    await message.channel.send(
        `📬 Done — ${dmsSent} buyer${dmsSent !== 1 ? 's' : ''} notified, ${unshipped.length + skipped.length} orders marked as shipped.`
    );
}

export { handleDroppedOff };
