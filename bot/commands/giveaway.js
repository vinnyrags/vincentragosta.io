/**
 * Giveaway System
 *
 * !giveaway start "Prize Name" [duration]  — Start a giveaway (react to enter)
 * !giveaway status                         — Show current giveaway
 * !giveaway close                          — Close entries
 * !giveaway draw                           — Random winner from entries
 * !giveaway draw duckrace                  — Load entries into a duck race for stream
 * !giveaway cancel                         — Cancel and notify
 */

import { EmbedBuilder } from 'discord.js';
import config from '../config.js';
import { giveaways } from '../db.js';
import { sendToChannel, sendEmbed, getMember, addRole } from '../discord.js';

const REACTION_EMOJI = '🎁';
const DISCORD_INVITE = 'https://discord.gg/EXqX685TTq';

/**
 * Format a JS Date as SQLite datetime string (YYYY-MM-DD HH:MM:SS).
 */
function toSqliteDatetime(date) {
    return date.toISOString().replace('T', ' ').replace(/\.\d{3}Z$/, '');
}

// =========================================================================
// Duration parsing
// =========================================================================

function parseDuration(str) {
    const match = str.match(/^(\d+)\s*(h|hr|hrs|hours?|d|days?|w|weeks?)$/i);
    if (!match) return null;
    const num = parseInt(match[1], 10);
    const unit = match[2].toLowerCase();
    if (unit.startsWith('h')) return num * 60 * 60 * 1000;
    if (unit.startsWith('d')) return num * 24 * 60 * 60 * 1000;
    if (unit.startsWith('w')) return num * 7 * 24 * 60 * 60 * 1000;
    return null;
}

function formatTimeRemaining(endsAt) {
    const ms = new Date(endsAt).getTime() - Date.now();
    if (ms <= 0) return 'Ended';
    const hours = Math.floor(ms / (1000 * 60 * 60));
    const minutes = Math.floor((ms % (1000 * 60 * 60)) / (1000 * 60));
    if (hours >= 24) {
        const days = Math.floor(hours / 24);
        const remainingHours = hours % 24;
        return `${days}d ${remainingHours}h`;
    }
    return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
}

// =========================================================================
// Auto-close timer
// =========================================================================

let closeTimer = null;

function scheduleClose(giveaway) {
    cancelCloseTimer();
    if (!giveaway.ends_at) return;

    const ms = new Date(giveaway.ends_at).getTime() - Date.now();
    if (ms <= 0) {
        // Already expired — close immediately
        closeGiveaway(giveaway.id);
        return;
    }

    closeTimer = setTimeout(() => closeGiveaway(giveaway.id), ms);
}

function cancelCloseTimer() {
    if (closeTimer) {
        clearTimeout(closeTimer);
        closeTimer = null;
    }
}

async function closeGiveaway(giveawayId) {
    const giveaway = giveaways.getById.get(giveawayId);
    if (!giveaway || giveaway.status !== 'open') return;

    giveaways.close.run(giveawayId);
    const entryCount = giveaways.getEntryCount.get(giveawayId).count;

    await sendEmbed('GIVEAWAYS', {
        title: `🔴 Giveaway Closed — ${giveaway.prize_name}`,
        description: `Entries are closed! **${entryCount}** entered.\n\nWinner will be drawn on stream.`,
        color: 0xe74c3c,
    });

    // Update the original embed
    await updateGiveawayEmbed(giveaway);
}

// =========================================================================
// Embed builders
// =========================================================================

