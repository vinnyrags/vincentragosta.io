/**
 * Pack Battle System
 *
 * Commands:
 *   !battle start <product-slug> [max-entries] — Start a new pack battle
 *   !battle close — Close entries, show final roster
 *   !battle cancel — Cancel the active battle
 *   !battle winner @user — Declare winner, assign Aha role
 *   !battle status — Show current battle status
 *
 * Viewers join by reacting to the battle message.
 * Payment is verified via Stripe webhook.
 */

const { EmbedBuilder } = require('discord.js');
const config = require('../config');
const { battles } = require('../db');
const { sendToChannel, sendEmbed, getMember, addRole } = require('../discord');

/**
 * Build the battle status embed.
 */
function buildBattleEmbed(battle, entries, paidEntries) {
    const totalEntries = entries.length;
    const paidCount = paidEntries.length;
    const statusText = battle.status === 'open'
        ? '🟢 OPEN — React ✅ to join!'
        : battle.status === 'closed'
            ? '🔴 CLOSED — No more entries'
            : battle.status === 'complete'
                ? '🏆 COMPLETE'
                : '❌ CANCELLED';

    const embed = new EmbedBuilder()
        .setTitle(`⚔️ Pack Battle — ${battle.product_name}`)
        .setDescription(statusText)
        .setColor(battle.status === 'open' ? 0x2ecc71 : battle.status === 'complete' ? 0xffd700 : 0xe74c3c)
        .addFields(
            { name: 'Entries', value: `${totalEntries}/${battle.max_entries}`, inline: true },
            { name: 'Confirmed Payments', value: `${paidCount}/${totalEntries}`, inline: true },
        );

    if (paidEntries.length > 0) {
        const roster = paidEntries.map((e, i) => `${i + 1}. <@${e.discord_user_id}> ✅`).join('\n');
        embed.addFields({ name: 'Roster', value: roster });
    }

    if (battle.winner_id) {
        embed.addFields({ name: '🏆 Winner', value: `<@${battle.winner_id}>` });
    }

    return embed;
}

/**
 * Handle !battle commands.
 */
async function handleBattle(message, args) {
    const subcommand = args[0]?.toLowerCase();

    // Only allow in #pack-battles
    if (message.channel.id !== config.CHANNELS.PACK_BATTLES) {
        return message.reply('Pack battle commands only work in <#' + config.CHANNELS.PACK_BATTLES + '>.');
    }

    // Only mods/owner can manage battles
    const isAdmin = message.member.roles.cache.has(config.ROLES.NANOOK)
        || message.member.roles.cache.has(config.ROLES.AKIVILI);

    if (!isAdmin && subcommand !== 'status') {
        return message.reply('Only moderators can manage pack battles.');
    }

    switch (subcommand) {
        case 'start':
            return startBattle(message, args.slice(1));
        case 'close':
            return closeBattle(message);
        case 'cancel':
            return cancelBattle(message);
        case 'winner':
            return declareBattleWinner(message, args.slice(1));
        case 'status':
            return battleStatus(message);
        default:
            return message.reply(
                'Usage: `!battle start <product-name> [max-entries]`, `!battle close`, `!battle cancel`, `!battle winner @user`, `!battle status`'
            );
    }
}

async function startBattle(message, args) {
    const active = battles.getActiveBattle.get();
    if (active) {
        return message.reply('There\'s already an active battle. Close or cancel it first.');
    }

    if (!args.length) {
        return message.reply('Usage: `!battle start <product-name> [max-entries]`');
    }

    const maxEntries = parseInt(args[args.length - 1], 10);
    const hasMaxArg = !isNaN(maxEntries) && maxEntries > 0;
    const productName = hasMaxArg ? args.slice(0, -1).join(' ') : args.join(' ');
    const productSlug = productName.toLowerCase().replace(/\s+/g, '-');
    const max = hasMaxArg ? Math.min(maxEntries, 50) : 20;

    const result = battles.createBattle.run(productSlug, productName, max, null);
    const battleId = result.lastInsertRowid;

    const embed = new EmbedBuilder()
        .setTitle(`⚔️ Pack Battle — ${productName}`)
        .setDescription('🟢 OPEN — React ✅ to join!')
        .setColor(0x2ecc71)
        .addFields(
            { name: 'Entries', value: `0/${max}`, inline: true },
            { name: 'Confirmed Payments', value: '0/0', inline: true },
        )
        .setFooter({ text: `Battle #${battleId} • Purchase your pack from the shop to confirm your entry` });

    const msg = await message.channel.send({ embeds: [embed] });
    await msg.react('✅');

    battles.setBattleMessage.run(msg.id, battleId);

    // Also announce in #announcements
    await sendEmbed('ANNOUNCEMENTS', {
        title: '⚔️ Pack Battle Starting!',
        description: `**${productName}** — Head to <#${config.CHANNELS.PACK_BATTLES}> and react to join!\n\nMax entries: ${max}`,
        color: 0x2ecc71,
    });
}

