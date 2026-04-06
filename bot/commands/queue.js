/**
 * Queue & Duck Race System
 *
 * Commands:
 *   !queue              — Show current queue
 *   !queue open         — Open a new queue (mods)
 *   !queue close        — Close the queue and archive to #card-night-queue (mods)
 *   !queue history      — Show recent queues with winners
 *   !duckrace           — Show duck race roster (unique buyers from queue)
 *   !duckrace winner @u — Declare duck race winner, assign Aha role (mods)
 *
 * Queue entries are auto-added when card products are purchased via Stripe.
 * Each buyer gets exactly one duck race entry regardless of how many items.
 */

import { EmbedBuilder } from 'discord.js';
import config from '../config.js';
import { queues } from '../db.js';
import { sendEmbed, getMember, addRole } from '../discord.js';

// =========================================================================
// Queue commands
// =========================================================================

async function handleQueue(message, args) {
    const subcommand = args[0]?.toLowerCase();

    const isAdmin = message.member.roles.cache.has(config.ROLES.NANOOK)
        || message.member.roles.cache.has(config.ROLES.AKIVILI);

    switch (subcommand) {
        case 'open':
            if (!isAdmin) return message.reply('Only moderators can open queues.');
            return openQueue(message);
        case 'close':
            if (!isAdmin) return message.reply('Only moderators can close queues.');
            return closeQueue(message);
        case 'history':
            return queueHistory(message);
        default:
            return showQueue(message);
    }
}

async function openQueue(message) {
    const active = queues.getActiveQueue.get();
    if (active) {
        return message.reply(`There's already an open queue (Queue #${active.id}). Close it first with \`!queue close\`.`);
    }

    const result = queues.createQueue.run();
    const queueId = result.lastInsertRowid;

    const embed = new EmbedBuilder()
        .setTitle('📋 Queue Open!')
        .setDescription('Pre-orders are now being accepted. Every card product purchase is automatically added to the queue.\n\nEvery unique buyer gets one entry into tonight\'s duck race.')
        .setColor(0xceff00)
        .setFooter({ text: `Queue #${queueId}` });

    await message.channel.send({ embeds: [embed] });
}

async function closeQueue(message) {
    const active = queues.getActiveQueue.get();
    if (!active) {
        return message.reply('No open queue to close.');
    }

    queues.closeQueue.run(active.id);

    const entries = queues.getEntries.all(active.id);
    const uniqueBuyers = queues.getUniqueBuyers.all(active.id);
    const embed = buildQueueEmbed(active, entries, uniqueBuyers, 'closed');

    // Post in current channel
    await message.channel.send({ embeds: [embed] });

    // Archive to #card-night-queue
    const archiveEmbed = new EmbedBuilder()
        .setTitle(`📋 Queue #${active.id} — Archived`)
        .setDescription(buildQueueDescription(entries, uniqueBuyers))
        .setColor(0x95a5a6)
        .setFooter({ text: `Opened: ${active.created_at} • Closed: ${new Date().toISOString().replace('T', ' ').slice(0, 19)}` });

    await sendEmbed('CARD_NIGHT_QUEUE', {
        title: `📋 Queue #${active.id} — ${entries.length} items from ${uniqueBuyers.length} buyers`,
        description: buildQueueDescription(entries, uniqueBuyers),
        color: 0x95a5a6,
        footer: `Opened: ${active.created_at}`,
    });

    await message.channel.send(`Queue #${active.id} closed and archived to <#${config.CHANNELS.CARD_NIGHT_QUEUE}>. Run \`!duckrace\` to see the race roster.`);
}

async function showQueue(message) {
    const active = queues.getActiveQueue.get();
    if (!active) {
        return message.reply('No open queue right now. A mod can start one with `!queue open`.');
    }

    const entries = queues.getEntries.all(active.id);
    const uniqueBuyers = queues.getUniqueBuyers.all(active.id);
    const embed = buildQueueEmbed(active, entries, uniqueBuyers, 'open');

    await message.channel.send({ embeds: [embed] });
}

async function queueHistory(message) {
    const recent = queues.getRecentQueues.all(5);

    if (!recent.length) {
        return message.reply('No queue history yet.');
    }

    const lines = recent.map((q) => {
        const entries = queues.getEntries.all(q.id);
        const buyers = queues.getUniqueBuyers.all(q.id);
        const winner = q.duck_race_winner_id ? `<@${q.duck_race_winner_id}>` : 'No winner';
        return `**Queue #${q.id}** — ${entries.length} items, ${buyers.length} buyers • Duck race: ${winner} • ${q.created_at.slice(0, 10)}`;
    });

    const embed = new EmbedBuilder()
        .setTitle('📋 Recent Queues')
        .setDescription(lines.join('\n'))
        .setColor(0x3498db);

    await message.channel.send({ embeds: [embed] });
}

// =========================================================================
// Duck race commands
// =========================================================================

async function handleDuckRace(message, args) {
    const subcommand = args[0]?.toLowerCase();

    if (subcommand === 'winner') {
        const isAdmin = message.member.roles.cache.has(config.ROLES.NANOOK)
            || message.member.roles.cache.has(config.ROLES.AKIVILI);
        if (!isAdmin) return message.reply('Only moderators can declare duck race winners.');
        return declareDuckRaceWinner(message, args.slice(1));
    }

    return showDuckRace(message);
}