function buildGiveawayEmbed(giveaway) {
    const entryCount = giveaways.getEntryCount.get(giveaway.id).count;
    const isOpen = giveaway.status === 'open';
    const isClosed = giveaway.status === 'closed';
    const isComplete = giveaway.status === 'complete';
    const isCancelled = giveaway.status === 'cancelled';

    let statusText = '🟢 OPEN — React below to enter!';
    let color = 0xceff00;
    if (isClosed) { statusText = '🔴 CLOSED — Winner to be drawn on stream'; color = 0xe74c3c; }
    if (isComplete) { statusText = `🏆 COMPLETE — Winner: <@${giveaway.winner_id}>`; color = 0xffd700; }
    if (isCancelled) { statusText = '❌ CANCELLED'; color = 0x95a5a6; }

    const lines = [statusText, ''];
    if (isOpen && giveaway.ends_at) {
        lines.push(`⏰ **Ends:** ${formatTimeRemaining(giveaway.ends_at)}`);
    }
    lines.push(`🎟️ **Entries:** ${entryCount}`);
    if (isOpen) {
        lines.push('', `React with ${REACTION_EMOJI} to enter — must be verified (Xipe role).`);
    }

    const embed = new EmbedBuilder()
        .setTitle(`🎁 Giveaway — ${giveaway.prize_name}`)
        .setDescription(lines.join('\n'))
        .setColor(color)
        .setFooter({ text: `Giveaway #${giveaway.id}` });

    return embed;
}

async function updateGiveawayEmbed(giveaway) {
    if (!giveaway.channel_message_id) return;

    try {
        const channel = (await import('../discord.js')).getChannel('GIVEAWAYS');
        if (!channel) return;
        const msg = await channel.messages.fetch(giveaway.channel_message_id);
        const embed = buildGiveawayEmbed(giveaways.getById.get(giveaway.id));
        await msg.edit({ embeds: [embed] });
    } catch (e) {
        console.error('Failed to update giveaway embed:', e.message);
    }
}

// =========================================================================
// Command handler
// =========================================================================

async function handleGiveaway(message, args) {
    const subcommand = args[0]?.toLowerCase();

    if (subcommand === 'status') {
        return showStatus(message);
    }

    // All other subcommands require admin
    const isAdmin = message.member.roles.cache.has(config.ROLES.NANOOK)
        || message.member.roles.cache.has(config.ROLES.AKIVILI);

    if (!isAdmin) {
        return message.reply('Only moderators can manage giveaways.');
    }

    switch (subcommand) {
        case 'start':
            return startGiveaway(message, args.slice(1));
        case 'close':
            return closeGiveawayCommand(message);
        case 'draw':
            return drawWinner(message, args.slice(1));
        case 'cancel':
            return cancelGiveaway(message);
        default:
            return message.reply('Usage: `!giveaway start "Prize" [24h]`, `!giveaway status`, `!giveaway close`, `!giveaway draw [duckrace]`, `!giveaway cancel`');
    }
}

// =========================================================================
// Subcommands
// =========================================================================