async function closeBattle(message) {
    const battle = battles.getActiveBattle.get();
    if (!battle) {
        return message.reply('No active battle to close.');
    }

    battles.closeBattle.run(battle.id);
    const entries = battles.getEntries.all(battle.id);
    const paidEntries = battles.getPaidEntries.all(battle.id);

    const embed = buildBattleEmbed({ ...battle, status: 'closed' }, entries, paidEntries);
    embed.setFooter({ text: `Battle #${battle.id} • Entries closed • ${paidEntries.length} confirmed` });

    await message.channel.send({ embeds: [embed] });

    if (paidEntries.length < entries.length) {
        const unpaid = entries.filter((e) => !e.paid);
        const unpaidList = unpaid.map((e) => `<@${e.discord_user_id}>`).join(', ');
        await message.channel.send(`⚠️ Unpaid entries: ${unpaidList} — these will not be included.`);
    }
}

async function cancelBattle(message) {
    const battle = battles.getActiveBattle.get();
    if (!battle) {
        return message.reply('No active battle to cancel.');
    }

    battles.cancelBattle.run(battle.id);
    const entries = battles.getEntries.all(battle.id);

    await message.channel.send(`❌ Pack battle **${battle.product_name}** has been cancelled.`);

    if (entries.length > 0) {
        const entrants = entries.map((e) => `<@${e.discord_user_id}>`).join(', ');
        await message.channel.send(`Notifying entrants: ${entrants} — battle cancelled, refunds if applicable.`);
    }
}

async function declareBattleWinner(message, args) {
    // Find the most recent closed battle
    const battle = battles.getBattleById.get(
        // Get the most recent non-open battle
        (() => {
            const row = require('../db').db.prepare(
                "SELECT id FROM battles WHERE status = 'closed' ORDER BY created_at DESC LIMIT 1"
            ).get();
            return row?.id;
        })()
    );

    if (!battle) {
        return message.reply('No closed battle found. Close the battle first with `!battle close`.');
    }

    const mentioned = message.mentions.users.first();
    if (!mentioned) {
        return message.reply('Usage: `!battle winner @user`');
    }

    battles.setBattleWinner.run(mentioned.id, battle.id);

    // Assign Aha role
    const member = await getMember(mentioned.id);
    if (member) {
        await addRole(member, config.ROLES.AHA);
    }

    const entries = battles.getEntries.all(battle.id);
    const paidEntries = battles.getPaidEntries.all(battle.id);
    const embed = buildBattleEmbed({ ...battle, status: 'complete', winner_id: mentioned.id }, entries, paidEntries);

    // Post in pack-battles
    await message.channel.send({ embeds: [embed] });

    // Cross-post to announcements
    await sendEmbed('ANNOUNCEMENTS', {
        title: '🏆 Pack Battle Winner!',
        description: `<@${mentioned.id}> won the **${battle.product_name}** pack battle and takes home ALL the cards!`,
        color: 0xffd700,
    });

    // Cross-post to pack-openings
    await sendEmbed('PACK_OPENINGS', {
        title: '⚔️ Pack Battle Results',
        description: `**${battle.product_name}** — ${paidEntries.length} entries\n🏆 Winner: <@${mentioned.id}>`,
        color: 0xffd700,
    });
}

async function battleStatus(message) {
    const battle = battles.getActiveBattle.get();
    if (!battle) {
        return message.reply('No active battle right now.');
    }

    const entries = battles.getEntries.all(battle.id);
    const paidEntries = battles.getPaidEntries.all(battle.id);
    const embed = buildBattleEmbed(battle, entries, paidEntries);

    await message.channel.send({ embeds: [embed] });
}

/**
 * Handle reaction-based joins on battle messages.
 */
async function handleBattleReaction(reaction, user) {
    if (user.bot) return;
    if (reaction.emoji.name !== '✅') return;

    const battle = battles.getActiveBattle.get();
    if (!battle || battle.channel_message_id !== reaction.message.id) return;

    const entryCount = battles.getEntryCount.get(battle.id).count;
    if (entryCount >= battle.max_entries) {
        await reaction.users.remove(user.id);
        const dm = await user.createDM();
        await dm.send(`Sorry, the pack battle for **${battle.product_name}** is full (${battle.max_entries} max).`);
        return;
    }

    battles.addEntry.run(battle.id, user.id);

    const newCount = battles.getEntryCount.get(battle.id).count;
    const paidCount = battles.getPaidEntryCount.get(battle.id).count;

    // Update the battle message embed
    const channel = reaction.message.channel;
    const embed = buildBattleEmbed(battle, battles.getEntries.all(battle.id), battles.getPaidEntries.all(battle.id));
    embed.setFooter({ text: `Battle #${battle.id} • Purchase your pack from the shop to confirm your entry` });

    try {
        const msg = await channel.messages.fetch(battle.channel_message_id);
        await msg.edit({ embeds: [embed] });
    } catch { /* message may not be editable */ }
}

module.exports = { handleBattle, handleBattleReaction };
