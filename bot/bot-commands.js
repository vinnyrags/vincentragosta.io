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
        color: 0x2ecc71,
    },

    // Message 2: Master Commands
    {
        title: '🎛️ Master Commands',
        description: [
            '**`!hype Product 1, Product 2`** — Pre-stream hype. Looks up products in Stripe, shows a preview with prices (detects sales), then posts a hype embed to #announcements with direct checkout links. Also drops raw checkout URLs in #ops for easy copy-paste to IG Stories, YouTube descriptions, etc. React ✅ to confirm.',
            '> Example: `!hype Prismatic Evolutions Booster Box, Crown Zenith ETB`',
            '',
            '**`!live`** — Go live. Posts pre-order summary (queue stays open), starts livestream session, posts shop link with `?live=1` (shipping-free for livestream buyers). Posts going-live in #announcements.',
            '',
            '**`!offline`** — End stream. Ends livestream session, closes queue and archives to #card-night-queue, DMs $10 shipping link to each unique buyer, opens next pre-order queue, posts stream-ended in #announcements, posts stream recap to #analytics.',
        ].join('\n'),
        color: 0x2ecc71,
    },

    // Message 3: Pack Battles
    {
        title: '⚔️ Pack Battles',
        description: [
            '**`!battle start <product name> [max]`** — Start a battle. Bot searches Stripe for the product, posts embed with direct checkout link. Default 20 max entries.',
            '> Example: `!battle start Prismatic Evolutions 12`',
            '',
            '**`!battle status`** — Show current battle (anyone can use)',
            '',
            '**`!battle close`** — Close entries, show final roster',
            '',
            '**`!battle cancel`** — Cancel the battle, notify entrants',
            '',
            '**`!battle winner @user`** — Declare winner. Assigns Aha role, cross-posts to #announcements and #and-in-the-back. Winner shipping bundled into `!offline`.',
            '',
            '*Only one battle can be active at a time. Close or cancel before starting a new one.*',
        ].join('\n'),
        color: 0x2ecc71,
    },

    // Message 4: Queue & Duck Race
    {
        title: '🦆 Queue & Duck Race',
        description: [
            '**`!queue`** — Show current queue (anyone can use)',
            '',
            '**`!queue open`** — Open a new pre-order queue (auto-opened by `!offline`)',
            '',
            '**`!queue close`** — Close queue, archive to #card-night-queue (auto-closed by `!offline`)',
            '',
            '**`!queue history`** — Show last 5 queues with winners',
            '',
            '**`!duckrace`** — Show duck race roster (1 entry per unique buyer from queue)',
            '',
            '**`!duckrace winner @user`** — Declare winner, assign Aha role, cross-post',
        ].join('\n'),
        color: 0x2ecc71,
    },

    // Message 5: Card Shop
    {
        title: '🃏 Card Shop',
        description: [
            '**`!sell @buyer "Card Name" 25.00`** — Reserve a card for a specific buyer. Posts listing in #card-shop, DMs buyer a checkout link. 15-minute expiry — if unpaid, relists as open.',
            '',
            '**`!list "Card Name" 25.00`** — List a card for open purchase in #card-shop. Anyone can buy via the checkout link.',
            '',
            '**`!sold <message_id>`** — Manually mark a listing as sold. Can also reply to the listing message. Auto-marked on Stripe payment.',
            '',
            '*Card name must be in quotes. Price in dollars. Shipping ($5) is included in the checkout.*',
        ].join('\n'),
        color: 0x2ecc71,
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
        color: 0x2ecc71,
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
        color: 0x2ecc71,
    },

    // Message 8: Other Commands
    {
        title: '🔧 Other Commands',
        description: [
            '**`!dropped-off`** — Weekly shipping notification. DMs every buyer with unshipped orders listing what they purchased, posts "Orders Shipped" in #order-feed, posts a detailed summary in #ops. Run every Monday after dropping off packages. Safe to re-run — only processes unshipped orders.',
            '',
            '**`!shipping @user 10.00 [reason]`** — Send a Stripe checkout link for any shipping amount. DMs the user; falls back to channel if DMs are disabled. Reason is optional (defaults to "Shipping").',
            '> Example: `!shipping @user 5.00 Custom order shipping`',
            '',
            '**`!link email@example.com`** — Link Discord account to shop email (for role upgrades). Bot deletes the message to protect email. Validates email exists in Stripe.',
            '',
            '*Note: Discord username field on Stripe checkout auto-links accounts — `!link` is a manual fallback.*',
        ].join('\n'),
        color: 0x2ecc71,
    },

    // Message 9: Product Sync
    {
        title: '🔄 Product Sync',
        description: [
            '**`!sync`** — Full pipeline: Google Sheets → Stripe → WordPress. Deactivates stale products, syncs all rows from the spreadsheet to Stripe, then rebuilds WordPress product listings. Posts summary to #ops. New products trigger category alerts (#pokemon, #anime, etc.).',
            '',
            '**`!sync stripe`** — Stripe → WordPress only. Skips the Sheets step — useful if you edited Stripe directly or just need to refresh the shop.',
            '',
            '*Run `!sync` after updating the Google Sheets product catalog.*',
        ].join('\n'),
        color: 0x2ecc71,
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
            '!battle start Product Name 12          → Start pack battle',
            '!battle close                          → Close entries',
            '!battle winner @user                   → Declare winner',
            '!duckrace                              → Show duck race roster',
            '!duckrace winner @user                 → Duck race winner',
            '!giveaway draw                         → Draw giveaway winner (if active)',
            '!offline                               → Close queue, send shipping DMs, open next queue',
            '!dropped-off                           → Monday: notify all buyers, mark orders shipped',
            '!snapshot                              → Anytime: post analytics snapshot to #analytics',
            '```',
        ].join('\n'),
        color: 0x2ecc71,
    },

    // Message 10: Shipping Model
    {
        title: '📦 Shipping Model',
        description: [
            '```',
            'Normal shop (no livestream)    →  $10 at checkout',
            'Livestream buyer (?live=1)     →  Free → $10 DM after !offline',
            'Pack battle buy-in             →  Free (nothing ships)',
            'Pack battle winner             →  Bundled in !offline shipping',
            'Card shop (!sell / !list)      →  $5 at checkout (card + shipping)',
            'Ad-hoc (!shipping @user amt)   →  Any amount, DM with checkout link',
            'Pre-order queue                →  $10 at checkout',
            'Weekly drop-off (!dropped-off) →  DMs each buyer + public #order-feed post',
            '```',
        ].join('\n'),
        color: 0x2ecc71,
    },
];

export default messages;
