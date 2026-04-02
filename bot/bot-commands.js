/**
 * Bot Commands Reference — auto-synced to #bot-commands on startup.
 *
 * Each entry is a Discord message posted in order. On startup, the bot
 * compares existing messages to this content and updates any that have
 * changed, adds missing ones, and removes extras.
 */

const messages = [
    // Message 1: Header + Master Commands
    `# Nous Command Reference

## Master Commands
**\`!hype Product 1, Product 2\`** — Pre-stream hype. Looks up products in Stripe, shows a preview with prices (detects sales), then posts a hype embed to #announcements with direct checkout links. React ✅ to confirm.
> Example: \`!hype Prismatic Evolutions Booster Box, Crown Zenith ETB\`

**\`!live\`** — Go live. Posts pre-order summary (queue stays open), starts livestream session, posts shop link with \`?live=1\` (shipping-free for livestream buyers). Posts going-live in #announcements.

**\`!offline\`** — End stream. Ends livestream session, closes queue and archives to #card-night-queue, DMs $10 shipping link to each unique buyer, opens next pre-order queue, posts stream-ended in #announcements.`,

    // Message 2: Pack Battles
    `## Pack Battles
**\`!battle start <product name> [max]\`** — Start a battle. Bot searches Stripe for the product, posts embed with direct checkout link. Default 20 max entries.
> Example: \`!battle start Prismatic Evolutions 12\`

**\`!battle status\`** — Show current battle (anyone can use)

**\`!battle close\`** — Close entries, show final roster

**\`!battle cancel\`** — Cancel the battle, notify entrants

**\`!battle winner @user\`** — Declare winner. Assigns Aha role, cross-posts to #announcements and #and-in-the-back. Winner shipping bundled into \`!offline\`.

*Only one battle can be active at a time. Close or cancel before starting a new one.*`,

    // Message 3: Queue & Duck Race
    `## Queue & Duck Race
**\`!queue\`** — Show current queue (anyone can use)

**\`!queue open\`** — Open a new pre-order queue (auto-opened by \`!offline\`)

**\`!queue close\`** — Close queue, archive to #card-night-queue (auto-closed by \`!offline\`)

**\`!queue history\`** — Show last 5 queues with winners

**\`!duckrace\`** — Show duck race roster (1 entry per unique buyer from queue)

**\`!duckrace winner @user\`** — Declare winner, assign Aha role, cross-post`,

    // Message 4: Card Shop
    `## Card Shop
**\`!sell @buyer "Card Name" 25.00\`** — Reserve a card for a specific buyer. Posts listing in #card-shop, DMs buyer a checkout link. 15-minute expiry — if unpaid, relists as open.

**\`!list "Card Name" 25.00\`** — List a card for open purchase in #card-shop. Anyone can buy via the checkout link.

**\`!sold <message_id>\`** — Manually mark a listing as sold. Can also reply to the listing message. Auto-marked on Stripe payment.

*Card name must be in quotes. Price in dollars. Shipping ($5) is included in the checkout.*`,

    // Message 5: Typical Stream Night Flow
    `## Typical Stream Night Flow
\`\`\`
!hype Product 1, Product 2            → Pre-stream hype (confirm with ✅)
!live                                  → Go live (queue stays open)
!sell @buyer "Card Name" 25.00        → Reserve a card for a viewer
!list "Card Name" 25.00               → List a card for open purchase
!battle start Product Name 12          → Start pack battle
!battle close                          → Close entries
!battle winner @user                   → Declare winner
!duckrace                              → Show duck race roster
!duckrace winner @user                 → Duck race winner
!offline                               → Close queue, send shipping DMs, open next queue
!dropped-off                           → Monday: notify all buyers, mark orders shipped
\`\`\``,

    // Message 6: Shipping Model
    `## Shipping Model
\`\`\`
Normal shop (no livestream)    →  $10 at checkout
Livestream buyer (?live=1)     →  Free → $10 DM after !offline
Pack battle buy-in             →  Free (nothing ships)
Pack battle winner             →  Bundled in !offline shipping
Card shop (!sell / !list)      →  $5 at checkout (card + shipping)
Ad-hoc (!shipping @user amt)   →  Any amount, DM with checkout link
Pre-order queue                →  $10 at checkout
Weekly drop-off (!dropped-off) →  DMs each buyer + public #order-feed post
\`\`\``,

    // Message 7: Other Commands
    `## Other
**\`!dropped-off\`** — Weekly shipping notification. DMs every buyer with unshipped orders listing what they purchased, posts "Orders Shipped" in #order-feed, posts a detailed summary in #ops. Run every Monday after dropping off packages. Safe to re-run — only processes unshipped orders.

**\`!shipping @user 10.00 [reason]\`** — Send a Stripe checkout link for any shipping amount. DMs the user; falls back to channel if DMs are disabled. Reason is optional (defaults to "Shipping").
> Example: \`!shipping @user 5.00 Custom order shipping\`

**\`!link email@example.com\`** — Link Discord account to shop email (for role upgrades). Bot deletes the message to protect email. Validates email exists in Stripe.

*Note: Discord username field on Stripe checkout auto-links accounts — \`!link\` is a manual fallback.*`,
];

export default messages;
