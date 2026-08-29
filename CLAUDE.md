# CLAUDE.md

> **Read [`RUNBOOK.md`](RUNBOOK.md) before any deploy, infra, cache, or seed work.**
> Do not rely on memory for hosts, paths, or commands. This file covers architecture and the
> Card Catalog Pipeline; the runbook covers environments, deploy, caches, and traps.

> **About this document.** A guide, not a contract — push back when a pattern here doesn't fit or there's a better approach. The architecture is still evolving; when a change shifts a convention, suggest updating this file, but never edit it automatically — prompt first.

## Project Overview

This is a WordPress site with three layers: **Mythus** (mu-plugin framework), a **parent theme**, and a **child theme**, all built on Timber 2.x and Twig for templating.

- **Mythus** (`wp-content/mu-plugins/mythus/`) — the platform framework. Owns the provider pattern, DI container (PHP-DI), contracts (`Registrable`, `Feature`, `Hook`, `Routable`), and all support managers (`AssetManager`, `BlockManager`, `FeatureManager`, `PatternManager`, `AcfManager`, `RestManager`). Theme-agnostic — no Timber/Twig coupling.
- **Parent theme** (`wp-content/themes/ix/`) — the Timber/Twig bridge layer. Extends `Mythus\Provider` with template resolution, Twig filter registration, and theme-specific path overrides. Provides reusable features and hooks.
- **Child theme** (`wp-content/themes/vincentragosta/`) — site-specific. Extends parent providers for the vincentragosta.io website.

The **Nous Discord bot** was **retired 2026-08-01** — decommissioned from the server (its `/opt/nous-bot/` service, bare repo at `/var/repo/Nous.git`, and nginx `/bot/` proxy were all removed) and archived. This project has no bot-related code. (The Discord *community* itself remains active.)

