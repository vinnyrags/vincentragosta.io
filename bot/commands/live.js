/**
 * Livestream Master Commands
 *
 * !live    — Go live: close pre-order queue, start livestream session, post shop link
 * !offline — End stream: end livestream session, send shipping DMs, open next queue
 */

import { EmbedBuilder } from 'discord.js';
import config from '../config.js';
import { livestream, queues, analytics, goals } from '../db.js';
import { sendEmbed, sendToChannel, getMember, getGuild } from '../discord.js';
import {
    hasShippingCovered,
    isInternationalByEmail,
    formatShippingRate,
    recordShipping,
} from '../shipping.js';

/**
 * Toggle the server-side livestream transient via WordPress REST API.
 * This ensures ?live=1 only works when actually live.
 */
async function toggleLivestreamMode(active) {
    const url = `${config.SITE_URL}/wp-json/shop/v1/livestream`;
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                active,
                secret: config.LIVESTREAM_SECRET,
            }),
        });
        if (!response.ok) {
            console.error(`Failed to toggle livestream mode: ${response.status}`);
            return null;
        }
        const data = await response.json();
        return data.token || null;
    } catch (e) {
        console.error('Could not reach WordPress to toggle livestream mode:', e.message);
        return null;
    }
}

// =========================================================================
// !live
// =========================================================================

async function handleLive(message) {
    const isAdmin = message.member.roles.cache.has(config.ROLES.NANOOK)
        || message.member.roles.cache.has(config.ROLES.AKIVILI);

    if (!isAdmin) {
        return message.reply('Only moderators can use this command.');
    }

    // Check if already live
    const active = livestream.getActiveSession.get();
    if (active) {
        return message.reply('Already live! Use `!offline` to end the current session first.');
    }

    // Enable shipping-free mode on the WordPress side (returns signed token)
    const liveToken = await toggleLivestreamMode(true);

    // Start 4-hour reminder
    startReminder();

    // Auto-open queue if none exists (first stream or manual close)
    let activeQueue = queues.getActiveQueue.get();
    if (!activeQueue) {
        const queueResult = queues.createQueue.run();
        activeQueue = queues.getActiveQueue.get();
        await message.channel.send({ embeds: [new EmbedBuilder()
            .setDescription(`📋 No queue was open — auto-opened queue #${activeQueue.id}`)
            .setColor(0x3498db)] });
    }

    // Post pre-order summary if queue has entries (but keep it open)
    if (activeQueue) {
        const entries = queues.getEntries.all(activeQueue.id);
        const uniqueBuyers = queues.getUniqueBuyers.all(activeQueue.id);

        if (entries.length > 0) {
            await sendEmbed('ANNOUNCEMENTS', {
                title: `📋 ${entries.length} Pre-Orders Tonight!`,
                description: `${uniqueBuyers.length} buyer(s) already in the queue. Let's go!`,
                color: 0x2ecc71,
            });
        }
    }

    // Start livestream session
    const result = livestream.startSession.run();
    const sessionId = result.lastInsertRowid;

    const shopLink = liveToken
        ? `${config.SHOP_URL}?live=1&token=${liveToken}`
        : `${config.SHOP_URL}?live=1`;

    // Post going-live announcement
    const embed = new EmbedBuilder()
        .setTitle('🔴 We\'re Live!')
        .setDescription(
            `Card night is starting! Come hang.\n\n` +
            `🛒 **[Shop Now](${shopLink})** — shipping is collected once at the end of the stream.\n\n` +
            `👉 [Watch on Twitch](https://twitch.tv/itzenzoTTV)`
        )
        .setColor(0x9146ff);

    await sendToChannel('ANNOUNCEMENTS', { embeds: [embed] });

    // Confirm in current channel
    const confirmEmbed = new EmbedBuilder()
        .setTitle('🔴 Live Session Started')
        .setDescription(
            `**Session #${sessionId}**\n\n` +
            `• Queue${activeQueue ? ` #${activeQueue.id}` : ''} stays open — orders continue adding\n` +
            `• Livestream purchases are shipping-free — collected after \`!offline\``
        )
        .addFields({ name: 'Shop Link', value: shopLink })
        .setColor(0x9146ff);

    await message.channel.send({ embeds: [confirmEmbed] });
}

