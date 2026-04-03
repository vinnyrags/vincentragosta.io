/**
 * Community Goals — tracks revenue toward the next restock.
 *
 * Every $2,500 in product revenue (shipping excluded) triggers a restock cycle.
 * Lifetime milestones reward the community at $5K, $10K, $25K, $50K.
 *
 * The pinned message in #community-goals is updated on every purchase.
 */

import { EmbedBuilder } from 'discord.js';
import { client } from './discord.js';
import config from './config.js';
import { goals } from './db.js';

const CYCLE_GOAL = 250000; // $2,500 in cents

const LIFETIME_MILESTONES = [
    { amount: 500000,   label: '$5,000',  reward: 'Community shoutout on stream!' },
    { amount: 1000000,  label: '$10,000', reward: 'Opening a box on stream for the community!' },
    { amount: 2500000,  label: '$25,000', reward: 'Community celebration stream!' },
    { amount: 5000000,  label: '$50,000', reward: 'Something big is coming...' },
    { amount: 10000000, label: '$100,000', reward: 'Legend status. The community built this.' },
];

/**
 * Build a text progress bar.
 * @param {number} percent 0-100
 * @param {number} length bar character count
 */
function progressBar(percent, length = 20) {
    const filled = Math.round((percent / 100) * length);
    return '█'.repeat(filled) + '░'.repeat(length - filled);
}

/**
 * Build the community goals embed.
 */
function buildGoalEmbed(goal) {
    const cycleRevenue = goal.cycle_revenue;
    const lifetimeRevenue = goal.lifetime_revenue;
    const cycle = goal.cycle;

    const percent = Math.min(Math.round((cycleRevenue / CYCLE_GOAL) * 100), 100);
    const cycleDollars = (cycleRevenue / 100).toFixed(2);
    const goalDollars = (CYCLE_GOAL / 100).toFixed(2);
    const lifetimeDollars = (lifetimeRevenue / 100).toFixed(2);
    const restocksCompleted = cycle - 1;

    const bar = progressBar(percent);

    const description = [
        `${bar} **${percent}%**`,
        `**$${cycleDollars}** / $${goalDollars}`,
        '',
        `Every $${goalDollars} in sales funds the next restock.`,
        'When we hit the goal, new product drops for everyone.',
    ].join('\n');

    const embed = new EmbedBuilder()
        .setTitle(`📊 Community Restock Goal — Cycle #${cycle}`)
        .setDescription(description)
        .setColor(percent >= 100 ? 0x2ecc71 : 0x3498db);

    // Lifetime stats
    const lifetimeLines = [`💰 **$${lifetimeDollars}** lifetime revenue`];
    if (restocksCompleted > 0) {
        lifetimeLines.push(`📦 **${restocksCompleted}** restock${restocksCompleted !== 1 ? 's' : ''} funded by this community`);
    }

    // Show next lifetime milestone
    const nextMilestone = LIFETIME_MILESTONES.find((m) => lifetimeRevenue < m.amount);
    if (nextMilestone) {
        const msPercent = Math.round((lifetimeRevenue / nextMilestone.amount) * 100);
        lifetimeLines.push(`🎯 Next milestone: **${nextMilestone.label}** (${msPercent}%) — *${nextMilestone.reward}*`);
    }

    embed.addFields({ name: 'Lifetime', value: lifetimeLines.join('\n') });
    embed.setFooter({ text: 'Updated live with every purchase' });

    return embed;
}

/**
 * Add revenue from a purchase and update the pinned message.
 * @param {number} amountCents Revenue in cents (shipping excluded)
 */
async function addRevenue(amountCents) {
    if (amountCents <= 0) return;

    // Check lifetime before update (for milestone detection)
    const before = goals.get.get();
    const lifetimeBefore = before.lifetime_revenue;

    // Update revenue
    goals.addRevenue.run(amountCents, amountCents);

    // Check if cycle goal was hit (possibly multiple times)
    let goal = goals.get.get();
    while (goal.cycle_revenue >= CYCLE_GOAL) {
        goals.resetCycle.run(CYCLE_GOAL);
        goal = goals.get.get();

        // Announce cycle completion
        await announceRestock(goal.cycle - 1);
    }

    // Check lifetime milestones
    const lifetimeAfter = goal.lifetime_revenue;
    for (const milestone of LIFETIME_MILESTONES) {
        if (lifetimeBefore < milestone.amount && lifetimeAfter >= milestone.amount) {
            await announceMilestone(milestone);
        }
    }

    // Update pinned message
    await updatePinnedMessage(goal);
}

/**
 * Update (or create) the pinned message in #community-goals.
 */
async function updatePinnedMessage(goal) {
    const channel = client.channels.cache.get(config.CHANNELS.COMMUNITY_GOALS);
    if (!channel) return;

    const embed = buildGoalEmbed(goal || goals.get.get());

    try {
        if (goal?.channel_message_id) {
            // Try to edit existing message
            const msg = await channel.messages.fetch(goal.channel_message_id);
            await msg.edit({ embeds: [embed] });
        } else {
            throw new Error('No pinned message — create one');
        }
    } catch {
        // Create new message and pin it
        const msg = await channel.send({ embeds: [embed] });
        await msg.pin();
        goals.setMessageId.run(msg.id);
    }
}

/**
 * Announce a completed restock cycle in #community-goals.
 */
async function announceRestock(cycleNumber) {
    const channel = client.channels.cache.get(config.CHANNELS.COMMUNITY_GOALS);
    if (!channel) return;

    const embed = new EmbedBuilder()
        .setTitle('🎉 Restock Goal Hit!')
        .setDescription(
            `**Cycle #${cycleNumber} complete!** The community just hit $${(CYCLE_GOAL / 100).toFixed(2)} in sales.\n\n` +
            'New product is on the way. Stay tuned for the drop!'
        )
        .setColor(0x2ecc71);

    await channel.send({ embeds: [embed] });
}

/**
 * Announce a lifetime milestone in #community-goals and #announcements.
 */
async function announceMilestone(milestone) {
    const embed = new EmbedBuilder()
        .setTitle(`🏆 Lifetime Milestone — ${milestone.label}!`)
        .setDescription(
            `The community just crossed **${milestone.label}** in lifetime sales!\n\n` +
            `**${milestone.reward}**`
        )
        .setColor(0xf1c40f);

    // Post in both community-goals and announcements
    const goalsChannel = client.channels.cache.get(config.CHANNELS.COMMUNITY_GOALS);
    if (goalsChannel) await goalsChannel.send({ embeds: [embed] });

    const announcements = client.channels.cache.get(config.CHANNELS.ANNOUNCEMENTS);
    if (announcements) await announcements.send({ embeds: [embed] });
}

/**
 * Initialize the pinned message on startup.
 */
async function initCommunityGoals() {
    const goal = goals.get.get();
    await updatePinnedMessage(goal);
    console.log(`Community goals initialized: Cycle #${goal.cycle}, $${(goal.cycle_revenue / 100).toFixed(2)}/$${(CYCLE_GOAL / 100).toFixed(2)}`);
}

export { addRevenue, initCommunityGoals, CYCLE_GOAL, LIFETIME_MILESTONES };
