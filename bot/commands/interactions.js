/**
 * Discord Button & Modal Interaction Handlers
 *
 * Routes button clicks and modal submissions from Discord components
 * to the appropriate checkout flow. Each handler:
 *   1. Identifies the buyer (Discord ID → email lookup)
 *   2. Checks their shipping status
 *   3. Creates a personalized Stripe checkout session
 *   4. Replies with an ephemeral checkout URL
 *
 * Button customId prefixes:
 *   card-buy-{listingId}  — card shop purchase
 *   hype-buy-{priceId}    — hype product purchase
 *   battle-buy-{battleId} — battle buy-in
 *
 * Modal customId:
 *   email-link-{context}  — email entry for unlinked buyers
 */

import { ModalBuilder, TextInputBuilder, TextInputStyle, ActionRowBuilder } from 'discord.js';
import config from '../config.js';
import { purchases, cardListings, battles } from '../db.js';
import {
    hasShippingCoveredByDiscordId,
    hasShippingCovered,
    getShippingLabel,
    isInternational,
    formatShippingRate,
} from '../shipping.js';

const baseUrl = config.SHOP_URL.replace(/\/shop$/, '');

/**
 * Route button interactions by customId prefix.
 */
async function handleButtonInteraction(interaction) {
    const customId = interaction.customId;

    if (customId.startsWith('card-buy-')) {
        const listingId = customId.replace('card-buy-', '');
        return handleCardBuy(interaction, Number(listingId));
    }

    if (customId.startsWith('hype-buy-')) {
        const priceId = customId.replace('hype-buy-', '');
        return handleHypeBuy(interaction, priceId);
    }

    if (customId.startsWith('pull-buy-')) {
        const listingId = customId.replace('pull-buy-', '');
        return handlePullBuy(interaction, Number(listingId));
    }

    if (customId.startsWith('battle-buy-')) {
        const battleId = customId.replace('battle-buy-', '');
        return handleBattleBuy(interaction, Number(battleId));
    }
}

/**
 * Handle email modal submission for unlinked buyers.
 */
async function handleModalSubmit(interaction) {
    if (!interaction.customId.startsWith('email-link-')) return;

    const email = interaction.fields.getTextInputValue('email_input')?.trim();
    if (!email || !email.includes('@')) {
        return interaction.reply({ content: 'Please enter a valid email address.', ephemeral: true });
    }

    // Auto-link the buyer
    purchases.linkDiscord.run(interaction.user.id, email);

    // Parse the original context to retry the checkout
    const context = interaction.customId.replace('email-link-', '');
    const [type, ...rest] = context.split('-');
    const id = rest.join('-');

    await interaction.deferReply({ ephemeral: true });

    if (type === 'card') {
        return handleCardBuy(interaction, Number(id), true);
    } else if (type === 'pull') {
        return handlePullBuy(interaction, Number(id), true);
    } else if (type === 'hype') {
        return handleHypeBuy(interaction, id, true);
    } else if (type === 'battle') {
        return handleBattleBuy(interaction, Number(id), true);
    }

    await interaction.editReply({ content: `Email linked! Please click the Buy button again.` });
}

/**
 * Show email prompt modal for unlinked buyers.
 */
function showEmailModal(interaction, contextPrefix) {
    const modal = new ModalBuilder()
        .setCustomId(`email-link-${contextPrefix}`)
        .setTitle('Enter Your Email');

    const emailInput = new TextInputBuilder()
        .setCustomId('email_input')
        .setLabel('Email address (used for shipping status)')
        .setStyle(TextInputStyle.Short)
        .setRequired(true)
        .setPlaceholder('you@example.com');

    const row = new ActionRowBuilder().addComponents(emailInput);
    modal.addComponents(row);

    return interaction.showModal(modal);
}

/**
 * Build a personalized checkout URL with optional ?user= param.
 */
function buildCheckoutUrl(path, discordUserId) {
    const url = `${baseUrl}/bot/${path}`;
    return discordUserId ? `${url}?user=${discordUserId}` : url;
}

/**
 * Card shop button handler.
 */
