# Unified Queue

> Extracted from `CLAUDE.md` (2026-06-04) to keep that file under the size limit. Post-Whatnot-pivot this machinery is parked but fully operational — see `akivili/business/whatnot-first-strategy.md`.

The Shop provider owns a single ledger of every "thing waiting to happen on stream" — orders, pack battle entries, pull box entries, and request-to-see card requests — so the same data feeds the Discord `/queue` slash command, the public itzenzo.tv homepage Live Queue section, and any future admin tooling.

## Data model

Two custom tables, created via `dbDelta()` in `Hooks/QueueMigration.php` with a version-keyed option (`shop_queue_schema_version`):

- `wp_queue_sessions` — one row per livestream queue window. Columns: `id`, `status` (`open` / `closed` / `racing` / `complete`), `channel_message_id` (Discord embed pointer), `duck_race_winner_user_id`, `created_at`, `closed_at`. Indexed on `status` and `created_at`.
- `wp_queue_entries` — one row per queued item. Columns: `id`, `session_id`, `type` (`order` / `pack_battle` / `pull_box` / `rts`), `source` (`discord` / `shop`), `status` (`queued` / `active` / `completed` / `skipped`), `discord_user_id`, `discord_handle`, `customer_email`, `order_number`, `display_name`, `detail_label`, `detail_data` (JSON), `stripe_session_id`, `external_ref` (idempotency key), `created_at`, `completed_at`. Indexed on `(session_id, status, created_at)`, `stripe_session_id`, `external_ref`, and `(type, source)`.

**Position is computed at read time from `created_at` order — never stored.** This avoids the classic queue-shift race and keeps inserts cheap.

All `$wpdb` access goes through `Support/QueueRepository.php`. Two serialization shapes:
- `serializeEntry()` — public/homepage shape with `identifier { kind, label }` and `detail { label, data }` discriminated union by type.
- `serializeEntryRaw()` — camelCase raw fields for Nous (which needs `discordUserId` for `<@id>` mentions).

## REST surface

Seven endpoints under `/wp-json/shop/v1/queue/*`, registered through the standard `RestManager` route map on `ShopProvider`:

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `GET /queue` | public | Snapshot of active session: session metadata, current `active` entry, top-N `upcoming`, total. ETag-cached, returns 304 on no change. |
| `GET /queue/sessions` | public | Recent sessions list (for `/queue history`). |
| `GET /queue/sessions/{id}/entries` | public | Full entries list + unique buyers (for duck race roster). Returns `serializeEntryRaw()` shape. |
| `POST /queue/sessions` | bot-secret | Open a new session. Refuses if one is already open. |
| `PATCH /queue/sessions/{id}` | bot-secret | Update status (`closed` / `racing` / `complete`), `channel_message_id`, `duck_race_winner_user_id`. |
| `POST /queue/entries` | bot-secret | Create entry. Idempotent on `external_ref` — re-submitting the same key returns the existing entry with `duplicate: true`. |
| `PATCH /queue/entries/{id}` | bot-secret | Update entry status / fields. |

Bot-secret auth uses the existing `LIVESTREAM_SECRET` constant via `X-Bot-Secret` header (`hash_equals` comparison).

## WPGraphQL exposure

`Hooks/QueueGraphQL.php` registers four custom object types (`QueueEntryIdentifier`, `QueueEntryDetail`, `QueueEntry`, `QueueSession`, `LiveQueueSnapshot`) and a single root field:

```graphql
liveQueue(limit: Int): LiveQueueSnapshot
```

Returns the active session snapshot, or an empty payload (`session: null`) when no session is open. itzenzo.tv consumes this for the homepage initial render before subscribing to SSE for live updates.

## Change broadcasting

`QueueRepository.createSession()`, `updateSession()`, `createEntry()`, and `updateEntry()` each fire a corresponding action:

