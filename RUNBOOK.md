# vincentragosta.io — Runbook

Operating this project: where things live, how to deploy, what to purge, what bites.

**Architecture, provider pattern, block system, testing, and the Card Catalog Pipeline live in
[`CLAUDE.md`](CLAUDE.md) — this file does not repeat them.** For the full target list run
`make help`; it is generated from the Makefile's own `##` annotations, so it never drifts.

**Stack** WordPress · Mythus mu-plugin · IX parent + `vincentragosta` child · Timber/Twig · PHP 8.4 · DDEV
**Repo** `~/Projects/vinnyrags/personal/vincentragosta.io`
**Local** https://vincentragosta.io.ddev.site

---

## Environments

Staging and production are **two vhosts on the same droplet**. So is itzenzo.tv.

| Env | Host | Docroot | WP core | Branch | URL |
|---|---|---|---|---|---|
| local | DDEV | repo root | `wp/` | `develop` | https://vincentragosta.io.ddev.site |
| staging | `root@174.138.70.29` | `/var/www/staging.vincentragosta.io` | `…/wp` | `develop` | https://staging.vincentragosta.io |
| production | `root@174.138.70.29` | `/var/www/vincentragosta.io` | `…/wp` | `main` | https://vincentragosta.io |

**Co-located on the same box** (headless storefront, separate repo):

| | Dir | PM2 process | URL |
|---|---|---|---|
| itzenzo prod | `/var/www/itzenzo.tv` | `itzenzo-tv` | https://itzenzo.tv |
| itzenzo staging | `/var/www/staging.itzenzo.tv` | `staging-itzenzo` | https://staging.itzenzo.tv |

Node for the PM2 processes: `/root/.nvm/versions/node/v20.20.2/bin`.

This droplet also hosts **ellenharvey**. It is **not** on deploy-kit — extending deploy-kit to
vincentragosta.io and ellenharvey is a tracked follow-up.

Remote-only wp-cli takes the core path explicitly:

```bash
ssh root@174.138.70.29 "wp --path=/var/www/vincentragosta.io/wp --allow-root <cmd>"
```

## Deploy

Git remote `production` is the bare repo `root@174.138.70.29:/var/repo/vincentragosta.git`.
`origin` is GitHub. Deploying is a push to `production`; pushing to `origin` deploys nothing.

```bash
make deploy-staging      # git push production develop
make deploy-production   # checkout main; merge develop --ff-only; push production main; back to develop
make release             # merge develop→main, push BOTH to origin (GitHub only, no deploy)
```

**⚠️ A success banner is not proof.** `make deploy-production` ends with an unconditional
`✓ Production deployed`. A push that moved nothing (`Everything up-to-date`) still prints it.
Confirm the actual ref-update line — `d0f20ec..6560da9  main -> main` — and re-verify on the box.
This bit AVFTB on 2026-08-13, where a failed merge left production silently unpatched.

**`--ff-only` is load-bearing.** It only works while `main` and `develop` stay in sync. If someone
commits directly to `main`, the merge aborts and the deploy no-ops. Fix the topology; don't reach
for a plain merge without deciding that's what you want.

Three stale local branches exist (`chore/retire-stripe-nous-shop`,
`feature/itzenzo-cache-invalidation`, `feature/remove-itzenzo-cache`) — prune when convenient.

## Caches

**Redis object cache**, one daemon serving both WP environments, isolated by DB number + key salt:

| Env | Redis DB | `WP_CACHE_KEY_SALT` |
|---|---|---|
| production | 0 | `vincentragosta_` |
| staging | 1 | `staging_vincentragosta_` |

