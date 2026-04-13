/**
 * Wheel Spinner
 *
 * Commands:
 *   !spin giveaway                — Spin giveaway entries, animated draw (mods)
 *   !spin giveaway pick @user     — Pre-select giveaway winner (owner only)
 *   !spin @user1 @user2 @user3    — Ad-hoc spin with mentioned users (mods)
 *   !spin "A" "B" "C"             — Ad-hoc spin with text entries (mods)
 *   !spin pick <winner> <entries>  — Pre-select ad-hoc winner (owner only)
 *
 * Animated embed that cycles through entries, decelerates, and lands on a winner.
 * Giveaway mode handles post-draw actions (Aha role, announcements, embed updates).
 * Ad-hoc mode is fire-and-forget — no state changes.
 */

import { EmbedBuilder } from 'discord.js';
import config from '../config.js';
import { giveaways } from '../db.js';
import { client, sendEmbed, getMember, addRole } from '../discord.js';
import { updateGiveawayEmbed } from './giveaway.js';

let spinInProgress = false;

// =========================================================================
// Command router
// =========================================================================

async function handleSpin(message, args) {
    const isAdmin = message.member.roles.cache.has(config.ROLES.NANOOK)
        || message.member.roles.cache.has(config.ROLES.AKIVILI);
    const isOwner = message.member.roles.cache.has(config.ROLES.AKIVILI);

    if (!isAdmin) return message.reply('Only moderators can use the spinner.');

    const first = args[0]?.toLowerCase();

    if (first === 'giveaway') {
        const isPick = args[1]?.toLowerCase() === 'pick';
        if (isPick && !isOwner) return message.reply('Only the owner can pre-select.');
        if (isPick) {
            const pickUser = message.mentions.users.first();
            if (!pickUser) return message.reply('Usage: `!spin giveaway pick @user`');
            try { await message.delete(); } catch { /* may lack perms */ }
            return spinGiveaway(message, pickUser.id);
        }
        return spinGiveaway(message, null);
    }

    if (first === 'pick') {
        if (!isOwner) return message.reply('Only the owner can pre-select.');
        try { await message.delete(); } catch { /* may lack perms */ }
        return spinAdHoc(message, args.slice(1), true);
    }

    // Ad-hoc: !spin @user1 @user2 OR !spin "Prize1" "Prize2"
    return spinAdHoc(message, args, false);
}

// =========================================================================
// Giveaway spin
// =========================================================================

async function spinGiveaway(message, pickedWinnerId) {
    if (spinInProgress) {
        return message.channel.send('A spin is already in progress!');
    }

    const active = giveaways.getActive.get();
    if (active) {
        return message.channel.send('Giveaway is still open. Run `!giveaway close` first.');
    }

    const { db } = await import('../db.js');
    const giveaway = db.prepare(
        `SELECT * FROM giveaways WHERE status = 'closed' ORDER BY closed_at DESC LIMIT 1`
    ).get();

    if (!giveaway) {
        return message.channel.send('No closed giveaway to spin.');
    }

    const dbEntries = giveaways.getEntries.all(giveaway.id);
    if (dbEntries.length < 2) {
        return message.channel.send('Need at least 2 entries to spin!');
    }

    const entries = dbEntries.map((e) => {
        const tiktok = e.tiktok_username ? ` (@${e.tiktok_username})` : '';
        return { id: e.discord_user_id, label: `<@${e.discord_user_id}>${tiktok}` };
    });

    // Validate pick
    if (pickedWinnerId) {
        const inEntries = entries.some((e) => e.id === pickedWinnerId);
        if (!inEntries) {
            return message.channel.send(`<@${pickedWinnerId}> is not in the giveaway entries.`);
        }
        await message.channel.send(`\uD83C\uDFA1 Spin picked. Starting...`);
    }

    const winnerId = pickedWinnerId
        || entries[Math.floor(Math.random() * entries.length)].id;

    spinInProgress = true;

    try {
        const title = `Giveaway \u2014 ${giveaway.prize_name}`;
        await runSpinAnimation(message.channel, entries, winnerId, title);
        await finalizeGiveaway(giveaway, winnerId, message);
    } finally {
        spinInProgress = false;
    }
}

async function finalizeGiveaway(giveaway, winnerId, message) {
    giveaways.setWinner.run(winnerId, giveaway.id);

    const member = await getMember(winnerId);
    if (member) {
        await addRole(member, config.ROLES.AHA);
    }

    await updateGiveawayEmbed(giveaway);

    const entry = giveaways.getEntries.all(giveaway.id).find((e) => e.discord_user_id === winnerId);
    const tiktokNote = entry?.tiktok_username ? ` (TikTok: @${entry.tiktok_username})` : '';

    await sendEmbed('GIVEAWAYS', {
        title: `\uD83C\uDFC6 Giveaway Winner \u2014 ${giveaway.prize_name}`,
        description: `Congratulations <@${winnerId}>!${tiktokNote} \uD83C\uDF89\n\nYou've won **${giveaway.prize_name}**!`,
        color: 0xffd700,
    });

    await sendEmbed('ANNOUNCEMENTS', {
        title: `\uD83C\uDFC6 Giveaway Winner \u2014 ${giveaway.prize_name}`,
        description: `<@${winnerId}> just won **${giveaway.prize_name}**! \uD83C\uDF89`,
        color: 0xffd700,
    });

    await message.channel.send(
        `\uD83C\uDFC6 **<@${winnerId}>${tiktokNote} wins ${giveaway.prize_name}!**\n` +
        `\u2022 Aha role assigned\n` +
        `\u2022 Announced in #giveaways and #announcements`
    );
}

