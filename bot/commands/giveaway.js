/**
 * Giveaway System
 *
 * !giveaway start "Prize Name" [duration] [social] [url]  — Start a giveaway
 * !giveaway status                                        — Show current giveaway
 * !giveaway close                                         — Close entries
 * !giveaway draw                                          — Random winner
 * !giveaway draw duckrace                                 — Load entries for stream
 * !giveaway cancel                                        — Cancel and notify
 *
 * Standard giveaways: button click to enter (Xipe required).
 * Social giveaways: button click shows TikTok username modal, entry requires
 * social engagement (like + comment + tag 3 on the linked post).
 */

import { EmbedBuilder, ButtonBuilder, ButtonStyle, ActionRowBuilder } from 'discord.js';
import config from '../config.js';
import { giveaways } from '../db.js';
import { sendToChannel, sendEmbed, getMember, addRole } from '../discord.js';

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

    // Update the original giveaway embed (removes button, shows CLOSED)
    await updateGiveawayEmbed(giveaway);

    // Update announcements
    await sendEmbed('ANNOUNCEMENTS', {
        title: `🔴 Giveaway Closed — ${giveaway.prize_name}`,
        description: `Entries are closed! **${entryCount}** entered. Winner will be drawn on stream.`,
        color: 0xe74c3c,
    });
}

// =========================================================================
// Embed builders
// =========================================================================

function buildGiveawayEmbed(giveaway) {
    const entryCount = giveaways.getEntryCount.get(giveaway.id).count;
    const entries = giveaways.getEntries.all(giveaway.id);
    const isOpen = giveaway.status === 'open';
    const isClosed = giveaway.status === 'closed';
    const isComplete = giveaway.status === 'complete';
    const isCancelled = giveaway.status === 'cancelled';
    const isSocial = !!giveaway.is_social;

    let statusText = '🟢 OPEN';
    let color = 0xceff00;
    if (isClosed) { statusText = '🔴 CLOSED — Winner to be drawn on stream'; color = 0xe74c3c; }
    if (isComplete) { statusText = `🏆 COMPLETE — Winner: <@${giveaway.winner_id}>`; color = 0xffd700; }
    if (isCancelled) { statusText = '❌ CANCELLED'; color = 0x95a5a6; }

    const lines = [statusText, ''];

    if (isOpen && isSocial) {
        const postLink = giveaway.social_link
            ? `[TikTok post](${giveaway.social_link})`
            : 'the TikTok post';
        lines.push(
            '**How to enter:**',
            `1. Like, comment, and tag 3 friends on ${postLink}`,
            '2. Click the **Enter Giveaway** button below',
            ''
        );
    } else if (isOpen) {
        lines.push('Click the **Enter Giveaway** button below to enter.', '');
    }

    if (isOpen && giveaway.ends_at) {
        lines.push(`⏰ **Ends:** ${formatTimeRemaining(giveaway.ends_at)}`);
    }
    lines.push(`🎟️ **Entries:** ${entryCount}`);

    if (isOpen) {
        lines.push('', '*Must be verified (Xipe role).*');
    }

    // Show entry roster
    if (entries.length > 0) {
        lines.push('');
        const roster = entries.map((e, i) => {
            const label = `<@${e.discord_user_id}>`;
            const tiktok = e.tiktok_username ? ` (@${e.tiktok_username})` : '';
            return `${i + 1}. ${label}${tiktok}`;
        }).join('\n');
        lines.push(roster);
    }

    const embed = new EmbedBuilder()
        .setTitle(`🎁 Giveaway — ${giveaway.prize_name}`)
        .setDescription(lines.join('\n'))
        .setColor(color)
        .setFooter({ text: `Giveaway #${giveaway.id}` });

    return embed;
}

function buildGiveawayButton(giveawayId) {
    const button = new ButtonBuilder()
        .setCustomId(`giveaway-enter-${giveawayId}`)
        .setLabel('Enter Giveaway')
        .setStyle(ButtonStyle.Primary)
        .setEmoji('🎁');

    return new ActionRowBuilder().addComponents(button);
}