Config (`/etc/redis/redis.conf`): `maxmemory 256mb`, `allkeys-lru`, `save ""` — pure cache, no disk
persistence. The [redis-cache](https://wordpress.org/plugins/redis-cache/) drop-in overrides
`wp_cache_flush()`, so `wp cache flush` clears Redis too. Toggle with `wp redis enable|status|disable`.

**Direct DB mutation does not invalidate Redis.** After any `wp db query "UPDATE …"` from SSH, run
`wp cache flush`.

This site is recorded as **page-uncached** (no nginx FastCGI micro-cache, unlike the ARTHOUSE
droplets) — worth re-verifying before assuming a stale page is a cache problem.

**itzenzo.tv is ISR**, not WP-cached. Catalog changes reach it through the `revalidate-itzenzo`
step baked into the `-apply` targets. If prices look stale on itzenzo.tv but right in WP, the
revalidate leg is what failed.

## Content vs code

A deploy ships code. These do **not** travel with it:

- **Database** — `make push-production` / `pull-production`, `push-staging` / `pull-staging`
  (DB **and** uploads). `make refresh-from-production` refreshes local + staging from prod.
- **Uploads** — gitignored; carried by the same push/pull targets.
- **The mega-menu** — structure is DB-only and is wiped by a DB sync. Re-apply with
  `scripts/setup-mega-menu.sh`. ⚠️ **That script is currently untracked** — it exists only in the
  working tree, so a fresh clone cannot rebuild the menu. Commit it.
- **Block patterns** — `make pull-patterns` / `pull-patterns-staging` export them from a live env
  back into PHP files.

## Traps

1. **`../Nous` is not a typo, but it is a lie.** The Makefile sets `NOUS_DIR := $(realpath ../Nous)`
   and several targets `cd ../Nous/scripts/shop`. The repo was renamed to lowercase `nous`; this
   resolves only because macOS is case-insensitive. It will break on a case-sensitive filesystem
   (CI, Linux). The archived Discord bot at `_archived/nous` is a *different* repo — don't confuse them.
2. **Sheet column: `CLAUDE.md` says col Q, the Makefile says col S.** `CLAUDE.md` documents the
   join key as **col Q "WP Join Key"**; four Makefile comments and the `backfill-card-ids-production`
   help text say **col S**. One is stale. Verify against the live Singles tab before trusting either,
   then fix the loser.
3. **`backfill-card-ids-production` must run from production inventory only.** Staging and local
   post IDs diverge; writing them back to the sheet corrupts the join key for every environment.
4. **`make update-stock` does not touch the sheet.** The next sync reverts it. Update the sheet too.
5. **Card creation dedupes on `card_name` + `card_number` only — not set.** A re-added card that
   ever existed in WP is silently skipped.
6. **Retired Stripe targets fail safe.** `push-cards*`, `pull-cards*`, `pull-products*`,
   `rebuild-*-catalog` refuse to run without `CONFIRM_STRIPE=1`. If you find yourself typing that,
   stop — the current pipeline is Sheet → WP direct and has no Stripe leg.
7. **This box cannot send email.** No MTA, no SMTP plugin, so `wp_mail()` fails silently —
   password resets, form notifications, admin notices. Verified 2026-08-14, unfixed. Do not promise
   a client a password-reset link from here.
8. **WP salts moved out of VCS on 2026-08-13.** Real salts live in each environment's gitignored
   `wp-config-env.php`; the tracked `wp-config.php` carries guarded placeholders. To verify a box
   is actually patched, compare `php -r 'require "wp-config-env.php"; echo AUTH_KEY;'` against
   `wp eval 'echo AUTH_KEY;'` — checking only that the placeholder is absent gives a false pass.
9. **`composer update vincentragosta/ix` wipes `ix/node_modules`.** Run `npm install` inside the
   `ix` copy afterwards to restore build/test tooling.

## See also

- [`CLAUDE.md`](CLAUDE.md) — architecture, Card Catalog Pipeline, Redis contract, testing
- [`docs/server-setup.md`](docs/server-setup.md) — how this droplet was built (`status: active`)
- [`docs/unified-queue.md`](docs/unified-queue.md) — queue data model (`status: historical` — its
  consumers are gone)
- `akivili/docs/knowledge-architecture-plan.md` — why this file exists
