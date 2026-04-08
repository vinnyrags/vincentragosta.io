/**
 * Bot Commands Reference — auto-synced to #bot-commands on startup.
 *
 * Each entry is an embed posted in order. On startup, the bot compares
 * existing embeds to this content and updates any that have changed.
 */

const messages = [
    // Message 1: Master Commands
    {
        title: '📖 Nous Command Reference',
        description: 'All commands at a glance. Organized by feature area.',
        color: 0xceff00,
    },

    // Message 2: Master Commands
    {
        title: '🎛️ Master Commands',
        description: [
            '**`!hype Product 1, Product 2`** — Pre-stream hype. Looks up products in Stripe, shows a preview with prices (detects sales), then posts a hype embed to #announcements with Buy Now buttons. Also drops raw checkout URLs in #ops for easy copy-paste to socials. React ✅ to confirm.',
            '> Example: `!hype Prismatic Evolutions Booster Box, Crown Zenith ETB`',
            '',
            '**`!live`** — Go live. Posts pre-order summary (queue stays open), starts livestream session, posts shop link in #announcements.',
            '',
            '**`!offline`** — End stream. Ends livestream session, closes queue (updates #queue embed), opens next pre-order queue, posts stream-ended in #announcements, posts stream recap to #analytics.',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 3: Pack Battles
    {
        title: '⚔️ Pack Battles',
        description: [
            '**`!battle start <product name> [max]`** — Start a battle. Bot searches Stripe for the product, posts embed with Buy Pack button to #pack-battles. No shipping at buy-in — only the winner pays. Default 20 max entries. Auto-closes when full.',
            '> Example: `!battle start Prismatic Evolutions 12`',
            '',
            '**`!battle join`** — Owner enters battle without payment. Decrements stock.',
            '',
            '**`!battle status`** — Show current battle (anyone can use)',
            '',
            '**`!battle close`** — Close entries, update original embed to CLOSED',
            '',
            '**`!battle cancel`** — Cancel the battle, notify entrants',
            '',
            '**`!battle winner @user`** — Declare winner. Assigns Aha role, cross-posts to #announcements. DMs winner shipping link if not already covered.',
            '',
            '*Only one battle can be active at a time. Close or cancel before starting a new one. One entry per user.*',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 4: Queue & Duck Race
    {
        title: '🦆 Queue & Duck Race',
        description: [
            '**`!queue`** — Show current queue (anyone can use)',
            '',
            '**`!queue open`** — Open a new pre-order queue (auto-opened by `!offline`)',
            '',
            '**`!queue close`** — Close queue, update #queue embed (auto-closed by `!offline`)',
            '',
            '**`!queue history`** — Show last 5 queues with winners',
            '',
            '**`!duckrace`** — Show duck race roster (1 entry per unique buyer from queue)',
            '',
            '**`!duckrace winner @user`** — Declare winner, assign Aha role, announce in #announcements',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 5: Card Shop
    {
        title: '🃏 Card Shop',
        description: [
            '**`!sell @buyer "Card Name" 25.00`** — Reserve a card for a specific buyer. Posts listing in #card-shop, DMs buyer a Buy Now button with identity capture. 30-minute reservation expiry.',
            '',
            '**`!list "Card Name" 25.00`** — List a card for open purchase in #card-shop. Posts a "Buy Now" button — buyer clicks, bot checks shipping status, creates personalized checkout.',
            '',
            '**`!sold <message_id>`** — Manually mark a listing as sold. Can also reply to the listing message. Auto-marked on Stripe payment.',
            '',
            '**`!pull "Name" 3.00`** — Open a pull box in #card-shop. Posts a Buy Pull button that stays open for unlimited buyers. Embed updates with a live purchase count. $1–$5 typical.',
            '> Example: `!pull "Mystery Pull Box" 3.00`',
            '',
            '**`!pull close`** — Close the active pull box. Shows final count and revenue.',
            '',
            '**`!pull status`** — Show active pull box info (pulls sold, revenue).',
            '',
            '*Card name must be in quotes. Price in dollars. Shipping: $10 US / $25 international (waived if already covered this week/month).*',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 6: Giveaways
    {
        title: '🎁 Giveaways',
        description: [
            '**`!giveaway start "Prize Name" [duration]`** — Start a giveaway. Posts embed in #giveaways with 🎁 reaction to enter, teaser in #announcements, social copy in #ops. Duration optional (e.g., `24h`, `3d`, `1w`).',
            '> Example: `!giveaway start "Prismatic Evolutions ETB" 48h`',
            '',
            '**`!giveaway status`** — Show current giveaway (anyone can use)',
            '',
            '**`!giveaway close`** — Close entries. Auto-closes when duration expires.',
            '',
            '**`!giveaway draw`** — Random winner. Assigns Aha role, announces in #giveaways, #announcements, and #and-in-the-back.',
            '',
            '**`!giveaway draw duckrace`** — Load giveaway entries as a duck race roster for stream drawing.',
            '',
            '**`!giveaway cancel`** — Cancel the giveaway.',
            '',
            '*Only verified members (Xipe role) can enter. One entry per person. Social copy is posted to #ops for cross-platform promotion.*',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 7: Analytics
    {
        title: '📊 Analytics',
        description: [
            '**`!snapshot`** — Post a snapshot of the current month to #analytics. Revenue, orders, buyers (new vs returning), stream count, avg per stream, top products, community goal state.',
            '',
            '**`!snapshot march`** — Snapshot for a specific month (current year)',
            '',
            '**`!snapshot 2026`** — Snapshot for a full year',
            '',
            '**`!snapshot march 2026`** — Snapshot for a specific month and year',
            '',
            '*Stream recaps are posted automatically to #analytics when `!offline` runs — no extra step needed.*',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 8: Other Commands
    {
        title: '🔧 Other Commands',
        description: [
            '**`!dropped-off`** — Weekly domestic shipping notification. DMs every domestic buyer with unshipped orders, posts "Orders Shipped" in #order-feed. Safe to re-run.',
            '',
            '**`!dropped-off intl`** — Monthly international shipping notification. Same flow, filtered to international buyers only.',
            '',
            '**`!shipping @user 10.00 [reason]`** — Send a Stripe checkout link for any shipping amount. DMs the user; falls back to channel if DMs are disabled.',
            '> Example: `!shipping @user 25.00 International shipping`',
            '',
            '**`!intl @user CA`** — Flag a user as international. `!intl @user US` to revert. `!intl @user` to check. `!intl list` to list all.',
            '',
            '**`!intl-ship`** — Month-end: DM international buyers with unpaid shipping this month.',
            '',
            '**`!shipping-audit`** — Verify all shipping collected. `!shipping-audit intl` or `!shipping-audit week` for filtered views.',
            '',
            '**`!waive @user`** — Waive shipping for a buyer. If they already paid this period, refunds via Stripe and removes the record. If they haven\'t paid, inserts a $0 waiver so all checkouts this period skip shipping.',
            '',
            '**`!refund @user [amount] [reason]`** — Refund the most recent purchase for a user. Full refund if no amount specified, partial if amount given.',
            '> Example: `!refund @user 10.00 Duplicate charge`',
            '',
            '**`!refund session <session_id> [amount] [reason]`** — Refund a specific Stripe session. Use when you need to target a specific transaction.',
            '> Example: `!refund session cs_xxx 25.00 Wrong product shipped`',
            '',
            '**`!nous #channel message`** — Post a message as Nous in any channel. Deletes the command so it looks like Nous spoke on its own.',
            '> Example: `!nous #announcements 🎉 Big news dropping tomorrow.`',
            '',
            '**`!link email@example.com`** — Manually link a Discord account to a shop email. Validates email in Stripe. Bot deletes the message to protect the email.',
            '',
            '**`!reset`** — Wipe all bot data (purchases, shipping, queues, battles, etc.) and re-sync stock via `!sync`. Requires confirmation. Owner only.',
            '',
            '*Account linking is also handled via the Link Account button in #welcome or automatically at checkout.*',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 9: Coupons
    {
        title: '🏷️ Coupons',
        description: [
            '**`!coupon create <CODE> <discount>`** — Create a Stripe coupon + promotion code. Discount is a percentage (`20%`) or dollar amount (`5.00`).',
            '> Examples: `!coupon create SPRING20 20%` or `!coupon create WELCOME 5.00`',
            '',
            '**`!coupon <CODE>`** — Activate a promo code. Checkout pages show a promo code input field. Announces in #announcements.',
            '> Example: `!coupon SPRING20`',
            '',
            '**`!coupon off`** — Deactivate the current promo code. Removes promo field from checkout.',
            '',
            '**`!coupon status`** — Show the currently active coupon.',
            '',
            '*Only one coupon can be active at a time. Customers who already checked out keep their discount. The promo code field only appears while a coupon is active — random browsers won\'t see it.*',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 10: Product Sync
    {
        title: '🔄 Product Sync',
        description: [
            '**`!sync`** — Full pipeline: Google Sheets → Stripe → WordPress. Deactivates stale products, syncs all rows from the spreadsheet to Stripe, then rebuilds WordPress product listings. Posts summary to #ops. New products trigger category alerts (#pokemon, #anime, etc.).',
            '',
            '**`!sync stripe`** — Stripe → WordPress only. Skips the Sheets step — useful if you edited Stripe directly or just need to refresh the shop.',
            '',
            '*Run `!sync` after updating the Google Sheets product catalog.*',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 10: Typical Stream Night Flow
    {
        title: '🔴 Typical Stream Night Flow',
        description: [
            '```',
            '!hype Product 1, Product 2            → Pre-stream hype (confirm with ✅)',
            '!live                                  → Go live (queue stays open)',
            '!sell @buyer "Card Name" 25.00        → Reserve a card for a viewer',
            '!list "Card Name" 25.00               → List a card for open purchase',
            '!coupon STREAM10                       → Activate promo code for flash deal',
            '!coupon off                            → Deactivate when deal window ends',
            '!battle start Product Name 12          → Start pack battle',
            '!battle close                          → Close entries',
            '!battle winner @user                   → Declare winner',
            '!duckrace                              → Show duck race roster',
            '!duckrace winner @user                 → Duck race winner',
            '!giveaway draw                         → Draw giveaway winner (if active)',
            '!offline                               → Close queue, post recap, open next queue',
            '!dropped-off                           → Monday: notify all buyers, mark orders shipped',
            '!snapshot                              → Anytime: post analytics snapshot to #analytics',
            '```',
        ].join('\n'),
        color: 0xceff00,
    },

    // Message 10: Shipping Model
    {
        title: '📦 Shipping Model',
        description: [
            '**Two tiers, two cadences:**',
            '• **Domestic (US):** $10 flat rate, collected weekly (Mon–Sun)',
            '• **International (CA+):** $25 flat rate, collected monthly',
            '',
            '```',
            'Normal shop (email entered)    →  Bot checks coverage → $10/$25 or skip',
            'Normal shop (email skipped)    →  Both options at checkout, buyer picks',
            'Discord button (!list/!hype)   →  Bot checks status → $10/$25 or skip',
            '!sell @buyer (not live)        →  Bot checks status → $10/$25 or skip',
            'Pack battle buy-in             →  No shipping (winner pays after declaration)',
            'Ad-hoc (!shipping @user amt)   →  Any amount, DM with checkout link',
            'Waiver (!waive @user)          →  Pre-waive or refund+remove shipping',
            'Weekly drop-off (!dropped-off) →  DMs domestic buyers',
            'Monthly (!dropped-off intl)    →  DMs international buyers',
            '```',
            '',
            '*Double-charge prevention: one payment covers all purchases for the period (week/month). Bot checks before every checkout.*',
        ].join('\n'),
        color: 0xceff00,
    },
];

export default messages;
