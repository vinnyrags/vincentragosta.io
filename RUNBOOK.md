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

> ✅ **RESOLVED 2026-08-28 — `staging.itzenzo.tv` is no longer indexable.** (Found 2026-08-27.)
>
> It returned **200** with **no `X-Robots-Tag`**, **no robots meta tag**, and **no `robots.txt`** — the
> Next.js catch-all serves app HTML for `/robots.txt`. Nothing prevented Google crawling and indexing a
> staging copy of the storefront: duplicate content competing with `itzenzo.tv`, plus whatever test
> data staging happens to be holding.
>
> **Fix applied:** `add_header X-Robots-Tag "noindex, nofollow, noarchive" always;` in the
> `staging.itzenzo.tv` server block, **and repeated inside `location /_next/static`** — that block
> sets its own `add_header` (`Cache-Control`), and a location with any `add_header` of its own
> discards every header inherited from the server block. Both now verified in the live response.
> Production `itzenzo.tv` deliberately still has no such header.
>
> **Still open (app-side, itzenzo.tv repo):** a real `robots.txt` route in Next.js as the
> belt-and-braces half. Staging `/robots.txt` is still a 34KB HTML 404 from the catch-all; the header
> is what holds today.
>
> **Also note** — and this is why it went unnoticed — **modern WordPress and Next.js both ignore any
> "discourage search engines" setting when generating `robots.txt`.** WordPress's `do_robots()` reads
> `blog_public` and then never branches on it, so a private WP site serves the same permissive
> robots.txt as a public one. The `X-Robots-Tag` header is the only layer that reliably holds. Do not
> treat `blog_public=0` as sufficient anywhere.
>
> `staging.vincentragosta.io` had a lesser version of the same bug — a bare `location = /robots.txt`
> with no `try_files` served an HTML 404 from disk instead of letting WordPress answer. **Also fixed
> 2026-08-28**; it now returns the Yoast-generated body at `200 text/plain`.
>
> **Production `vincentragosta.io` had the same robots.txt bug — also fixed 2026-08-28.** There it
> was not cosmetic: the `Sitemap:` pointer and `Disallow: /wp/wp-admin/` were never served. Now
> `200 text/plain`. Note that `robots.txt` and `sitemap_index.xml` both return
> `X-Robots-Tag: noindex, follow` — that is **Yoast**, it predates the change, and it does not affect
> the directives inside. The homepage has no `X-Robots-Tag` and no meta robots tag; production is
> indexable. See `akivili/docs/fleet-nginx-security-sweep.md`.
>
> **`itzenzo.tv` has no `RUNBOOK.md`**, which is why this note lives here rather than in that repo.

Node for the PM2 processes: `/root/.nvm/versions/node/v20.20.2/bin`.

This droplet is **not** on deploy-kit — extending deploy-kit to it is a tracked follow-up.

> **It no longer hosts ellenharvey.** The pre-launch preview at `ellenharvey.vincentragosta.io` was
> fully removed 2026-08-28 once her site went live on her own droplet: docroot, `ellenharvey`
> database, `/var/repo/ellenharvey.git`, vhost, TLS cert and the DNS A record are all gone. A
> verified backup sits in `_archived/ellenharvey-preview-2026-08-28/`. See `ellenharvey/RUNBOOK.md`.
>
> Her own domain lives in a **separate Cloudflare account in her name** — deliberately not this one.

## DNS — Cloudflare, moved 2026-08-28

Moved off DigitalOcean DNS (`ns1–3.digitalocean.com`) to Cloudflare
(`dane.ns.cloudflare.com`, `mary.ns.cloudflare.com`). Registrar is **GoDaddy** — nameservers only,
no registrar transfer. **The DigitalOcean zone was deliberately left intact as the rollback**: put
the three DO nameservers back and the old zone serves again, unchanged.

| Record | Proxy | Why |
|---|---|---|
| `@`, `www`, `staging` | 🟠 **Proxied** | CDN, DDoS absorption, WAF. Cert renewal via HTTP-01 works behind the proxy — proven on AVFTB, which is proxied and uses `authenticator = nginx`. |
| `packages` | ⚪ **DNS only** | The satis Composer registry. **Every deploy on the fleet resolves this**, so it stays direct — no proxy in the deploy path. |
| `send`, `rsend` | ⚪ **DNS only** | Resend's SPF and bounce path. Cloudflare's proxy only handles HTTP/HTTPS; proxying these breaks SPF alignment and bounce handling, and all outbound mail starts failing DMARC. |
| MX ×2, 3× TXT | ⚪ n/a | Not proxiable. |

**SSL/TLS mode must stay Full (strict).** Flexible makes Cloudflare talk to the origin over plain
HTTP, which both is insecure and puts WordPress into an infinite redirect loop.

> ☠️ **Do NOT firewall the origin to Cloudflare IPs only.** Cloudflare's onboarding recommends it,
> and it would sever `packages.vincentragosta.io` — which is grey by design — from Composer on every
> droplet in the fleet. That advice assumes every record is proxied. Ours are not.

> ⚠️ **Cloudflare's import scan missed three records** on this zone: `packages`, `send` and `rsend`.
> Two of the three would have broken silently — deploys fleet-wide, and mail authentication. Treat
> the scan as a draft and diff it against the live zone record by record. Full method and the
> `itzenzo.tv` plan: `akivili/docs/cloudflare-dns-migration.md`.

