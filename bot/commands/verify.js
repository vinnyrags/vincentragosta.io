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
 */
async function handleVerify(message) {
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

module.exports = { handleVerify };