// =========================================================================
// Ad-hoc spin
// =========================================================================

async function spinAdHoc(message, args, isPick) {
    if (spinInProgress) {
        return message.channel.send('A spin is already in progress!');
    }

    const entries = parseEntries(args, message);
    if (!entries || entries.length < 2) {
        return message.channel.send(
            'Need at least 2 entries.\n' +
            'Usage: `!spin @user1 @user2 @user3` or `!spin "Prize 1" "Prize 2" "Prize 3"`'
        );
    }

    let winnerId;

    if (isPick) {
        // First entry in the parsed list is the pick target
        // For mentions: first mentioned user is the winner
        // For text: first quoted string is the winner
        const pickTarget = entries[0];
        const inEntries = entries.some((e) => e.id === pickTarget.id);
        if (!inEntries) {
            return message.channel.send(`"${pickTarget.label}" is not in the entries.`);
        }
        winnerId = pickTarget.id;
        await message.channel.send(`\uD83C\uDFA1 Spin picked. Starting...`);
    } else {
        winnerId = entries[Math.floor(Math.random() * entries.length)].id;
    }

    spinInProgress = true;

    try {
        await runSpinAnimation(message.channel, entries, winnerId, 'Wheel Spin');

        const winner = entries.find((e) => e.id === winnerId);
        await message.channel.send(`\uD83C\uDFC6 **${winner.label}** wins!`);
    } finally {
        spinInProgress = false;
    }
}

function parseEntries(args, message) {
    // If mentions exist, use those
    const mentions = message.mentions.users;
    if (mentions.size >= 2) {
        return [...mentions.values()].map((u) => ({ id: u.id, label: `<@${u.id}>` }));
    }

    // Parse quoted strings: "Prize 1" "Prize 2" "Prize 3"
    const raw = args.join(' ');
    const quoted = raw.match(/"([^"]+)"/g);
    if (quoted && quoted.length >= 2) {
        return quoted.map((q) => {
            const text = q.replace(/"/g, '');
            return { id: text, label: text };
        });
    }

    // Fallback: comma-separated
    const csv = raw.split(',').map((s) => s.trim()).filter(Boolean);
    if (csv.length >= 2) {
        return csv.map((text) => ({ id: text, label: text }));
    }

    return null;
}

// =========================================================================
// Animation engine
// =========================================================================

async function runSpinAnimation(channel, entries, winnerId, title) {
    const winnerIndex = entries.findIndex((e) => e.id === winnerId);
    const frames = generateSpinFrames(entries.length, winnerIndex);
    const totalFrames = frames.length;

    // Post initial "spinning" embed
    const initialEmbed = buildSpinEmbed(title, entries, frames[0], false);
    const spinMsg = await channel.send({ embeds: [initialEmbed] });

    const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    for (let i = 1; i < totalFrames; i++) {
        await delay(getFrameDelay(i));
        const isLast = i === totalFrames - 1;
        const embed = buildSpinEmbed(title, entries, frames[i], isLast);
        await spinMsg.edit({ embeds: [embed] });
    }
}

function generateSpinFrames(entryCount, winnerIndex) {
    const totalFrames = 10;
    const frames = [];

    // Start from a random position
    let pos = Math.floor(Math.random() * entryCount);

    for (let i = 0; i < totalFrames; i++) {
        if (i === totalFrames - 1) {
            // Final frame: land on winner
            pos = winnerIndex;
        } else {
            // Advance by decreasing amounts to simulate deceleration
            const speed = i < 4 ? 3 : i < 7 ? 2 : 1;
            pos = (pos + speed) % entryCount;
        }
        frames.push(pos);
    }

    return frames;
}

function getFrameDelay(frameIndex) {
    if (frameIndex < 4) return 800;
    if (frameIndex < 7) return 1500;
    if (frameIndex < 9) return 2200;
    return 2500;
}

function buildSpinEmbed(title, entries, highlightIndex, isFinished) {
    const lines = entries.map((entry, i) => {
        if (isFinished && i === highlightIndex) {
            return `\uD83C\uDFC6 **${entry.label}**  \u25C0 WINNER`;
        }
        if (i === highlightIndex) {
            return `\uD83D\uDC49 **${entry.label}**  \u25C0`;
        }
        return `\u2003\u2003 ${entry.label}`;
    });

    return new EmbedBuilder()
        .setTitle(`\uD83C\uDFA1 ${title}${isFinished ? ' \u2014 Winner!' : ''}`)
        .setDescription(lines.join('\n'))
        .setColor(isFinished ? 0xffd700 : 0xceff00)
        .setFooter({ text: isFinished
            ? `Winner: ${entries[highlightIndex].label}`
            : 'Spinning...' });
}

export { handleSpin };