async function startGiveaway(message, args) {
    // Check for existing active giveaway
    const existing = giveaways.getActive.get();
    if (existing) {
        return message.reply(`A giveaway is already active: **${existing.prize_name}** (#${existing.id}). Close or cancel it first.`);
    }

    // Parse: "Prize Name" [duration]
    const joined = args.join(' ');
    const quoteMatch = joined.match(/^[""](.+?)[""]\s*(.*)?$/);
    if (!quoteMatch) {
        return message.reply('Usage: `!giveaway start "Prize Name" [24h]`\nPrize name must be in quotes.');
    }

    const prizeName = quoteMatch[1];
    const durationStr = quoteMatch[2]?.trim();

    let endsAt = null;
    if (durationStr) {
        const durationMs = parseDuration(durationStr);
        if (!durationMs) {
            return message.reply('Invalid duration. Use formats like `24h`, `3d`, `1w`.');
        }
        endsAt = toSqliteDatetime(new Date(Date.now() + durationMs));
    }

    // Create in DB
    const result = giveaways.create.run(prizeName, endsAt);
    const giveawayId = result.lastInsertRowid;
    const giveaway = giveaways.getById.get(giveawayId);

    // Post embed to #giveaways
    const embed = buildGiveawayEmbed(giveaway);
    const giveawayMsg = await sendToChannel('GIVEAWAYS', { embeds: [embed] });

    if (giveawayMsg) {
        await giveawayMsg.react(REACTION_EMOJI);
        giveaways.setMessageId.run(giveawayMsg.id, giveawayId);
    }

    // Cross-post teaser to #announcements
    const durationText = endsAt ? ` Ends in **${formatTimeRemaining(endsAt)}**.` : '';
    await sendEmbed('ANNOUNCEMENTS', {
        title: `🎁 Giveaway — ${prizeName}`,
        description: `We're giving away **${prizeName}**!${durationText}\n\nHead to <#${config.CHANNELS.GIVEAWAYS}> and react with ${REACTION_EMOJI} to enter. Must be verified.`,
        color: 0xceff00,
    });

    // Post social copy to #ops
    const socialCopy = [
        `📋 **Social Copy for Giveaway #${giveawayId}:**`,
        '```',
        `🎁 GIVEAWAY — ${prizeName}!`,
        '',
        'Want a chance to win? Join our Discord and react to enter!',
        '',
        `👉 ${DISCORD_INVITE}`,
        `${durationText ? `\n⏰ Ends in ${formatTimeRemaining(endsAt)}` : ''}`,
        '```',
    ].join('\n');
    await sendToChannel('OPS', socialCopy);

    // Schedule auto-close if duration set
    if (endsAt) {
        scheduleClose(giveaway);
    }

    // Confirm
    await message.channel.send(
        `🎁 **Giveaway #${giveawayId} started: ${prizeName}**\n` +
        `• Posted to <#${config.CHANNELS.GIVEAWAYS}> — react with ${REACTION_EMOJI} to enter\n` +
        `• Teaser posted to #announcements\n` +
        `• Social copy posted to #ops` +
        (endsAt ? `\n• Auto-closes in ${formatTimeRemaining(endsAt)}` : '')
    );
}

async function showStatus(message) {
    const giveaway = giveaways.getActive.get();
    if (!giveaway) {
        return message.reply('No active giveaway.');
    }

    const embed = buildGiveawayEmbed(giveaway);
    await message.channel.send({ embeds: [embed] });
}

async function closeGiveawayCommand(message) {
    const giveaway = giveaways.getActive.get();
    if (!giveaway) {
        return message.reply('No active giveaway to close.');
    }

    cancelCloseTimer();
    await closeGiveaway(giveaway.id);

    const entryCount = giveaways.getEntryCount.get(giveaway.id).count;
    await message.channel.send(`🔴 **Giveaway #${giveaway.id} closed.** ${entryCount} entries. Use \`!giveaway draw\` or \`!giveaway draw duckrace\` to pick a winner.`);
}