> ⚠️ **MySQL root is unreachable on this droplet.** No passwordless socket auth, no `/root/.my.cnf`,
> and `/etc/mysql/debian.cnf` is the obsolete stub with no password (MariaDB 10.11). Per-site work
> still functions because each site's `wp-config-env.php` holds credentials with `ALL PRIVILEGES` on
> its own schema — that is how the `ellenharvey` database was dropped. But **creating a database, or
> dropping a MySQL user, currently has no working path.** Discovered 2026-08-28; unresolved.

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
2. **Mail: the CLI is not the web. Test as `www-data` or you will get a false green.**
   *(Trap rewritten 2026-08-28. It previously read "cannot send email … verified 2026-08-14,
   unfixed" — wrong. Then it was corrected to "works, wrong From" — also wrong, because that was
   tested over SSH as root.)*
   `msmtp-mta` relays through Gmail (`/etc/msmtprc`, account `itzenzottv@gmail.com`,
   `from noreply@itzenzo.tv`), installed 2026-05-11. **`/etc/msmtprc` shipped as mode `600`,
   owned by `root`.** PHP-FPM runs as `www-data`, so every *web-initiated* email failed with
   `sendmail: account default not found: no configuration file available` — while every
   `wp-cli`-over-SSH send succeeded, because root can read the file. Password resets, form
   notifications and admin notices were all silently dead; `wp_mail()` from the CLI returned `true`
   the whole time.
   **Fixed 2026-08-28** — `chgrp www-data /etc/msmtprc && chmod 640`, and the same for
   `/var/log/msmtp.log` at `660` so msmtp can append to its own log as `www-data`. msmtp refuses a
   config that is group- or world-*writable*; group-readable is fine. Verified end to end: the real
   `wp-login.php?action=lostpassword` POST now produces an `exitcode=EX_OK` msmtp entry, and a
   www-data-context test message reached the inbox.
   **Tradeoff, stated plainly:** the Gmail App Password is now readable by `www-data`, so a PHP RCE
   could read it. That is inherent to `msmtp` + PHP `mail()` (an SMTP plugin or a provider API key
   in `wp-config.php` has the same exposure). Rotate the App Password if this box is ever
   compromised.
   **Still wrong: the From address.** WordPress sends as `wordpress@vincentragosta.io`, which is not
   a verified Gmail "Send mail as" alias, so **Gmail rewrites it** — mail arrives as
   `WordPress <itzenzottv@gmail.com>`, with `X-Google-Original-From` preserving the original. It
   authenticates (`dkim/spf/dmarc=pass`, all on `gmail.com`) and inboxes, so nothing bounces. A
   password-reset link arriving from a stranger's Gmail reads as phishing — worth fixing. Verify
   `noreply@vincentragosta.io` as a Send-mail-as alias (improvmx MX receives the confirmation code),
   then set `wp_mail_from` / `wp_mail_from_name`. Do **not** make the global From
   `noreply@itzenzo.tv` — the Shop provider's `MailNotifications` sets that per-send on purpose.
   **How to test properly:**
   ```bash
   sudo -u www-data /usr/sbin/sendmail -t -i <<< $'To: you@example.com\nSubject: t\n\nbody'
   ```
   Never conclude mail works from a root `wp eval` — that is the one context that was working while
   the site was broken.
   **The Resend account** is Vincent's, registered to `ops@vincentragosta.io` (improvmx alias →
   his Gmail). Free tier is **3 domains / 3,000 a month / 100 a day, shared account-wide**. The
   domain cap is the binding constraint, not volume: `vincentragosta.io` uses one, and
   `ellenharvey.net` + `viewfromthebridgeplay.com` would take the other two. A fourth client means
   paying (~$20/mo) or that client owning their own account — decide before assuming a slot is free.
   Keys are named `<domain> — msmtp (droplet <ip>)` so a compromised key names the box to go fix.
   Ellen's is deliberately going in **her own** account, not this one.

   ⚠️ **This relay is personal-account infrastructure. Do not replicate it to client droplets** —
   see `akivili/docs/fleet-nginx-security-sweep.md` for why it fails outright on `ellenharvey.net`
   (`p=reject; aspf=s`) and `viewfromthebridgeplay.com` (no SPF, `p=quarantine`).
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
7. **`sites-enabled/vincentragosta.io` is a real file, not a symlink** — the only vhost on this box
   that isn't. Editing `sites-available/vincentragosta.io` changes a file nginx never reads:
   `nginx -t` passes, the reload succeeds, and nothing about the live site changes. This cost a full
   debug cycle on 2026-08-28. The two copies had also drifted (the live one carried two
   `wp-hardening.conf` includes the other lacked). They were re-synced that day, but they are still
   **two files that can drift again** — consider replacing the regular file with a symlink.
   Resolve before editing any vhost on any droplet: `readlink -f /etc/nginx/sites-enabled/<name>`.
   Six of eight ARTHOUSE vhosts have the same layout; `deploy-kit/provision/harden.sh` documents it.
   Prove the change landed with `curl`, not with `nginx -t`.

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