async function showDuckRace(message) {
    // Find the most recent open or closed queue (not yet complete)
    const active = queues.getActiveQueue.get();
    const queue = active || (() => {
        const recent = queues.getRecentQueues.all(1);
        return recent.length && !recent[0].duck_race_winner_id ? recent[0] : null;
    })();

    if (!queue) {
        return message.reply('No active queue with a duck race roster.');
    }

    const uniqueBuyers = queues.getUniqueBuyers.all(queue.id);

    if (!uniqueBuyers.length) {
        return message.reply('No entries in the duck race yet — queue has no purchases.');
    }

    const roster = uniqueBuyers.map((b, i) => {
        const label = /^\d+$/.test(b.buyer) ? `<@${b.buyer}>` : b.buyer;
        return `${i + 1}. ${label}`;
    }).join('\n');

    const embed = new EmbedBuilder()
        .setTitle(`🦆 Duck Race — Queue #${queue.id}`)
        .setDescription(`**${uniqueBuyers.length} entries** (1 per buyer)\n\n${roster}`)
        .setColor(0xffd700)
        .setFooter({ text: 'Each buyer gets exactly one entry regardless of items purchased' });

    await message.channel.send({ embeds: [embed] });
}

async function declareDuckRaceWinner(message, args) {
    const mentioned = message.mentions.users.first();
    if (!mentioned) {
        return message.reply('Usage: `!duckrace winner @user`');
    }

    // Find the most recent closed queue without a winner
    const recent = queues.getRecentQueues.all(1);
    const queue = recent.length && !recent[0].duck_race_winner_id ? recent[0] : null;

    // Also check the active queue
    const active = queues.getActiveQueue.get();

    const target = queue || active;
    if (!target) {
        return message.reply('No queue found to assign a duck race winner to.');
    }

    // Verify winner is actually in the roster
    const uniqueBuyers = queues.getUniqueBuyers.all(target.id);
    const isInRoster = uniqueBuyers.some((b) => b.buyer === mentioned.id);
    if (!isInRoster) {
        return message.reply(`<@${mentioned.id}> is not in the duck race roster for Queue #${target.id}.`);
    }

    queues.setDuckRaceWinner.run(mentioned.id, target.id);

    // Assign Aha role
    const member = await getMember(mentioned.id);
    if (member) {
        await addRole(member, config.ROLES.AHA);
    }

    const entries = queues.getEntries.all(target.id);

    // Post in current channel
    const embed = new EmbedBuilder()
        .setTitle('🦆 Duck Race Winner!')
        .setDescription(`<@${mentioned.id}> wins the duck race for Queue #${target.id}!\n\n${uniqueBuyers.length} entries from ${entries.length} items purchased.`)
        .setColor(0xffd700);

    await message.channel.send({ embeds: [embed] });

    // Cross-post to announcements
    await sendEmbed('ANNOUNCEMENTS', {
        title: '🦆 Duck Race Winner!',
        description: `<@${mentioned.id}> wins tonight's duck race! Congrats!`,
        color: 0xffd700,
    });

    // Cross-post to #and-in-the-back (community hype)
    await sendEmbed('AND_IN_THE_BACK', {
        title: `🦆 Queue #${target.id} — Duck Race Winner`,
        description: `Winner: <@${mentioned.id}>\nEntries: ${uniqueBuyers.length} buyers, ${entries.length} items`,
        color: 0xffd700,
    });
}

// =========================================================================
// Helpers
// =========================================================================

function buildQueueDescription(entries, uniqueBuyers) {
    if (!entries.length) return 'No entries yet.';

    const lines = entries.map((entry, i) => {
        const key = entry.discord_user_id || entry.customer_email || 'Unknown';
        const label = key === 'Unknown' ? key : /^\d+$/.test(key) ? `<@${key}>` : key;
        const product = `${entry.product_name}${entry.quantity > 1 ? ` ×${entry.quantity}` : ''}`;
        return `${i + 1}. ${label} — ${product}`;
    });

    return lines.join('\n') + `\n\n🦆 **Duck race entries:** ${uniqueBuyers.length}`;
}

function buildQueueEmbed(queue, entries, uniqueBuyers, status) {
    const statusText = status === 'open'
        ? '🟢 OPEN — Purchases are automatically added'
        : '🔴 CLOSED';

    const embed = new EmbedBuilder()
        .setTitle(`📋 Queue #${queue.id}`)
        .setDescription(`${statusText}\n\n${buildQueueDescription(entries, uniqueBuyers)}`)
        .setColor(status === 'open' ? 0xceff00 : 0xe74c3c)
        .addFields(
            { name: 'Items', value: String(entries.length), inline: true },
            { name: 'Buyers', value: String(uniqueBuyers.length), inline: true },
            { name: 'Duck Race Entries', value: String(uniqueBuyers.length), inline: true },
        )
        .setFooter({ text: `Queue #${queue.id} • Opened ${queue.created_at}` });

    return embed;
}

/**
 * Add a purchase to the active queue (called from Stripe webhook).
 * Returns true if added, false if no active queue.
 */
function addToQueue(discordUserId, customerEmail, productName, quantity, stripeSessionId) {
    const active = queues.getActiveQueue.get();
    if (!active) return false;

    queues.addEntry.run(active.id, discordUserId, customerEmail, productName, quantity, stripeSessionId);
    return true;
}

export {
    handleQueue,
    handleDuckRace,
    addToQueue,
};
