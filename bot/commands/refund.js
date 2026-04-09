/**
 * Refund Command
 *
 * !refund @user [amount] [reason]              — refund most recent purchase
 * !refund session <session_id> [amount] [reason] — refund a specific session
 *
 * Owner-only. Issues Stripe refunds (full or partial).
 */

import Stripe from 'stripe';
import config from '../config.js';
import { purchases } from '../db.js';
import { EmbedBuilder } from 'discord.js';
import { sendEmbed, getMember } from '../discord.js';

const stripe = new Stripe(config.STRIPE_SECRET_KEY);

async function handleRefund(message, args) {
    // Owner-only
    if (!message.member.roles.cache.has(config.ROLES.AKIVILI)) {
        return message.reply('Only the server owner can issue refunds.');
    }

    if (args.length === 0) {
        return message.reply(
            'Usage:\n' +
            '`!refund @user [amount] [reason]` — refund most recent purchase\n' +
            '`!refund session <session_id> [amount] [reason]` — refund a specific session'
        );
    }

    // Determine mode: session-based or user-based
    const isSessionMode = args[0]?.toLowerCase() === 'session';

    let sessionId;
    let purchase;
    let refundArgs;

    if (isSessionMode) {
        // !refund session <session_id> [amount] [reason]
        sessionId = args[1];
        if (!sessionId) {
            return message.reply('Usage: `!refund session <session_id> [amount] [reason]`');
        }
        refundArgs = args.slice(2);

        // Look up purchase for context (optional — refund works even without local record)
        purchase = purchases.getBySessionId.get(sessionId);
    } else {
        // !refund @user [amount] [reason]
        const mentioned = message.mentions.users.first();
        if (!mentioned) {
            return message.reply('Usage: `!refund @user [amount] [reason]`');
        }

        // Find most recent purchase
        purchase = purchases.getRecentByDiscordId.get(mentioned.id);
        if (!purchase) {
            return message.reply(`No purchases found for <@${mentioned.id}>.`);
        }

        sessionId = purchase.stripe_session_id;
        // Remove mention from args to parse amount/reason
        refundArgs = args.filter((a) => !a.startsWith('<@'));
    }

    // Parse optional amount
    const amountArg = refundArgs.find((a) => /^\d+(\.\d{1,2})?$/.test(a));
    const amountCents = amountArg ? Math.round(parseFloat(amountArg) * 100) : null;

    // Parse optional reason
    const amountIndex = amountArg ? refundArgs.indexOf(amountArg) : -1;
    const reason = amountIndex >= 0
        ? refundArgs.slice(amountIndex + 1).join(' ') || null
        : refundArgs.filter((a) => !a.startsWith('<@')).join(' ') || null;

    try {
        // Retrieve the checkout session to get PaymentIntent
        const session = await stripe.checkout.sessions.retrieve(sessionId, {
            expand: ['payment_intent'],
        });

        const paymentIntent = session.payment_intent;
        if (!paymentIntent || typeof paymentIntent === 'string') {
            return message.reply(`Could not retrieve payment intent for session \`${sessionId}\`.`);
        }

        // Build refund params
        const refundParams = {
            payment_intent: paymentIntent.id,
        };

        if (amountCents) {
            refundParams.amount = amountCents;
        }

        if (reason) {
            refundParams.metadata = { reason };
        }

        // Issue refund
        const refund = await stripe.refunds.create(refundParams);

        const refundDollars = (refund.amount / 100).toFixed(2);
        const originalDollars = purchase ? (purchase.amount / 100).toFixed(2) : 'unknown';
        const productName = purchase?.product_name || 'Unknown';
        const isPartial = amountCents && purchase?.amount && amountCents < purchase.amount;

        await message.channel.send(
            `Refund issued — **$${refundDollars}**${isPartial ? ' (partial)' : ''} for ${productName}. Stripe refund \`${refund.id}\``
        );

        // Log to #ops
        await sendEmbed('OPS', {
            title: `💸 Refund Issued${isPartial ? ' (Partial)' : ''}`,
            description: [
                `**Product:** ${productName}`,
                `**Original:** $${originalDollars}`,
                `**Refunded:** $${refundDollars}`,
                reason ? `**Reason:** ${reason}` : null,
                `**Session:** \`${sessionId}\``,
                `**Refund ID:** \`${refund.id}\``,
                `**By:** ${message.author.tag}`,
            ].filter(Boolean).join('\n'),
            color: 0xe74c3c,
        });

        // DM the buyer about the refund
        const discordUserId = purchase?.discord_user_id || (isSessionMode ? null : message.mentions.users.first()?.id);
        if (discordUserId) {
            try {
                const member = await getMember(discordUserId);
                if (member) {
                    const dm = await member.createDM();
                    const dmEmbed = new EmbedBuilder()
                        .setTitle(`💸 Refund Processed${isPartial ? ' (Partial)' : ''}`)
                        .setDescription(
                            `**$${refundDollars}** has been refunded for **${productName}**.\n\n` +
                            `The refund should appear on your statement within 5-10 business days.` +
                            (reason ? `\n\n**Reason:** ${reason}` : '')
                        )
                        .setColor(0xceff00);
                    await dm.send({ embeds: [dmEmbed] });
                }
            } catch (e) {
                console.error(`Failed to DM refund notification to ${discordUserId}:`, e.message);
            }
        }
    } catch (e) {
        console.error('Refund error:', e.message);

        if (e.message.includes('has already been refunded')) {
            return message.reply('This payment has already been fully refunded.');
        }

        return message.reply(`Stripe refund failed: ${e.message}`);
    }
}

export { handleRefund };
