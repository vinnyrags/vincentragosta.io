# vincentragosta.io — Runbook

Operating this project: where things live, how to deploy, what to purge, what bites.

**Architecture, provider pattern, block system, and testing live in [`CLAUDE.md`](CLAUDE.md) — this
file does not repeat them.** The Card Catalog Pipeline was **retired 2026-08-16**; the shop is no
longer operating and the Whatnot pipeline lives entirely in `nous`, touching no WordPress. For the full target list run
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

> 🚨 **OPEN TODO — `staging.itzenzo.tv` is publicly indexable. Found 2026-08-27.**
>
> It returns **200** with **no `X-Robots-Tag`**, **no robots meta tag**, and **no `robots.txt`** — the
> Next.js catch-all serves app HTML for `/robots.txt`. Nothing prevents Google crawling and indexing a
> staging copy of the storefront: duplicate content competing with `itzenzo.tv`, plus whatever test
> data staging happens to be holding.
>
> Contrast `staging.vincentragosta.io`, which is correctly protected by
> `add_header X-Robots-Tag "noindex, nofollow, noarchive" always;` in its vhost.
>
> **Fix:** add the same header to the `staging.itzenzo.tv` server block. It proxies to PM2, so the
> nginx layer is the right place and it takes about two minutes. A `robots.txt` route in the Next.js
> app is the belt-and-braces half.
>
> **Also note** — and this is why it went unnoticed — **modern WordPress and Next.js both ignore any
> "discourage search engines" setting when generating `robots.txt`.** WordPress's `do_robots()` reads
> `blog_public` and then never branches on it, so a private WP site serves the same permissive
> robots.txt as a public one. The `X-Robots-Tag` header is the only layer that reliably holds. Do not
> treat `blog_public=0` as sufficient anywhere.
>
> `staging.vincentragosta.io` has a lesser version of the same bug: a bare
> `location = /robots.txt` with no `try_files` makes it serve an HTML 404 from disk instead of letting
> WordPress answer. Cosmetic there, since the header covers it, but worth fixing when nearby.
>
> **`itzenzo.tv` has no `RUNBOOK.md`**, which is why this note lives here rather than in that repo.

Node for the PM2 processes: `/root/.nvm/versions/node/v20.20.2/bin`.

This droplet also hosts **ellenharvey**. It is **not** on deploy-kit — extending deploy-kit to
vincentragosta.io and ellenharvey is a tracked follow-up.

> ⚠️ **This zone's DNS carries a client's live preview link. Read before moving it to Cloudflare.**
>
> `ellenharvey.vincentragosta.io` is an **A record to `174.138.70.29` inside the `vincentragosta.io`
> zone** — which sits on **DigitalOcean DNS** (`ns1–3.digitalocean.com`) as of 2026-08-26. It is not
> a stray subdomain: it is the preview URL Ellen Harvey is **actively reviewing** ahead of her site's
> go-live, and it is the only way she can see the rebuild.
>
> When this zone moves to Cloudflare:
> - **The `ellenharvey` record must survive the import.** Verify it explicitly rather than trusting
>   the scan — if it's dropped, her preview goes dark and she has no way to tell it's our fault.
> - **Leave it unproxied (grey cloud).** That vhost terminates its own Let's Encrypt cert and runs
>   `blog_public=0`; proxying changes how both behave, and search-visibility is load-bearing here —
>   the staging copy must stay out of the index until cutover.
>
> Ellen's own domain, `ellenharvey.net`, is unrelated to this zone and goes in a **separate Cloudflare
> account in her name** — deliberately not this one. See `ellenharvey/RUNBOOK.md` and
> `akivili/engagements/ellen-harvey-engagement.md`.

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
  `scripts/setup-mega-menu.sh <staging|production>` (tracked; idempotent) after any DB re-sync
  into an env that lacks it, or when standing up a fresh clone.
- **Block patterns** — `make pull-patterns` / `pull-patterns-staging` export them from a live env
  back into PHP files.

## Traps

1. **A success banner is not proof.** `make deploy-production` ends with an unconditional
   `✓ Production deployed`, which prints even when the push moved nothing. Confirm the real
   ref-update line and re-verify on the box.
2. **This box cannot send email.** No MTA, no SMTP plugin, so `wp_mail()` fails silently —
   password resets, form notifications, admin notices. Verified 2026-08-14, unfixed.
3. **WP salts moved out of VCS on 2026-08-13.** Real salts live in each environment's gitignored
   `wp-config-env.php`; the tracked `wp-config.php` carries guarded placeholders and **must require
   the env file above the salt block** (PHP is first-wins). Verify a box by proving the env value
   *won* — compare `php -r 'require "wp-config-env.php"; echo AUTH_KEY;'` against
   `wp eval 'echo AUTH_KEY;'`. Checking only that the placeholder is gone gives a false pass.
4. **`composer update vincentragosta/ix` wipes `ix/node_modules`.** Run `npm install` inside the
   `ix` copy afterwards to restore build and test tooling.
5. **Root `composer update` without local ACF auth wipes the mythus mu-plugin.** Update the child
   vendor instead. Deploys are unaffected.
6. **The mega-menu is DB-only** and is wiped by a DB sync. Re-apply with
   `scripts/setup-mega-menu.sh <staging|production>` (tracked, idempotent).

> **Retired 2026-08-16 — traps that no longer apply.** The catalog-pipeline traps that used to live
> here (join-key column, `backfill-card-ids-production` running from production only, `update-stock`
> not writing back to the sheet, card-creation dedupe rules, the quarantined Stripe targets) went
> with the pipeline itself. The shop is no longer operating; the Whatnot pipeline lives in `nous`
> and touches no WordPress. Kept as a note so a returning reader knows they were removed
> deliberately rather than lost.

## See also

- [`CLAUDE.md`](CLAUDE.md) — architecture, Card Catalog Pipeline, Redis contract, testing
- [`docs/server-setup.md`](docs/server-setup.md) — how this droplet was built (`status: active`)
- [`docs/unified-queue.md`](docs/unified-queue.md) — queue data model (`status: historical` — its
  consumers are gone)
- `akivili/docs/knowledge-architecture-plan.md` — why this file exists