// =========================================================================
// !offline
// =========================================================================

async function handleOffline(message) {
    const isAdmin = message.member.roles.cache.has(config.ROLES.NANOOK)
        || message.member.roles.cache.has(config.ROLES.AKIVILI);

    if (!isAdmin) {
        return message.reply('Only moderators can use this command.');
    }

    const session = livestream.getActiveSession.get();
    if (!session) {
        return message.reply('No active livestream session. Use `!live` to start one.');
    }

    // End the session
    livestream.endSession.run(session.id);

    // Disable shipping-free mode on the WordPress side
    await toggleLivestreamMode(false);

    // Cancel the reminder
    cancelReminder();

    // Get unique buyers who need shipping (shipping_paid = 0 for this session)
    const buyers = livestream.getBuyers.all(session.id);

    // Split buyers into three groups using the unified shipping tracker
    const domesticNeedsShipping = [];
    const internationalNeedsShipping = [];
    const alreadyCovered = [];

    for (const buyer of buyers) {
        if (hasShippingCovered(buyer.customer_email)) {
            alreadyCovered.push(buyer);
        } else if (isInternationalByEmail(buyer.customer_email)) {
            internationalNeedsShipping.push(buyer);
        } else {
            domesticNeedsShipping.push(buyer);
        }
    }

    // Mark already-covered buyers as paid so they aren't DM'd again
    for (const buyer of alreadyCovered) {
        livestream.markShippingPaid.run(session.id, buyer.customer_email);
    }

    // Send "already covered" DMs
    for (const buyer of alreadyCovered) {
        if (!buyer.discord_user_id) continue;
        const coveredEmbed = new EmbedBuilder()
            .setTitle('📦 You\'re All Set!')
            .setDescription(
                `You've already paid shipping this period — tonight's items will ship with your existing order. No additional shipping needed!`
            )
            .setColor(0x2ecc71)
            .setFooter({ text: 'Shipping already covered.' });

        try {
            const member = await getMember(buyer.discord_user_id);
            if (member) {
                const dm = await member.createDM();
                await dm.send({ embeds: [coveredEmbed] });
            }
        } catch { /* DMs disabled — skip */ }
    }

    // Send shipping DMs to buyers who need to pay
    let shippingsSent = 0;
    const baseShippingUrl = `${config.SHOP_URL.replace(/\/shop$/, '')}/bot/livestream/shipping/${session.id}`;

    // Domestic buyers — $10 weekly
    for (const buyer of domesticNeedsShipping) {
        const isPlaceholder = buyer.customer_email.includes('@placeholder');
        const params = [];
        if (!isPlaceholder) params.push(`email=${encodeURIComponent(buyer.customer_email)}`);
        if (buyer.discord_user_id) params.push(`user=${buyer.discord_user_id}`);
        const queryString = params.length ? `?${params.join('&')}` : '';

        const shippingEmbed = new EmbedBuilder()
            .setTitle('📦 Shipping for Tonight\'s Orders')
            .setDescription(
                `Thanks for being part of tonight's stream!\n\n` +
                `Click below to pay shipping (${formatShippingRate(config.SHIPPING.DOMESTIC)} flat rate) and enter your address. This covers everything you buy this week — if you come back before Monday, you won't be charged again.\n\n` +
                `📦 **[Pay Shipping & Enter Address](${baseShippingUrl}${queryString})**`
            )
            .setColor(0x2ecc71)
            .setFooter({ text: `${formatShippingRate(config.SHIPPING.DOMESTIC)} flat rate — covers all items and winnings through Monday.` });

        shippingsSent += await sendShippingDm(buyer, shippingEmbed);
    }

    // International buyers — $25 monthly
    for (const buyer of internationalNeedsShipping) {
        const params = [];
        if (!buyer.customer_email.includes('@placeholder')) params.push(`email=${encodeURIComponent(buyer.customer_email)}`);
        if (buyer.discord_user_id) params.push(`user=${buyer.discord_user_id}`);
        const queryString = params.length ? `?${params.join('&')}` : '';

        const shippingEmbed = new EmbedBuilder()
            .setTitle('🌍 International Shipping for Tonight\'s Orders')
            .setDescription(
                `Thanks for being part of tonight's stream!\n\n` +
                `Click below to pay shipping (${formatShippingRate(config.SHIPPING.INTERNATIONAL)} international flat rate) and enter your address. This covers all your purchases through the end of the month.\n\n` +
                `📦 **[Pay Shipping & Enter Address](${baseShippingUrl}${queryString})**`
            )
            .setColor(0x3498db)
            .setFooter({ text: `${formatShippingRate(config.SHIPPING.INTERNATIONAL)} flat rate — covers all items this month.` });

        shippingsSent += await sendShippingDm(buyer, shippingEmbed);
    }

    /**
     * Send a shipping DM to a buyer. Falls back to announcements if DMs disabled.
     * Returns 1 if sent, 0 if failed.
     */
    async function sendShippingDm(buyer, embed) {
        if (buyer.discord_user_id) {
            try {
                const member = await getMember(buyer.discord_user_id);
                if (member) {
                    const dm = await member.createDM();
                    await dm.send({ embeds: [embed] });
                    return 1;
                }
            } catch { /* DMs disabled — fall through */ }
        }

        // Fallback: post in announcements
        await sendToChannel('ANNOUNCEMENTS', {
            content: buyer.discord_user_id ? `<@${buyer.discord_user_id}>` : buyer.customer_email,
            embeds: [embed],
        });
        return 1;
    }

    // Close the current queue and archive it
    const activeQueue = queues.getActiveQueue.get();
    let closedQueueId = null;
    if (activeQueue) {
        queues.closeQueue.run(activeQueue.id);
        closedQueueId = activeQueue.id;
        const entries = queues.getEntries.all(activeQueue.id);
        const uniqueBuyers = queues.getUniqueBuyers.all(activeQueue.id);

        if (entries.length > 0) {
            await sendEmbed('CARD_NIGHT_QUEUE', {
                title: `📋 Queue #${activeQueue.id} — ${entries.length} items from ${uniqueBuyers.length} buyers`,
                description: 'Tonight\'s queue archived.',
                color: 0x95a5a6,
                footer: `Opened: ${activeQueue.created_at}`,
            });
        }
    }

    // Open next queue for pre-orders
    const queueResult = queues.createQueue.run();
    const newQueueId = queueResult.lastInsertRowid;

    // Post stream-ended recap
    await sendEmbed('ANNOUNCEMENTS', {
        title: '📴 Stream\'s Over!',
        description: 'Thanks for hanging out! Clips and highlights coming soon.\n\nPre-order queue is open for next stream.',
        color: 0x95a5a6,
    });

    // Post analytics recap to #analytics
    await postStreamRecap(session, closedQueueId);

    // Confirm in current channel
    const shippingParts = [];
    if (domesticNeedsShipping.length) shippingParts.push(`${domesticNeedsShipping.length} domestic`);
    if (internationalNeedsShipping.length) shippingParts.push(`${internationalNeedsShipping.length} international`);
    if (alreadyCovered.length) shippingParts.push(`${alreadyCovered.length} already covered`);
    const shippingSummary = shippingParts.length ? shippingParts.join(', ') : 'no buyers';

    const offlineEmbed = new EmbedBuilder()
        .setTitle('📴 Live Session Ended')
        .setDescription(`**Session #${session.id}**`)
        .addFields(
            { name: 'Queue', value: `${closedQueueId ? `#${closedQueueId} closed` : 'None'} → archived to <#${config.CHANNELS.CARD_NIGHT_QUEUE}>`, inline: false },
            { name: 'Shipping', value: `${shippingSummary} (${shippingsSent} DMs sent)`, inline: false },
            { name: 'Next Queue', value: `#${newQueueId} opened for pre-orders`, inline: false },
        )
        .setColor(0x95a5a6)
        .setFooter({ text: 'Stream recap posted to #analytics' });

    await message.channel.send({ embeds: [offlineEmbed] });
}

// =========================================================================
// Post-stream analytics recap — posted to #analytics after !offline
// =========================================================================

function formatDollars(cents) {
    return `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

async function postStreamRecap(session, closedQueueId) {
    const startTime = session.created_at;
    const endTime = new Date().toISOString();

    // Session stats
    const stats = analytics.getRangeStats.get(startTime, endTime);
    const topProducts = analytics.getTopProducts.all(startTime, endTime);
    const newBuyers = analytics.getNewBuyerCount.get(startTime, endTime, startTime);
    const battleCount = analytics.getBattleCount.get(startTime, endTime);

    // Queue stats
    let queueEntryCount = 0;
    let queueBuyerCount = 0;
    if (closedQueueId) {
        const entries = queues.getEntries.all(closedQueueId);
        const uniqueBuyers = queues.getUniqueBuyers.all(closedQueueId);
        queueEntryCount = entries.length;
        queueBuyerCount = uniqueBuyers.length;
    }

    // Community goal delta
    const goal = goals.get.get();
    const sessionRevenueDollars = formatDollars(stats.total_revenue);

    // Build embed
    const returningBuyers = stats.unique_buyers - newBuyers.count;
    const lines = [
        `**Revenue:** ${sessionRevenueDollars}`,
        `**Orders:** ${stats.order_count}`,
        `**Buyers:** ${stats.unique_buyers} (${newBuyers.count} new, ${returningBuyers} returning)`,
    ];

    if (battleCount.count > 0) {
        lines.push(`**Battles:** ${battleCount.count}`);
    }

    if (queueEntryCount > 0) {
        lines.push(`**Queue:** ${queueEntryCount} items from ${queueBuyerCount} buyers`);
    }

    const embed = new EmbedBuilder()
        .setTitle(`📊 Stream Recap — Session #${session.id}`)
        .setDescription(lines.join('\n'))
        .setColor(0x2ecc71);

    // Top products
    if (topProducts.length > 0) {
        const productLines = topProducts.map((p, i) =>
            `${i + 1}. **${p.product_name}** — ${p.count} sold (${formatDollars(p.revenue)})`
        );
        embed.addFields({ name: 'Top Products', value: productLines.join('\n') });
    }

    // Community goal state
    const cyclePercent = Math.min(Math.round((goal.cycle_revenue / 250000) * 100), 100);
    const goalLine = `Cycle #${goal.cycle} — ${cyclePercent}% (${formatDollars(goal.cycle_revenue)} / $2,500.00)`;
    embed.addFields({ name: 'Community Goal', value: `${goalLine}\n+${sessionRevenueDollars} this stream` });

    embed.setFooter({ text: `${session.created_at} → ${new Date().toLocaleTimeString('en-US')}` });

    try {
        await sendToChannel('ANALYTICS', { embeds: [embed] });
    } catch (e) {
        console.error('Failed to post stream recap:', e.message);
    }
}

// =========================================================================
// Reminder timer — DMs you after 4 hours if !offline hasn't been run
// =========================================================================

let reminderTimer = null;
const REMINDER_MS = 4 * 60 * 60 * 1000; // 4 hours

function startReminder() {
    cancelReminder();
    reminderTimer = setTimeout(async () => {
        const session = livestream.getActiveSession.get();
        if (!session) return;

        try {
            const guild = getGuild();
            if (!guild) return;
            const owner = await guild.members.fetch(guild.ownerId);
            if (owner) {
                const dm = await owner.createDM();
                await dm.send(
                    `⏰ **Reminder:** Your livestream session (#${session.id}) has been active for 4+ hours. ` +
                    `Don't forget to run \`!offline\` when you're done — shipping DMs won't go out until you do.`
                );
                console.log('Sent 4-hour livestream reminder to owner');
            }
        } catch (e) {
            console.error('Failed to send livestream reminder:', e.message);
        }
    }, REMINDER_MS);
}

function cancelReminder() {
    if (reminderTimer) {
        clearTimeout(reminderTimer);
        reminderTimer = null;
    }
}

/**
 * Track a livestream buyer (called from Stripe webhook when metadata.live === '1').
 */
function addLivestreamBuyer(discordUserId, customerEmail) {
    const session = livestream.getActiveSession.get();
    if (!session) return false;

    livestream.addBuyer.run(session.id, discordUserId, customerEmail);
    return true;
}

/**
 * Check if there's an active livestream session.
 */
function isLive() {
    return !!livestream.getActiveSession.get();
}

export { handleLive, handleOffline, addLivestreamBuyer, isLive };