- `shop_queue_session_created` (session row)
- `shop_queue_session_updated` (after, before)
- `shop_queue_entry_created` (entry row)
- `shop_queue_entry_updated` (after, before)

`Hooks/QueueChangeWebhook.php` subscribes to all four and POSTs `{ event, data, timestamp }` to `NOUS_BOT_URL/webhooks/queue-changed` with `X-Bot-Secret`. The post is `blocking: false` with a 2-second timeout — Nous outage cannot delay or fail a queue write. Event types emitted to Nous:

- `entry.added` / `entry.advanced` / `entry.completed` / `entry.updated`
- `session.opened` / `session.updated`

Nous re-broadcasts each event to its connected SSE clients (the itzenzo.tv homepage). Phase summary: WP is canonical, Nous is the SSE broadcaster (PHP-FPM is bad at long-lived connections, Node is fine), itzenzo.tv hits Nous through a Next.js Route Handler proxy.

## Producers (who calls the writes)

Four code paths put rows into `wp_queue_entries`:

1. **Orders** — Nous Stripe webhook → `addToQueue()` in `commands/queue.js` → `queueSource.addEntry({ type: 'order', source: 'shop' })`. One entry per line item.
2. **Pack battles** — Nous Stripe webhook → `checkBattlePayment()` in `webhooks/stripe.js` after `confirmPayment` → `queueSource.addEntry({ type: 'pack_battle' })`. Idempotent on `stripe:<sid>:battle`.
3. **Pull boxes** — Nous Stripe webhook → `recordPullBoxPurchase()` in `commands/pull.js` → `queueSource.addEntry({ type: 'pull_box', detailLabel: 'Pull Box • slots N, M, ...' })`. Perpetual single-box model — the box auto-creates from settings (`pb_price_id`, `pb_total_slots`) on first access, and operator runs `/pull reset` (Discord) or clicks the WP admin "Reset Pull Box" button when the chase prize hits. Pull-box checkouts skip shipping at checkout; settlement runs at `/offline` — see "Speculative shipping" below.
4. **Request-to-see** — WP `CardRequestEndpoint::callback()` → `QueueRepository::createEntry({ type: 'rts', external_ref: 'rts:{cardId}:{email}' })`. Single write, no parallel ledger; idempotent on the external_ref (re-submission while the entry is still queued/active returns the existing row). Requires an active queue session — returns 503 if none exists, since the bot is supposed to keep one open between streams.

## Speculative shipping

Pull boxes, individual booster packs (product CPT with `skip_shipping_at_checkout = true`), and pack-battle entries are **speculative** — the buyer pays only the buy-in at checkout, no shipping line. The `/offline` command runs a settlement scan: buyers with held items since their last DM AND no shipping payment for the current period get a Discord DM with a Stripe shipping link. Dedup via the `speculative_shipping_dms` table — only fresh purchases since the last DM trigger a new one. Unlinked-Discord buyers surface in `#ops` for manual follow-up. The `purchases.source` column carries `'pull_box' | 'speculative' | 'pack_battle'` to drive the dedup query. Buyer-facing policy lives at `/how-it-works/shipping` on itzenzo.tv (4-week hold before cards return to pulling pool).

All four feed the same `wp_queue_entries` table, the same actions fire, the same SSE events reach the homepage, and the same `/queue` Discord embed renders.

## Testing the queue path

Bot-side: Nous's `npm run test:critical` (CLI replacement for the legacy `!test` Discord command) opens with the active queue source (`config.QUEUE_SOURCE`) printed in the header, then probes it with `getActiveQueue()` before running the rest of the buyer-critical-path suite — fails loud if WP is unreachable.

WP-side: unit tests at `tests/Unit/Providers/Shop/Support/QueueRepositoryTest.php` (serialization), `tests/Unit/Providers/Shop/Endpoints/QueueEndpointsTest.php` (route/methods/auth), and `tests/Unit/Providers/Shop/Hooks/QueueMigrationTest.php` (table naming).
