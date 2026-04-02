/**
 * Livestream Master Commands
 *
 * !live    — Go live: close pre-order queue, start livestream session, post shop link
 * !offline — End stream: end livestream session, send shipping DMs, open next queue
 */

import { EmbedBuilder } from 'discord.js';
import config from '../config.js';
import { livestream, queues } from '../db.js';
import { sendEmbed, sendToChannel, getMember, getGuild } from '../discord.js';

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
        }
    } catch (e) {
        console.error('Could not reach WordPress to toggle livestream mode:', e.message);
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

    // Enable shipping-free mode on the WordPress side
    await toggleLivestreamMode(true);

    // Start 4-hour reminder
    startReminder();

    // Post pre-order summary if queue has entries (but keep it open)
    const activeQueue = queues.getActiveQueue.get();
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

    const shopLink = `${config.SHOP_URL}?live=1`;

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
    await message.channel.send(
        `🔴 **Live session #${sessionId} started.**\n` +
        `• Queue${activeQueue ? ` #${activeQueue.id}` : ''} stays open — orders continue adding\n` +
        `• Shop link with \`?live=1\`: ${shopLink}\n` +
        `• Livestream purchases are shipping-free — collected after \`!offline\``
    );
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

    // Split buyers: those who already paid shipping this week vs those who need it
    const needsShipping = [];
    const alreadyCovered = [];

    for (const buyer of buyers) {
        const paidThisWeek = livestream.hasShippingThisWeek.get(buyer.customer_email);
        if (paidThisWeek) {
            alreadyCovered.push(buyer);
        } else {
            needsShipping.push(buyer);
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
                `You've already paid shipping this week — tonight's items will ship with your existing order on Monday. No additional shipping needed!`
            )
            .setColor(0x2ecc71)
            .setFooter({ text: 'All orders ship Monday morning.' });

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
    const shippingUrl = `${config.SHOP_URL.replace(/\/shop$/, '')}/bot/livestream/shipping/${session.id}`;

    for (const buyer of needsShipping) {
        const isPlaceholder = buyer.customer_email.includes('@placeholder');
        const emailParam = isPlaceholder ? '' : `?email=${encodeURIComponent(buyer.customer_email)}`;
        const shippingEmbed = new EmbedBuilder()
            .setTitle('📦 Shipping for Tonight\'s Orders')
            .setDescription(
                `Thanks for being part of tonight's stream!\n\n` +
                `Click below to pay shipping ($10 flat rate) and enter your address. This covers everything you buy this week — if you come back before Monday, you won't be charged again.\n\n` +
                `📦 **[Pay Shipping & Enter Address](${shippingUrl}${emailParam})**`
            )
            .setColor(0x2ecc71)
            .setFooter({ text: '$10 flat rate — covers all items and winnings through Monday.' });

        if (buyer.discord_user_id) {
            try {
                const member = await getMember(buyer.discord_user_id);
                if (member) {
                    const dm = await member.createDM();
                    await dm.send({ embeds: [shippingEmbed] });
                    shippingsSent++;
                    continue;
                }
            } catch { /* DMs disabled — fall through */ }
        }

        // Fallback: post in announcements if we can't DM
        await sendToChannel('ANNOUNCEMENTS', {
            content: buyer.discord_user_id ? `<@${buyer.discord_user_id}>` : buyer.customer_email,
            embeds: [shippingEmbed],
        });
        shippingsSent++;
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

    // Confirm in current channel
    await message.channel.send(
        `📴 **Live session #${session.id} ended.**\n` +
        `• Queue${closedQueueId ? ` #${closedQueueId}` : ''} closed and archived to <#${config.CHANNELS.CARD_NIGHT_QUEUE}>\n` +
        `• Shipping DMs sent to ${shippingsSent} buyer(s)${alreadyCovered.length ? `, ${alreadyCovered.length} already covered this week` : ''}\n` +
        `• New pre-order queue opened (#${newQueueId})\n` +
        `• Stream-ended message posted to #announcements`
    );
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