The **itzenzo.tv storefront** ([itzenzo.tv](https://github.com/vinnyrags/itzenzo.tv)) is a headless Next.js frontend that uses this WordPress instance as its backend. The ShopProvider registers product/card CPTs, REST endpoints (stock, **unified queue** data), and ACF field groups. The Stripe checkout/webhook and shipping-lookup routes were **removed 2026-08-01** (Stripe + ShippingEasy fully retired; `stripe/stripe-php` dropped from the theme). WPGraphQL + WPGraphQL for ACF expose product data, site settings, and the **live queue snapshot**. The shop page on vincentragosta.io 301-redirects to `https://itzenzo.tv`. The ShopProvider is headless-only — no frontend blocks, cart assets, or shop UI are rendered by WordPress.

The **unified queue** (orders, pack battles, pull boxes, request-to-see card requests) lives in WordPress as the source of truth. (Its former subscribers — the Nous bot and the itzenzo.tv homepage Live Queue — were both retired 2026-08-01; the queue data + REST/GraphQL surface remain.) See [Unified Queue](#unified-queue) below for the data model, REST surface, and GraphQL exposure.

- **PHP 8.4+** with strict types
- **PHP-DI 7.0** for dependency injection (autowiring-first, owned by Mythus)
- **Timber 2.x / Twig** for templating
- **DDEV** for local development
- **esbuild + sass** for asset compilation

## Architecture

### The Provider Pattern

Providers are the primary organizational unit. Each provider is a self-contained domain — it owns its PHP classes, assets (SCSS, JS), blocks, config files, and tests. Think of them like Laravel service providers scoped to a feature domain.

The hierarchy:

```
Mythus\Contracts\Registrable (interface)
  ├── Mythus\Contracts\Feature (marker) — toggleable, $features array, opt-out via => false
  ├── Mythus\Contracts\Hook (marker) — always-active, $hooks array, additive only
  └── Mythus\Provider (abstract base) — theme-agnostic framework
        └── IX\Providers\Provider (bridge) — adds Timber/Twig support
              ├── ThemeProvider — core theme setup, supports, global assets
              ├── PostTypeProvider — custom post types via JSON config
              └── ProjectProvider (child only) — projects CPT + block
```

The parent theme's `Provider` bridge extends `Mythus\Provider` and adds:
- Template directory resolution (`$templatesPath`, `getTemplatePath()`, `getTemplateSearchPaths()`)
- Twig filter registration (`addTwigFunctions()`, `maybeRegisterTwigFilter()`)
- Theme-aware path overrides (`getDistPath()`, `getDistUri()`, `resolveTextDomain()`)

Each provider composes managers rather than inheriting them:

- **AssetManager** (`Mythus\Support\Asset`) — enqueues CSS/JS from `dist/`, derives a kebab-case slug from the provider class name (e.g., `ThemeProvider` → `theme`, `PostTypeProvider` → `post-type`)
- **BlockManager** (`Mythus\Support\Block`) — registers dynamic blocks from the provider's `blocks/` directory
- **FeatureManager** (`Mythus\Support\Feature`) — resolves and registers toggleable `Feature` classes via the DI container
- **PatternManager** (`Mythus\Support\Pattern`) — auto-discovers and registers block patterns from the provider's `patterns/` directory
- **AcfManager** (`Mythus\Support\Acf`) — registers ACF JSON load/save paths and auto-discovers options pages
- **RestManager** (`Mythus\Support\Rest`) — registers REST API endpoints with toggleable enable/disable

Managers are instantiated in `Provider::setup()`, which runs lazily (idempotent, deferred until first access).

### Provider Registration Flow

```
functions.php → (new Theme())->bootstrap()
  → Timber::init()
  → registerAll($providers)
    → container->get(ThemeProvider::class)  // autowired
    → provider->register()
      → setup()            // creates managers, sets configPath
      → registerHooks()    // always-active structural behavior
      → registerFeatures() // toggleable features via FeatureManager
      → blockManager->initializeHooks()
      → patternManager->initializeHooks()
```

### Features vs Hooks

The codebase distinguishes two kinds of registrable classes:

- **Features** (in `$features` array, `Features/` directory) — toggleable capabilities that implement the `Feature` interface. Child providers can opt out via `ClassName::class => false`. Examples: `DisableComments`, `DisablePosts`, `EnableSvgUploads`.
- **Hooks** (in `$hooks` array, `Hooks/` directory) — always-active structural behavior that implements the `Hook` interface. Inheritance is additive only — no opt-out. Examples: `ButtonIconEnhancer`, `CoverBlockStyles`, `SocialIconChoices`.

### Feature Inheritance

Child providers extend parent providers using aliases. Features support opt-out via `=> false`:

```php
protected array $features = [
    DisablePosts::class => false,  // removes parent's DisablePosts
    NewChildFeature::class,
];
```

`collectFeatures()` walks the class hierarchy and merges parent + child features automatically. `FeatureManager` validates that classes in `$features` implement the `Feature` interface — plain `Registrable` classes are skipped with a warning.

### Hook Inheritance

Hooks use additive-only inheritance — no opt-out syntax:

```php
protected array $hooks = [
    ButtonIconEnhancer::class,
    CoverBlockStyles::class,
];
```

`collectHooks()` merges parent + child hooks and deduplicates. Hooks are resolved from the DI container and registered directly by the Provider (no Manager class needed).

## Design Patterns

**Three-layer architecture** — Mythus owns framework infrastructure (contracts, managers, DI). The parent theme bridges Mythus to Timber/Twig. The child theme is site-specific. Mythus has no Timber/Twig dependency.

**Composition over inheritance** — Providers compose AssetManager, BlockManager, FeatureManager, PatternManager, AcfManager, and RestManager as internal collaborators. The managers are not part of any inheritance chain.

**Constructors are for DI only** (injectables) — No initialization logic in constructors of container-managed classes (providers, services, features). Setup happens in `setup()` (manager creation), `register()` (hook binding), or `bootstrap()` (Theme entry point). This is especially important because `Theme` extends `Timber\Site`, and calling `init()` on it would collide with Timber's method. **Newables** — short-lived objects created via `new` with runtime parameters (e.g., `IconService` instances) — may do initialization work in their constructor, since the container doesn't create them.

**Features vs Hooks** — Features (in `$features` array, `Features/` directory) are toggleable — child providers can opt out via `ClassName::class => false`. They implement `Mythus\Contracts\Feature`. Hooks (in `$hooks` array, `Hooks/` directory) are always-active structural behavior — inheritance is additive only, no opt-out. They implement `Mythus\Contracts\Hook`. Both extend `Mythus\Contracts\Registrable`. Placing a class in the wrong array triggers a runtime warning.

**Autowiring-first** — PHP-DI (owned by Mythus) resolves everything by default. To add explicit definitions when autowiring can't figure it out, override `getContainerDefinitions()` in Theme.php and create a `src/Config/container.php` file that returns an array of definitions.

**Lazy initialization** — `Provider::setup()` is idempotent and defers manager setup until actually needed. Multiple calls are safe.

**Silent asset failures** — If a CSS or JS file doesn't exist in `dist/`, the enqueue call silently skips it. This prevents errors when a provider has PHP logic but no compiled assets.

**Infrastructure in Mythus, bridge in parent, specifics in child** — Mythus contains no theme-specific code. The parent theme adds Timber/Twig integration. All website functionality lives in the child theme.

## Directory Structure

### Provider Layout

```
src/Providers/{Name}/
├── {Name}Provider.php     # Provider class
├── Features/              # Toggleable Feature classes (opt-out via => false)
│   ├── DisableComments.php
│   └── EnableSvgUploads.php
├── Hooks/                 # Always-active Registrable classes (additive only)
│   ├── ButtonIconEnhancer.php
│   └── CoverBlockStyles.php
├── assets/
│   ├── js/                # Provider-scoped scripts
│   └── scss/
│       └── index.scss     # Entry point for provider CSS
├── blocks/
│   └── {block-name}/
│       ├── block.json     # Block metadata
│       ├── render.php     # Server-side rendering
│       ├── style.scss     # Block styles (frontend + editor)
│       ├── view.js        # Optional frontend interactivity
│       ├── {name}.twig    # Twig template for render.php
│       └── editor/        # React/JSX editor UI
│           ├── index.js
│           ├── edit.js
│           └── editor.scss
├── patterns/              # Block patterns (auto-discovered by PatternManager)
│   └── {pattern-name}.php # Pattern file with WP header (Title, Slug, Categories)
└── config/
    └── post-type.json     # JSON config loaded via loadConfig()
```

### Mythus Layout

```
wp-content/mu-plugins/
├── mythus-loader.php            # Root loader (fail-fast if vendor missing)
└── mythus/
    ├── mythus.php               # Plugin header, constants
    ├── composer.json             # Owns php-di/php-di ^7.0
    ├── phpunit.xml
    ├── src/
    │   ├── Provider.php          # Abstract base (theme-agnostic)
    │   ├── Contracts/
    │   │   ├── Registrable.php
    │   │   ├── Feature.php
    │   │   ├── Hook.php
    │   │   └── Routable.php
    │   ├── Support/
    │   │   ├── AbstractRegistry.php
    │   │   ├── Acf/AcfManager.php
    │   │   ├── Asset/AssetManager.php
    │   │   ├── Block/BlockManager.php
    │   │   ├── Feature/FeatureManager.php
    │   │   ├── Pattern/PatternManager.php
    │   │   └── Rest/{RestManager,Endpoint}.php
    │   └── Hooks/
    │       └── BlockStyles.php   # Abstract declarative block style registration
    └── tests/
        ├── bootstrap.php
        ├── Support/              # HasContainer trait, ACF mocks
        └── Unit/Support/         # Manager + contract tests
```

### Key Conventions

- Assets live inside providers, never at the theme root
- `dist/` is git-ignored and rebuilt via `npm run build`
- Mythus tests mirror source: `tests/Unit/Support/{Acf,Asset,Block,Feature,Pattern,Rest}/`
- Theme tests mirror providers: `tests/Unit/Providers/`, `tests/Integration/Providers/`
- PSR-4 autoloading: namespace path matches directory path exactly
- Contracts and support managers live in Mythus (`Mythus\Contracts\*`, `Mythus\Support\*`), not in the themes

## Block Architecture

Blocks are WordPress dynamic blocks with server-side rendering:

1. **`block.json`** — standard WordPress block metadata, references `render.php`
2. **`editor/`** — React/JSX components for the block editor. WordPress package imports (`@wordpress/blocks`, etc.) map to globals at build time via esbuild externals
3. **`render.php`** — receives `$attributes` and `$content`, builds a Timber context, renders a Twig template
4. **`style.scss`** — block styles for both frontend and editor
5. **`{name}.twig`** — Twig template consumed by `render.php`

Blocks are declared in the provider's `$blocks` array and live in the provider's `blocks/` subdirectory. The BlockManager handles registration and hook wiring.

## Block Patterns

Block patterns are **authored in the CMS** (WordPress block editor) as synced patterns (`wp_block` post type), then exported to PHP files via the `make pull-patterns` command. They are not created or edited locally.

### Workflow

1. **Create or edit patterns** in the WordPress editor on production or staging
2. **Export** with `make pull-patterns` (production) or `make pull-patterns-staging` (staging)
3. **Review and commit** the generated PHP files

### Provider Routing

The export script routes patterns to provider directories based on their CMS category:

- A pattern with the `project` category → `src/Providers/Project/patterns/`
- A pattern with the `theme` category → `src/Providers/Theme/patterns/`
- The category slug is converted to PascalCase and matched against `src/Providers/{PascalCase}/`
- **Unmatched categories** fall back to the theme-root `patterns/` directory, where WordPress auto-discovers them

### Registration

Each provider gets a **PatternManager** instance during `setup()`. If the provider has a `patterns/` directory, the manager hooks into `init` and registers all `.php` files found there. Pattern files are self-describing via standard WordPress file headers (`Title`, `Slug`, `Categories`, etc.) — no explicit `$patterns` array on the provider is needed.

### Pattern File Format

```php
<?php
/**
 * Title: Hero Section
 * Slug: vincentragosta/hero-section
 * Categories: project
 * Inserter: true
 */
?>
<!-- Block markup here -->
```

The export script automatically replaces hardcoded upload URLs with dynamic `content_url()` calls so media references work across environments.

## Unified Queue — *parked*

The Shop provider owns a single ledger of every "thing waiting to happen on stream" — orders, pack battle entries, pull box entries, and request-to-see card requests. WP custom tables (`wp_queue_sessions`, `wp_queue_entries`) are the source of truth; all access goes through `Support/QueueRepository.php`; REST endpoints live under `/wp-json/shop/v1/queue/*` (bot-secret auth via `X-Bot-Secret`); WPGraphQL exposes `liveQueue`. The change-broadcast to Nous (SSE) was **removed 2026-08-01** with the bot's retirement (its former consumers — the Discord `/queue` command and the itzenzo.tv homepage Live Queue — are both gone); the queue data + REST/GraphQL surface remain, **parked post-Whatnot-pivot**. Full data model, REST surface, producers, speculative shipping, and test map: [docs/unified-queue.md](docs/unified-queue.md).

## Card Catalog Pipeline — *retired 2026-08-16*

**The online shop is no longer operating. All live commerce is on Whatnot, and that pipeline does
not touch WordPress at all** — it runs Google Sheet → Whatnot import CSV, entirely inside the
`nous` repo (`build-whatnot.mjs` reads the "Product Pricing" sheet directly).

Removed from this repo: the eight Makefile sections that made up the Sheet → WP → itzenzo.tv
pipeline (`Card singles`, `Card sync`, `Card create`, `Card price sync`, `Product price/stock sync`,
`Product create`, `Card image migration`, `Whatnot CSV pipeline`) — roughly 60 targets — plus their
18 supporting scripts and the `NOUS_DIR`/`NOUS_SHOP` plumbing that shelled into nous. The matching
47 scripts were removed from `nous` in the same pass.

**What remains and still works:**

- The `card` and `product` **custom post types, their ACF fields, and all existing data are
  untouched.** itzenzo.tv keeps reading them over WPGraphQL and renders exactly as before — the
  catalog is simply frozen at its last sync rather than gone.
- `Shop` provider code (`CardPost`, `CardRepository`, `ProductPost`, the REST endpoints) is
  unchanged; it serves reads.
- `stripe_product_id` / `stripe_price_id` postmeta survive as inert historical identifiers.

**If the shop is ever revived**, this is a rebuild, not a revert: the sheet schema, the join-key
model, and the failure modes worth designing around are written up in
`akivili/docs/catalog-sync-lessons.md`. Git history holds the old implementation.

## Caching — two layers

Production runs **both** an nginx FastCGI page cache and a Redis object cache. They are
**complementary, not alternatives**: the page cache stores finished HTML for anonymous GETs; the
object cache stores query results for everything the page cache deliberately skips.

**Staging has neither.** It is a content and feature environment — determinism there is worth more
than mirroring production's performance profile.

### FastCGI micro-cache (added 2026-08-28, production only)

Zone `VINRAG`, **30s TTL**, maps in `conf.d/wp-fastcgi-cache.conf`. Short TTL by design: content
changes appear on their own, so there is no purge step and no stale-content class of bug. Inspect
with the `X-FastCGI-Cache` header (HIT / MISS / BYPASS / EXPIRED).

Skipped: POST/PUT/DELETE, `/wp-admin/`, `wp-*.php`, feeds, sitemaps, **any query string**, and the
`wordpress_logged_in` / `comment_author` / `wp-postpass` cookies.

> **Why Redis is still needed alongside it.** The skip map opens with `POST 1`, and **WPGraphQL lives
> at `/wp/graphql` and is queried over POST by itzenzo.tv** — so that traffic can never be
> page-cached, no matter how the cache is tuned. Redis is the only layer serving it.

> **A missing `X-FastCGI-Cache` header is not a missing skip.** POST responses carry no header at
> all. Prove behaviour with two different POSTs returning two different responses.

### Redis object cache — production only

| Env | Redis DB | `WP_CACHE_KEY_SALT` | State |
|---|---|---|---|
| Production WP | 0 | `vincentragosta_` | **active** |
| Staging WP | — | — | **removed 2026-08-28**, deliberately |

> ☠️ **An earlier version of this table claimed staging used db 1 with salt
> `staging_vincentragosta_`. Neither constant was ever defined in staging's `wp-config-env.php`** —
> while the redis-cache plugin *was* active there. So staging connected to the default database:
> **db 0, production's, with no salt separating them.**
>
> Not theoretical: on 2026-08-28 a `wp cache flush` run against *staging* flushed **production's**
> object cache, 5,629 keys down to 35. Harmless in itself, but it proves staging could read and
> write production's cached objects.
>
> **If Redis is ever wanted on staging again, define `WP_REDIS_DATABASE` and `WP_CACHE_KEY_SALT`
> FIRST, then enable the drop-in.** Drop-in first means production collision.

**Daemon config** (`/etc/redis/redis.conf`): `maxmemory 256mb`, `maxmemory-policy allkeys-lru`, `save ""` (pure cache, no disk persistence). Reasoning: 256 MB is generous for our cache size (~5–10 MB warm), LRU evicts oldest keys under pressure so Redis can never OOM the box, and disabling save snapshots removes disk I/O we don't need from a pure cache.

**Plugin**: [redis-cache](https://wordpress.org/plugins/redis-cache/) (Till Krüss). Activates `wp-content/object-cache.php` as a drop-in. Toggle with `wp redis enable` / `wp redis status` / `wp redis disable`. The drop-in overrides `wp_cache_flush()`, so every existing call to `wp cache flush` (including the one in the deploy hook) clears Redis along with in-memory caches.

**`wp-config-env.php`** carries `WP_REDIS_DATABASE` and `WP_CACHE_KEY_SALT` — **production only**.

> **`WP_CACHE` is not defined anywhere**, contrary to an earlier version of this file. That is
> correct and should stay that way: `WP_CACHE` gates *page* caching via `advanced-cache.php`, not the
> object cache, which loads regardless. Do not "fix" it by defining it.

**Invalidation contract.** ACF, WPGraphQL, Yoast, and anything else that uses standard WP APIs (`update_post_meta`, `update_option`, `set_transient`) honor the cache lifecycle correctly — the cache busts on writes automatically. `audit-stripe-active.js --apply` uses `wp post meta update` (WP API), so its writes are safe.

**Two scenarios that bypass the cache** — both currently safe in this codebase, but watch for future code:
1. **Raw `$wpdb` writes** — `Hooks/QueueRepository` and the pull-box repository write queue/pull-box state directly via `$wpdb`. Reads go through raw `$wpdb` too, so net-neutral. If new code reads queue data via `get_post_meta()` or similar WP APIs, those reads could be stale; route them through the existing repository helpers instead.
2. **Direct DB mutations from SSH/wp-cli** — manual `wp db query "UPDATE wp_postmeta SET ..."` does not invalidate Redis. Run `wp cache flush` after.

## Build System

A single `build-providers.js` script lives in `ix/scripts/`. It auto-discovers all providers with assets or blocks and compiles them.

The child theme runs the same script: `node ../ix/scripts/build-providers.js`. The script uses `process.cwd()` as the theme root, so it works for any theme that invokes it.

**Output mapping:**
- Provider SCSS: `src/Providers/{Name}/assets/scss/index.scss` → `dist/css/{slug}.css`
- Provider JS: `src/Providers/{Name}/assets/js/*.js` → `dist/js/{slug}/*.js`
- Block editor JS: `blocks/{name}/editor/index.js` → `dist/js/{name}.js`
- Block frontend style: `blocks/{name}/style.scss` → `dist/css/{name}.css`
- Block editor style: `blocks/{name}/editor/editor.scss` → `dist/css/{name}-editor.css`

**Theme-specific SCSS config** is optional via `scripts/build-providers.config.js`, which can export `sassLoadPaths` (extra directories for the Sass compiler). The child uses this so block and provider SCSS files can resolve `@use 'common/breakpoints' as *;` against the parent theme's SCSS directory. Every SCSS file that uses breakpoint mixins must include the explicit `@use` — there is no auto-injection.

## Commands

From the project root, run `make help` for the full list of targets, grouped
by section. The list is auto-generated from `## description` annotations on
each target in the `Makefile`, so it stays in sync with what's actually
available — when you add a new target, give it a `## description` and a
section header (`##@ Section Name`) and it'll show up automatically.

Common targets: `make start`, `make watch`, `make build`, `make test`,
`make stop`, `make install`, `make autoload`, `make update`, `make clean`,
`make release`. Environment sync: `make push-staging` / `pull-staging` /
`push-production` / `pull-production`. Block patterns and Stripe products
have their own sections in the help output.

From a theme directory:

| Command | What it does |
|---------|-------------|
| `composer test` | Run that theme's PHPUnit tests |
| `composer dump-autoload` | Regenerate PSR-4 autoloader (after adding/moving classes) |
| `npm run build` | Compile that theme's assets |
| `npm run start` | Watch mode for that theme |
| `npm run test:js` | Run JavaScript unit tests (Vitest) |
| `npm run test:js:watch` | Vitest in watch mode |
| `npm run test:js:coverage` | Vitest with coverage report (child theme) |
| `npm run test:e2e` | Run Playwright E2E tests (child theme, requires DDEV) |
| `npm run test:e2e:headed` | Playwright with visible browser |
| `npm run test:e2e:report` | Open Playwright HTML report |

## Testing

The codebase has three layers of automated testing: PHP unit/integration tests, JavaScript unit tests, and end-to-end browser tests with accessibility audits.

### PHP Tests (PHPUnit 9 + WorDBless)

New PHP code should include tests where applicable. **WorDBless** is a WordPress test harness that loads WordPress without a database for fast, isolated execution. Tests run with `make test` from the project root or `composer test` from the Mythus directory or either theme directory.

#### Structure

There are three PHP test suites, each with Unit and Integration directories:

- **Mythus** (`mu-plugins/mythus/tests/`) — tests for contracts, support managers (Asset, Block, Feature, Pattern, Acf, Rest), and abstract base classes. These test the framework in isolation from any theme.
- **Parent theme** (`themes/ix/tests/`) — tests for the Provider bridge, theme-level features, hooks, and integration tests for the registration lifecycle.
- **Child theme** (`themes/vincentragosta/tests/`) — tests for site-specific providers, custom post types, and child-specific behavior.

Test directories mirror source structure. A class at `Mythus\Support\Asset\AssetManager` has tests at `mythus/tests/Unit/Support/Asset/AssetManagerTest.php`. A class at `IX\Providers\Theme\ThemeProvider` has tests at `ix/tests/Unit/Providers/Theme/ThemeProviderTest.php`.

#### Conventions

- Test classes extend `WorDBless\BaseTestCase`
- Both Mythus and the parent theme have a `HasContainer` trait (`tests/Support/HasContainer.php`) that provides `buildTestContainer()` for tests needing DI
- Tests are method-per-behavior, named `test{Behavior}` (e.g., `testSlugifyRemovesProviderSuffix`)
- New support managers or contracts go in Mythus with corresponding Mythus tests
- New theme features/hooks go in the appropriate theme with corresponding theme tests
- Run `composer dump-autoload` in the relevant directory if tests can't find new classes

### JavaScript Tests (Vitest + Testing Library)

Frontend JavaScript is tested with **Vitest** using a **jsdom** environment and **Testing Library** (`@testing-library/dom`, `@testing-library/jest-dom`) for accessible, user-centric assertions. Run with `npm run test:js` from either theme directory.

#### Structure

JS tests live at `tests/js/` and mirror the provider directory structure:

```
tests/js/Providers/
├── Theme/
│   ├── assets/js/
│   │   ├── dropdown.test.js    # ARIA toggles, keyboard nav, focus management
│   │   └── header.test.js      # Light/dark mode, overlay, focus trapping
│   └── blocks/
│       └── shutter-cards/
│           └── view.test.js    # Card activation, keyboard, animation classes
└── Project/
    └── blocks/
        └── projects/
            └── view.test.js    # Sort by title/date, ascending/descending
```

#### Conventions

- The parent theme provides a shared base config (`scripts/vitest.base.config.js`) and test setup file (`scripts/test-setup.js`) — the child theme inherits both
- The setup file mocks browser APIs not available in jsdom: `window.matchMedia` and `IntersectionObserver` (with a `trigger()` helper for testing intersection behavior)
- Cleanup runs after each test: DOM reset, localStorage clear, document class removal
- Test files use inline fixture builders (e.g., `createDropdown()`, `createShutterCards(N)`) to construct DOM structures

### End-to-End Tests (Playwright + axe-core)

The child theme includes **Playwright** browser automation tests that run against the live DDEV local site. Run with `npm run test:e2e` from the child theme directory (requires DDEV to be running).

#### Structure

E2E tests live at `tests/e2e/`:

- **`smoke.test.js`** — verifies every page loads with expected structure and zero console errors
- **`header.test.js`** — dark/light mode persistence across reloads, mobile hamburger menu, overlay focus trapping, keyboard escape handling
- **`accessibility.test.js`** — automated WCAG 2.1 AA audits on every page via `@axe-core/playwright`

#### Conventions

- Config at `playwright.config.js`: Chromium only, fully parallel, 2 retries on CI
- Screenshots captured on failure, HTML reports generated in `tests/e2e/report/`
- Accessibility tests assert zero violations against `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` tags
- Mobile viewport tests use 375x812 for responsive behavior

### When to Write Tests

- New Mythus support classes (managers, contracts, abstract bases) — always, in Mythus test suite
- New feature classes — when they contain logic beyond simple hook registration
- New frontend JavaScript with DOM manipulation or user interaction — Vitest + Testing Library
- Bug fixes — a regression test that reproduces the bug before the fix
- Refactors that change behavior boundaries — verify the new boundaries
- New pages or major UI changes — add E2E smoke tests and accessibility audits

Tests aren't expected for pure WordPress hook wiring (e.g., a feature that only calls `add_filter`), Twig templates, or SCSS/JS assets without interactive behavior.

## Naming Conventions

- **Provider classes**: `{Name}Provider` — not `ServiceProvider`, not `{Name}Service`
- **Provider directories**: PascalCase (`Theme/`, `PostType/`, `Project/`)
- **Asset slugs**: kebab-case, derived from class name minus "Provider" suffix (`ThemeProvider` → `theme`)
- **Block directories**: kebab-case (`shutter-cards/`, `projects/`)
- **PSR-4**: namespace matches directory path — `ChildTheme\Providers\Project\ProjectProvider` lives at `src/Providers/Project/ProjectProvider.php`
- **Child extends parent**: use alias pattern (`ThemeProvider as BaseThemeProvider`), call `parent::register()` at the end of the child's `register()` method

## Common Pitfalls

These are patterns the codebase has evolved away from. Avoid reintroducing them:

- **Assets at theme root** — All assets live inside their provider's `assets/` or `blocks/` directory, not in a top-level `assets/` folder
- **Hardcoded parent paths in child** — The build script uses `process.cwd()` and reflection-based paths. Don't hardcode `get_template_directory()` where `get_stylesheet_directory()` is correct (or vice versa)
- **Duplicated build scripts** — There is one canonical `build-providers.js` in the parent theme. The child runs it, it doesn't copy it
- **Initialization in constructors** — Constructors take DI parameters only. Setup logic goes in `setup()`, `register()`, or `bootstrap()`
- **Explicit container definitions for autowirable classes** — If PHP-DI can resolve it automatically, don't add a definition
- **Calling `init()` on Theme** — The entry point is `bootstrap()`. Using `init()` conflicts with `Timber\Site::init()`
- **`new` for feature classes** — Features are resolved through the container via `FeatureManager`. This enables autowiring of their dependencies
- **Hooks in `$features`** — Always-active behavioral classes (icon enhancers, block styles) belong in `$hooks`, not `$features`. `FeatureManager` validates this at runtime and skips non-`Feature` classes with a warning
- **Wrong marker interface** — Feature classes implement `Mythus\Contracts\Feature`, hook classes implement `Mythus\Contracts\Hook`. Both extend `Mythus\Contracts\Registrable`. Placing a `Feature` in `$hooks` or a `Hook` in `$features` triggers a runtime warning and the class is skipped
- **Contracts or managers in the parent theme** — All contracts (`Registrable`, `Feature`, `Hook`, `Routable`) and support managers (`AssetManager`, `BlockManager`, etc.) live in Mythus, not in the parent theme. The parent theme only has the `Provider` bridge class
- **PHP-DI in theme composer.json** — PHP-DI is owned by Mythus. Don't add `php-di/php-di` to either theme's `composer.json`
- **Timber/Twig in Mythus** — Mythus is theme-agnostic. Don't add Timber, Twig, or template-related code to Mythus. That belongs in the parent theme's Provider bridge
- **Missing Mythus vendor** — The `mythus-loader.php` will `wp_die()` with instructions if `composer install` hasn't been run in the Mythus directory. Run `make install` to install all dependencies
- **Stale IX/Mythus vendor after `composer update`** — When you run `composer update vincentragosta/ix` (or `vincentragosta/mythus`) from the project root, Composer extracts the new package contents into `wp-content/themes/ix/` (or `wp-content/mu-plugins/mythus/`) but does NOT run `composer install` *inside* that directory. The package's own `vendor/` is left in a partial-extract state — autoload files (`autoload_psr4.php` etc.) are missing — which breaks PHPUnit (the `IX\Tests\Support\HasContainer` trait fails to autoload) and any code path that touches the package's own dependencies. The root `composer.json` `post-install-cmd` and `post-update-cmd` scripts handle this automatically by running `@composer install --working-dir=wp-content/{mu-plugins/mythus,themes/ix}` after every parent install/update. If you ever see PHPUnit blow up with `Trait "IX\Tests\Support\HasContainer" not found`, the autoload didn't refresh — re-run `composer install` from the project root, or run the nested install manually: `composer install --working-dir=wp-content/themes/ix`
- **Direct DB writes from SSH leave Redis stale** — `wp db query "UPDATE wp_postmeta ..."` and similar bypass `WP_Object_Cache` and don't trigger invalidation. After any direct DB mutation via SSH, run `wp cache flush --path=$WP_PATH --allow-root` so Redis re-reads from MySQL on the next request. Code that uses standard WP APIs (`update_post_meta`, `update_option`) is unaffected; this only matters for raw SQL.
- **`apt install php-*` can swap PHP CLI version under you** — installing PHP packages (e.g. `php-redis`) pulls in the latest PHP as a transitive dependency and updates `/etc/alternatives/php` to point at it. PHP-FPM keeps running 8.4 (correct), but `wp` CLI starts using whatever the alternative now resolves to — which may lack `mysqli` and break wp-cli with a confusing "MySQL extension missing" error (the real failure is the wrong CLI version, not a missing extension). Fix: `update-alternatives --set php /usr/bin/php8.4`. Always check `php --version` after any `apt` operation that touches PHP packages.
