/**
 * Age Verification — !verify command.
 *
 * Flow:
 * 1. User types !verify in any channel
 * 2. Bot DMs user with confirmation prompt
 * 3. User reacts ✅ in DM
 * 4. Bot assigns Ena role
 */

const { EmbedBuilder } = require('discord.js');
const config = require('../config');
const { getMember, hasRole, addRole } = require('../discord');

/**
 * Handle !verify command.
 *
 * !verify — start age verification for yourself
 * !verify @user — check a user's role status (mods only)
 */
async function handleVerify(message) {
    // Check mode: !verify @user
    const mentioned = message.mentions.users.first();
    if (mentioned) {
        return handleVerifyCheck(message, mentioned);
    }

    const member = message.member;

    if (hasRole(member, config.ROLES.ENA)) {
        return message.reply('You already have access to After Dark channels.');
    }

    try {
        const dm = await message.author.createDM();

        const embed = new EmbedBuilder()
            .setTitle('🔞 Age Verification — itzenzoTTV')
            .setDescription(
                'The **After Dark** channels contain mature content including Goddess Story cards, mature playmats, and adult discussion.\n\n' +
                'By reacting ✅ below, you confirm that you are **18 years of age or older**.\n\n' +
                'This action grants you the **Ena** role, unlocking the After Dark category.'
            )
            .setColor(0xc0392b)
            .setFooter({ text: 'This verification is permanent. Contact a mod to remove it.' });

        const dmMsg = await dm.send({ embeds: [embed] });
        await dmMsg.react('✅');

        // Delete the command message to keep channels clean
        try { await message.delete(); } catch { /* may not have perms */ }

        // Wait for reaction in DM (5 minute timeout)
        const filter = (reaction, user) => reaction.emoji.name === '✅' && user.id === message.author.id;

        try {
            await dmMsg.awaitReactions({ filter, max: 1, time: 300_000, errors: ['time'] });

            // Assign Ena role
            const freshMember = await getMember(message.author.id);
            if (freshMember) {
                await addRole(freshMember, config.ROLES.ENA);
                await dm.send('✅ Verified! You now have access to the **After Dark** channels.');
            }
        } catch {
            await dm.send('⏰ Verification timed out. Run `!verify` again when you\'re ready.');
        }
    } catch {
        // User may have DMs disabled
        await message.reply('I couldn\'t send you a DM. Please enable DMs from server members and try again.');
    }
}

/**
 * Check a user's role status — !verify @user
 */
async function handleVerifyCheck(message, user) {
    const member = await getMember(user.id);
    if (!member) {
        return message.reply(`Could not find <@${user.id}> in the server.`);
    }

    const roleChecks = [
        { id: config.ROLES.AKIVILI, name: 'Akivili', emoji: '👑' },
        { id: config.ROLES.NANOOK, name: 'Nanook', emoji: '🔴' },
        { id: config.ROLES.NOUS, name: 'Nous', emoji: '🔵' },
        { id: config.ROLES.AHA, name: 'Aha', emoji: '🩷' },
        { id: config.ROLES.XIPE, name: 'Xipe', emoji: '🟢' },
        { id: config.ROLES.YAOSHI, name: 'Yaoshi', emoji: '🟣' },
        { id: config.ROLES.IX, name: 'IX', emoji: '⬛' },
        { id: config.ROLES.ENA, name: 'Ena', emoji: '🔞' },
    ];

    const lines = roleChecks.map((r) => {
        const has = hasRole(member, r.id);
        return `${has ? '✅' : '❌'} ${r.emoji} **${r.name}**`;
    });

    const embed = new EmbedBuilder()
        .setTitle(`Role Status — ${member.displayName}`)
        .setDescription(lines.join('\n'))
        .setColor(0x3498db)
        .setThumbnail(member.user.displayAvatarURL());

    await message.channel.send({ embeds: [embed] });
}

module.exports = { handleVerify };