async function drawWinner(message, args) {
    // Find most recent closed giveaway
    const active = giveaways.getActive.get();
    if (active) {
        return message.reply('Giveaway is still open. Run `!giveaway close` first.');
    }

    // Get the most recent closed (not complete/cancelled) giveaway
    const giveaway = (await import('../db.js')).db.prepare(
        `SELECT * FROM giveaways WHERE status = 'closed' ORDER BY closed_at DESC LIMIT 1`
    ).get();

    if (!giveaway) {
        return message.reply('No closed giveaway to draw from.');
    }

    const entries = giveaways.getEntries.all(giveaway.id);
    if (entries.length === 0) {
        return message.reply('No entries in this giveaway. Nothing to draw.');
    }

    const mode = args[0]?.toLowerCase();

    if (mode === 'duckrace') {
        // Output roster for duck race format
        const roster = entries.map((e, i) => `${i + 1}. <@${e.discord_user_id}>`).join('\n');
        const embed = new EmbedBuilder()
            .setTitle(`🦆 Duck Race Roster — ${giveaway.prize_name}`)
            .setDescription(`${entries.length} entrants loaded from Giveaway #${giveaway.id}:\n\n${roster}`)
            .setColor(0xceff00)
            .setFooter({ text: 'Use !giveaway draw to pick randomly, or run the duck race on stream!' });

        await message.channel.send({ embeds: [embed] });
        return;
    }

    // Random draw
    const winner = entries[Math.floor(Math.random() * entries.length)];

    // Update DB
    giveaways.setWinner.run(winner.discord_user_id, giveaway.id);

    // Assign Aha role
    const member = await getMember(winner.discord_user_id);
    if (member) {
        await addRole(member, config.ROLES.AHA);
    }

    // Update original embed
    await updateGiveawayEmbed(giveaway);

    // Announce in #giveaways
    await sendEmbed('GIVEAWAYS', {
        title: `🏆 Giveaway Winner — ${giveaway.prize_name}`,
        description: `Congratulations <@${winner.discord_user_id}>! 🎉\n\nYou've won **${giveaway.prize_name}**!`,
        color: 0xffd700,
    });

    // Cross-post to #announcements
    await sendEmbed('ANNOUNCEMENTS', {
        title: `🏆 Giveaway Winner — ${giveaway.prize_name}`,
        description: `<@${winner.discord_user_id}> just won **${giveaway.prize_name}**! 🎉`,
        color: 0xffd700,
    });

    // Cross-post to #and-in-the-back
    await sendEmbed('AND_IN_THE_BACK', {
        title: `🏆 Giveaway Winner — ${giveaway.prize_name}`,
        description: `<@${winner.discord_user_id}> takes it! 🎁`,
        color: 0xffd700,
    });

    // Confirm in channel
    await message.channel.send(
        `🏆 **<@${winner.discord_user_id}> wins ${giveaway.prize_name}!**\n` +
        `• Aha role assigned\n` +
        `• Announced in #giveaways, #announcements, and #and-in-the-back`
    );
}

async function cancelGiveaway(message) {
    const giveaway = giveaways.getActive.get();
    if (!giveaway) {
        return message.reply('No active giveaway to cancel.');
    }

    cancelCloseTimer();
    giveaways.cancel.run(giveaway.id);

    // Update original embed
    await updateGiveawayEmbed(giveaway);

    await sendEmbed('GIVEAWAYS', {
        title: `❌ Giveaway Cancelled — ${giveaway.prize_name}`,
        description: 'This giveaway has been cancelled.',
        color: 0x95a5a6,
    });

    await message.channel.send(`❌ **Giveaway #${giveaway.id} cancelled.**`);
}

// =========================================================================
// Reaction handler — called from index.js on messageReactionAdd
// =========================================================================

async function handleGiveawayReaction(reaction, user) {
    // Only process the giveaway emoji
    if (reaction.emoji.name !== REACTION_EMOJI) return;

    // Only process in #giveaways channel
    if (reaction.message.channelId !== config.CHANNELS.GIVEAWAYS) return;

    // Look up active giveaway by message ID
    const giveaway = giveaways.getByMessageId.get(reaction.message.id);
    if (!giveaway || giveaway.status !== 'open') return;

    // Verify Xipe role (verified member)
    const member = await getMember(user.id);
    if (!member) return;

    if (!member.roles.cache.has(config.ROLES.XIPE)) {
        try {
            const dm = await user.createDM();
            await dm.send(`You need to be verified (Xipe role) to enter giveaways. Head to #verify first!`);
        } catch { /* DMs disabled */ }
        return;
    }

    // Add entry
    const result = giveaways.addEntry.run(giveaway.id, user.id);
    if (result.changes > 0) {
        // New entry — update embed with new count
        await updateGiveawayEmbed(giveaway);
    }
}

// =========================================================================
// Startup — check for expired giveaways and schedule active timers
// =========================================================================

function initGiveaways() {
    // Close any expired giveaways
    const expired = giveaways.getExpired.all();
    for (const g of expired) {
        closeGiveaway(g.id);
    }

    // Schedule timer for active giveaway with ends_at
    const active = giveaways.getActive.get();
    if (active?.ends_at) {
        scheduleClose(active);
    }
}

export { handleGiveaway, handleGiveawayReaction, initGiveaways };