async function handleCardBuy(interaction, listingId, isDeferred = false) {
    const discordUserId = interaction.user.id;

    // Check if buyer has a linked email
    const link = purchases.getEmailByDiscordId.get(discordUserId);
    if (!link && !isDeferred) {
        return showEmailModal(interaction, `card-${listingId}`);
    }

    if (!isDeferred) await interaction.deferReply({ ephemeral: true });

    const listing = cardListings.getById.get(listingId);
    if (!listing || (listing.status !== 'active' && listing.status !== 'reserved')) {
        return interaction.editReply({ content: 'This card is no longer available.' });
    }

    const covered = hasShippingCoveredByDiscordId(discordUserId);
    const checkoutUrl = buildCheckoutUrl(`card-shop/checkout/${listingId}`, discordUserId);

    const shippingNote = covered
        ? '✅ Shipping already covered this period!'
        : `📦 Includes ${formatShippingRate(getShippingLabel(discordUserId).rate)} shipping`;

    await interaction.editReply({
        content: `🃏 **${listing.card_name}** — $${(listing.price / 100).toFixed(2)}\n${shippingNote}\n\n🛒 **[Complete Purchase](${checkoutUrl})**`,
    });
}

/**
 * Hype product button handler.
 */
async function handleHypeBuy(interaction, priceId, isDeferred = false) {
    const discordUserId = interaction.user.id;

    const link = purchases.getEmailByDiscordId.get(discordUserId);
    if (!link && !isDeferred) {
        return showEmailModal(interaction, `hype-${priceId}`);
    }

    if (!isDeferred) await interaction.deferReply({ ephemeral: true });

    const covered = hasShippingCoveredByDiscordId(discordUserId);
    const checkoutUrl = buildCheckoutUrl(`product/checkout/${priceId}`, discordUserId);

    const shippingNote = covered
        ? '✅ Shipping already covered this period!'
        : `📦 Includes ${formatShippingRate(getShippingLabel(discordUserId).rate)} shipping`;

    await interaction.editReply({
        content: `🔥 Ready to check out!\n${shippingNote}\n\n🛒 **[Complete Purchase](${checkoutUrl})**`,
    });
}

/**
 * Battle buy-in button handler.
 */
async function handleBattleBuy(interaction, battleId, isDeferred = false) {
    const discordUserId = interaction.user.id;

    const link = purchases.getEmailByDiscordId.get(discordUserId);
    if (!link && !isDeferred) {
        return showEmailModal(interaction, `battle-${battleId}`);
    }

    if (!isDeferred) await interaction.deferReply({ ephemeral: true });

    const battle = battles.getBattleById.get(battleId);
    if (!battle || battle.status !== 'open') {
        return interaction.editReply({ content: 'This battle is no longer open.' });
    }

    // Check if user already entered this battle
    const entries = battles.getEntries.all(battle.id);
    if (entries.some((e) => e.discord_user_id === discordUserId)) {
        return interaction.editReply({ content: `You're already in this battle! One entry per person. Good luck! 🍀` });
    }

    const covered = hasShippingCoveredByDiscordId(discordUserId);
    const checkoutUrl = buildCheckoutUrl(`battle/checkout/${battleId}`, discordUserId);

    const shippingNote = covered
        ? '✅ Shipping already covered this period!'
        : `📦 Includes ${formatShippingRate(getShippingLabel(discordUserId).rate)} shipping`;

    await interaction.editReply({
        content: `⚔️ **${battle.product_name}** Pack Battle\n${shippingNote}\n\n🛒 **[Buy Your Pack](${checkoutUrl})**`,
    });
}

/**
 * Pull box button handler — same as card buy but allows 'pull' status.
 */
async function handlePullBuy(interaction, listingId, isDeferred = false) {
    const discordUserId = interaction.user.id;

    const link = purchases.getEmailByDiscordId.get(discordUserId);
    if (!link && !isDeferred) {
        return showEmailModal(interaction, `pull-${listingId}`);
    }

    if (!isDeferred) await interaction.deferReply({ ephemeral: true });

    const listing = cardListings.getById.get(listingId);
    if (!listing || listing.status !== 'pull') {
        return interaction.editReply({ content: 'This pull box is no longer available.' });
    }

    const covered = hasShippingCoveredByDiscordId(discordUserId);
    const checkoutUrl = buildCheckoutUrl(`card-shop/checkout/${listingId}`, discordUserId);

    const shippingNote = covered
        ? '✅ Shipping already covered this period!'
        : `📦 Includes ${formatShippingRate(getShippingLabel(discordUserId).rate)} shipping`;

    await interaction.editReply({
        content: `🎰 **${listing.card_name}** — $${(listing.price / 100).toFixed(2)}\n${shippingNote}\n\n🛒 **[Buy Pull](${checkoutUrl})**`,
    });
}

export { handleButtonInteraction, handleModalSubmit };