async function updateGiveawayEmbed(giveaway) {
    if (!giveaway.channel_message_id) return;

    try {
        const channel = (await import('../discord.js')).getChannel('GIVEAWAYS');
        if (!channel) return;
        const msg = await channel.messages.fetch(giveaway.channel_message_id);
        const updated = giveaways.getById.get(giveaway.id);
        const embed = buildGiveawayEmbed(updated);

        const editPayload = { embeds: [embed] };
        if (updated.status !== 'open') {
            editPayload.components = [];
        }
        await msg.edit(editPayload);
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
            return message.reply('Usage: `!giveaway start "Prize" [24h] [social] [url]`, `!giveaway close`, `!giveaway draw [duckrace]`, `!giveaway cancel`');
    }
}

// =========================================================================
// Subcommands
// =========================================================================

async function startGiveaway(message, args) {
    const existing = giveaways.getActive.get();
    if (existing) {
        return message.reply(`A giveaway is already active: **${existing.prize_name}** (#${existing.id}). Close or cancel it first.`);
    }

    // Parse: "Prize Name" [duration] [social] [url]
    const joined = args.join(' ');
    const quoteMatch = joined.match(/^[""](.+?)[""]\s*(.*)?$/);
    if (!quoteMatch) {
        return message.reply('Usage: `!giveaway start "Prize Name" [24h] [social] [tiktok-url]`\nPrize name must be in quotes.');
    }

    const prizeName = quoteMatch[1];
    const remaining = quoteMatch[2]?.trim() || '';
    const tokens = remaining.split(/\s+/).filter(Boolean);

    let endsAt = null;
    let isSocial = false;
    let socialLink = null;

    for (const token of tokens) {
        if (token.toLowerCase() === 'social') {
            isSocial = true;
        } else if (token.startsWith('http')) {
            socialLink = token;
        } else {
            const durationMs = parseDuration(token);
            if (durationMs) {
                endsAt = toSqliteDatetime(new Date(Date.now() + durationMs));
            }
        }
    }

    // Create in DB
    const result = giveaways.create.run(prizeName, endsAt, isSocial ? 1 : 0, socialLink);
    const giveawayId = result.lastInsertRowid;
    const giveaway = giveaways.getById.get(giveawayId);

    // Post embed to #giveaways with button
    const embed = buildGiveawayEmbed(giveaway);
    const row = buildGiveawayButton(giveawayId);
    const giveawayMsg = await sendToChannel('GIVEAWAYS', { embeds: [embed], components: [row] });

    if (giveawayMsg) {
        giveaways.setMessageId.run(giveawayMsg.id, giveawayId);
    }

    // Cross-post teaser to #announcements
    const durationText = endsAt ? ` Ends in **${formatTimeRemaining(endsAt)}**.` : '';
    const socialText = isSocial ? ' Complete the social engagement and ' : ' ';
    await sendEmbed('ANNOUNCEMENTS', {
        title: `🎁 Giveaway — ${prizeName}`,
        description: `We're giving away **${prizeName}**!${durationText}\n\n${socialText}Head to <#${config.CHANNELS.GIVEAWAYS}> and click **Enter Giveaway** to enter. Must be verified.`,
        color: 0xceff00,
    });

    // Post social copy to #ops
    const socialCopy = [
        `📋 **Social Copy for Giveaway #${giveawayId}:**`,
        '```',
        `🎁 GIVEAWAY — ${prizeName}!`,
        '',
        isSocial
            ? 'How to enter:\n1. Like this post\n2. Comment + tag 3 friends\n3. Join our Discord and click Enter Giveaway'
            : 'Want a chance to win? Join our Discord and click Enter Giveaway!',
        '',
        `👉 ${DISCORD_INVITE}`,
        `${durationText ? `\n⏰ ${durationText}` : ''}`,
        '```',
    ].join('\n');
    await sendToChannel('OPS', socialCopy);

    // Schedule auto-close if duration set
    if (endsAt) {
        scheduleClose(giveaway);
    }

    const typeLabel = isSocial ? 'Social giveaway' : 'Giveaway';
    await message.channel.send(
        `🎁 **${typeLabel} #${giveawayId} started: ${prizeName}**\n` +
        `• Posted to <#${config.CHANNELS.GIVEAWAYS}> with Enter Giveaway button\n` +
        `• Teaser posted to #announcements\n` +
        `• Social copy posted to #ops` +
        (endsAt ? `\n• Auto-closes in ${formatTimeRemaining(endsAt)}` : '') +
        (socialLink ? `\n• TikTok: ${socialLink}` : '')
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
    const active = giveaways.getActive.get();
    if (active) {
        return message.reply('Giveaway is still open. Run `!giveaway close` first.');
    }

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
        const roster = entries.map((e, i) => {
            const tiktok = e.tiktok_username ? ` (@${e.tiktok_username})` : '';
            return `${i + 1}. <@${e.discord_user_id}>${tiktok}`;
        }).join('\n');
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

    giveaways.setWinner.run(winner.discord_user_id, giveaway.id);

    const member = await getMember(winner.discord_user_id);
    if (member) {
        await addRole(member, config.ROLES.AHA);
    }

    await updateGiveawayEmbed(giveaway);

    const tiktokNote = winner.tiktok_username ? ` (TikTok: @${winner.tiktok_username})` : '';

    await sendEmbed('GIVEAWAYS', {
        title: `🏆 Giveaway Winner — ${giveaway.prize_name}`,
        description: `Congratulations <@${winner.discord_user_id}>!${tiktokNote} 🎉\n\nYou've won **${giveaway.prize_name}**!`,
        color: 0xffd700,
    });

    await sendEmbed('ANNOUNCEMENTS', {
        title: `🏆 Giveaway Winner — ${giveaway.prize_name}`,
        description: `<@${winner.discord_user_id}> just won **${giveaway.prize_name}**! 🎉`,
        color: 0xffd700,
    });

    await message.channel.send(
        `🏆 **<@${winner.discord_user_id}>${tiktokNote} wins ${giveaway.prize_name}!**\n` +
        `• Aha role assigned\n` +
        `• Announced in #giveaways and #announcements`
    );
}

async function cancelGiveaway(message) {
    const giveaway = giveaways.getActive.get();
    if (!giveaway) {
        return message.reply('No active giveaway to cancel.');
    }

    cancelCloseTimer();
    giveaways.cancel.run(giveaway.id);

    await updateGiveawayEmbed(giveaway);

    await sendEmbed('GIVEAWAYS', {
        title: `❌ Giveaway Cancelled — ${giveaway.prize_name}`,
        description: 'This giveaway has been cancelled.',
        color: 0x95a5a6,
    });

    await message.channel.send(`❌ **Giveaway #${giveaway.id} cancelled.**`);
}

// =========================================================================
// Button handler — called from interactions.js
// =========================================================================

async function handleGiveawayEntry(interaction, giveawayId, tiktokUsername = null) {
    const giveaway = giveaways.getById.get(giveawayId);
    if (!giveaway || giveaway.status !== 'open') {
        return interaction.reply({ content: 'This giveaway is no longer open.', ephemeral: true });
    }

    // Verify Xipe role
    const member = await getMember(interaction.user.id);
    if (!member || !member.roles.cache.has(config.ROLES.XIPE)) {
        return interaction.reply({ content: 'You need to be verified (Xipe role) to enter giveaways. Head to #verify first!', ephemeral: true });
    }

    // Check for duplicate
    const entries = giveaways.getEntries.all(giveaway.id);
    if (entries.some((e) => e.discord_user_id === interaction.user.id)) {
        return interaction.reply({ content: 'You\'re already entered in this giveaway! Good luck! 🍀', ephemeral: true });
    }

    // Add entry
    giveaways.addEntry.run(giveaway.id, interaction.user.id, tiktokUsername || null);

    // Update embed with new entry
    await updateGiveawayEmbed(giveaway);

    const confirmText = tiktokUsername
        ? `You're in! 🎁 Entry recorded as @${tiktokUsername}. Good luck!`
        : 'You\'re in! 🎁 Good luck!';

    return interaction.reply({ content: confirmText, ephemeral: true });
}

// =========================================================================
// Startup
// =========================================================================

function initGiveaways() {
    const expired = giveaways.getExpired.all();
    for (const g of expired) {
        closeGiveaway(g.id);
    }

    const active = giveaways.getActive.get();
    if (active?.ends_at) {
        scheduleClose(active);
    }
}

export { handleGiveaway, handleGiveawayEntry, initGiveaways, updateGiveawayEmbed };
