# vincentragosta.io

WordPress site with a three-layer architecture: a mu-plugin framework ([Mythus](https://github.com/vinnyrags/mythus)), a parent theme ([IX](https://github.com/vinnyrags/IX)), and a child theme (this project), all built on Timber 2.x / Twig with PHP-DI for dependency injection.

- **[Mythus](https://github.com/vinnyrags/mythus)** (`wp-content/mu-plugins/mythus/`) — theme-agnostic platform framework. Owns the provider pattern, DI container, contracts, and support managers.
- **[IX](https://github.com/vinnyrags/IX)** (`wp-content/themes/ix/`) — Timber/Twig bridge parent theme. Extends Mythus with template resolution and reusable blog/project infrastructure.
- **Child theme** (`wp-content/themes/vincentragosta/`) — site-specific. Extends IX providers for the vincentragosta.io website.

The **[Nous Discord bot](https://github.com/vinnyrags/Nous)** (order notifications, pack battles, card shop) and the **[itzenzo.tv storefront](https://github.com/vinnyrags/itzenzo.tv)** (headless Next.js frontend) live in separate repositories. This site acts as their WordPress backend — the Shop provider registers the `product` and `card` CPTs, the card singles taxonomies (`card_game`, `card_set`), Stripe checkout/webhook endpoints, the unified queue (`wp_queue_sessions` / `wp_queue_entries`) that backs orders, pack battles, pull boxes, and "Request to See" submissions, and ACF field groups that the bot and storefront consume.

## Prerequisites

- PHP 8.4+ with strict types
- WordPress 6.0+
- Node.js 18+
- Composer
- DDEV (local development)

## Setup

```bash
make start     # start DDEV, restore DB snapshot, install deps, build assets
```

## Commands

Run `make help` for the full list of Make targets, grouped by section (local
development, deploy, DB sync, Stripe products, card singles, etc.). Each
target's description is generated from its Makefile annotation, so this list
stays in sync automatically.

Day-to-day:

```bash
make start    # start DDEV, restore DB snapshot, install deps, build assets
make watch    # rebuild assets on change
make build    # one-shot build (parent theme then child)
make test     # PHP + JS suites across Mythus, IX, and child
make stop     # snapshot DB and stop DDEV
```

From a theme directory: `composer test`, `npm run build`, `npm run start`.

---

## Architecture

### Bootstrap

```
functions.php → (new Theme())->bootstrap()
  → Timber::init()
  → registerAll($providers)
    → container->get(ThemeProvider::class)  // autowired via PHP-DI
    → provider->register()
      → setup()            // creates managers, sets configPath
      → registerHooks()    // always-active structural behavior
      → registerFeatures() // toggleable features via FeatureManager
      → blockManager->initializeHooks()
      → patternManager->initializeHooks()
```

### The Provider Pattern

Providers are the primary organizational unit. Each provider is a self-contained domain — it owns its PHP classes, assets (SCSS, JS), blocks, config files, and tests. They're like Laravel service providers scoped to a feature domain.

```
Registrable (interface)
  ├── Feature (marker) — toggleable, $features array, opt-out via => false
  ├── Hook (marker) — always-active, $hooks array, additive only
  └── Provider (abstract base)
        ├── ThemeProvider — core theme setup, supports, global assets
        ├── PostTypeProvider — custom post types via JSON config
        └── ProjectProvider (child only) — projects CPT + block
```

Each provider composes six managers (created in `setup()`):

| Manager | Purpose |
|---------|---------|
| **AssetManager** | Enqueues CSS/JS from `dist/`, derives kebab-case slug from class name |
| **BlockManager** | Registers dynamic blocks from the provider's `blocks/` directory |
| **FeatureManager** | Resolves and registers toggleable `Feature` classes via the DI container |
| **PatternManager** | Auto-discovers and registers block patterns from the provider's `patterns/` directory |
| **AcfManager** | ACF JSON sync paths and options page auto-discovery |
| **RestManager** | REST API endpoint registration with inheritance and opt-out |

### Features vs Hooks

- **Features** (`$features` array, `Features/` directory) — toggleable, implement `Feature` interface. Child providers can opt out via `ClassName::class => false`.
- **Hooks** (`$hooks` array, `Hooks/` directory) — always-active, implement `Hook` interface. Additive only — no opt-out.

Both extend `Registrable`. Placing a class in the wrong array triggers a runtime warning and the class is skipped.

### Dependency Injection

PHP-DI 7.0 with autowiring-first. The container is built in `Theme::__construct()` and stored as a static instance. Providers receive `Container` via constructor injection. Explicit definitions go in `src/Config/container.php` when autowiring can't resolve a dependency.

### Directory Structure

```
ix/
├── src/
│   ├── Models/              # Post, Image, CropDirection
│   ├── Providers/
│   │   ├── Contracts/       # Registrable, Feature, Hook, Routable
│   │   ├── Support/         # AssetManager, BlockManager, FeatureManager, PatternManager, AcfManager, RestManager
│   │   ├── Theme/           # ThemeProvider + Features/ + Hooks/
│   │   └── PostType/        # PostTypeProvider (JSON-based CPT registration)
│   ├── Repositories/        # Repository base class + interface
│   ├── Services/            # IconServiceFactory, SvgSanitizerService
│   └── Theme.php            # Base theme class (extends Timber\Site)
├── scripts/
│   └── build-providers.js   # Canonical build script (child theme runs this too)
└── tests/

vincentragosta/             # Child theme (wp-content/themes/vincentragosta/)
├── src/
│   ├── Providers/
│   │   ├── Theme/           # ThemeProvider + Features/ + Hooks/ + blocks/
│   │   ├── Project/         # ProjectProvider + blocks/ + config/ + acf-json/
│   │   ├── Blog/            # Child BlogProvider + NousSignal* hooks + blocks/
│   │   └── Shop/            # Shop REST endpoints, ACF, Stripe integration (headless)
│   ├── Services/            # Child-specific services
│   └── Theme.php            # Extends parent Theme
├── views/                   # Twig templates (base, header, footer, pages)
├── theme.json               # Block editor design tokens
└── tests/
```

Provider layout:

```
src/Providers/{Name}/
├── {Name}Provider.php
├── Features/              # Toggleable Feature classes
├── Hooks/                 # Always-active Hook classes
├── assets/
│   ├── js/
│   └── scss/
│       └── index.scss     # Entry point
├── blocks/
│   └── {block-name}/
│       ├── block.json     # Block metadata
│       ├── render.php     # Server-side rendering
│       ├── style.scss     # Block styles (frontend + editor)
│       ├── view.js        # Optional frontend interactivity
│       ├── {name}.twig    # Twig template
│       └── editor/        # React/JSX editor UI
│           ├── index.js
│           ├── edit.js
│           └── editor.scss
├── patterns/              # Block patterns (auto-discovered by PatternManager)
│   └── {pattern-name}.php # Pattern file with WP headers (Title, Slug, Categories)
├── config/
│   └── post-type.json     # JSON config loaded via loadConfig()
└── acf-json/
    ├── group_*.json        # ACF field groups (auto-loaded)
    └── options-page-*.json # Options pages (auto-registered)
```

---

## Support Managers

### AssetManager

Handles path resolution and WordPress enqueuing for a provider's compiled assets.

| Method | Description |
|--------|-------------|
| `enqueueStyle($handle, $filename, $deps)` | Enqueue stylesheet from `dist/css/` |
| `enqueueScript($handle, $filename, $deps, $inFooter)` | Enqueue script from `dist/js/{slug}/` |
| `enqueueDistStyle($handle, $path, $deps)` | Enqueue stylesheet from any `dist/` path |
| `enqueueDistScript($handle, $path, $deps, $inFooter)` | Enqueue script from any `dist/` path |
| `enqueueManifestScript($handle, $path, $extraDeps, $inFooter)` | Enqueue script using `.asset.php` manifest for deps/version |
| `hasStyle($filename)` / `hasScript($filename)` | Check if file exists in `dist/` |
| `getStyleUri($filename)` / `getScriptUri($filename)` | Get URI to a file |
| `slugify(string $className)` | Static. `ThemeProvider` → `theme`, `PostTypeProvider` → `post-type` |

All enqueue methods skip silently if the file doesn't exist. Version cache-busting uses `filemtime()`. Providers expose wrapper methods that delegate to AssetManager.

### BlockManager

Handles block registration and editor asset hooks.

| Method | Description |
|--------|-------------|
| `registerBlocks()` | Register all blocks via `register_block_type` |
| `enqueueEditorScript($handle, $filename, $deps)` | Enqueue block editor script from `dist/js/` |
| `initializeHooks($provider)` | Wire up `init`, `enqueue_block_assets`, `enqueue_block_editor_assets` |

Default editor script dependencies: `wp-blocks`, `wp-element`, `wp-block-editor`, `wp-components`, `wp-i18n`, `wp-data`.

Providers declare blocks via `$blocks` property and override `enqueueBlockAssets()` / `enqueueBlockEditorAssets()`.

### FeatureManager

Resolves and registers `Feature` classes from the DI container with inheritance support.

| Method | Description |
|--------|-------------|
| `registerAll()` | Resolve each enabled feature, validate `Feature` interface, call `register()` |
| `isEnabled(string $class)` | Check if a feature is enabled |
| `getEnabled()` / `getDisabled()` | Get enabled/disabled class lists |
| `normalize(array $items)` | Static. Convert mixed array to `[class => bool]` map |

How it works: `Provider::collectFeatures()` walks the class hierarchy via reflection, normalizes each level's `$features` array, and merges bottom-up so child entries override parent entries.

### AcfManager

ACF JSON sync and options page auto-discovery.

| Method | Description |
|--------|-------------|
| `hasAcfJson()` | Whether provider has an `acf-json/` directory |
| `initializeHooks()` | Register load path filter and options page discovery |
| `registerSavePath()` | Set this provider as the ACF JSON save target |
| `registerOptionsPages()` | Discover and register `options-page-*.json` files |

Options page JSON files use the same keys as `acf_add_options_page()`. Include `parent_slug` for sub-pages.

### PatternManager

Auto-discovers and registers block patterns from a provider's `patterns/` directory.

| Method | Description |
|--------|-------------|
| `hasPatterns()` | Whether provider has a `patterns/` directory |
| `getPatternsPath()` | Get the patterns directory path |
| `initializeHooks()` | Hook `registerPatterns` to `init` if directory exists; silent no-op otherwise |
| `registerPatterns()` | Discover files, parse headers, register via `register_block_pattern()` |

Pattern files are self-describing via standard WordPress file headers — no explicit `$patterns` array on the provider is needed. If a `.php` file is in `patterns/`, it gets registered.

#### Block Patterns Workflow

Patterns are **authored in the CMS** (WordPress block editor) as synced patterns, then exported to PHP files. They are not created or edited locally.

1. **Create or edit patterns** in the WordPress editor on production or staging
2. **Export** with `make pull-patterns` (production) or `make pull-patterns-staging` (staging)
3. **Review and commit** the generated PHP files

The export script routes patterns to provider directories based on their CMS category:

- A pattern with the `project` category → `src/Providers/Project/patterns/`
- A pattern with the `theme` category → `src/Providers/Theme/patterns/`
- Category slug is converted to PascalCase and matched against `src/Providers/{PascalCase}/`
- Unmatched categories fall back to theme-root `patterns/` (WordPress auto-discovery)

Hardcoded upload URLs are automatically replaced with dynamic `content_url()` calls so media references work across environments.

### RestManager

REST API endpoint registration with inheritance and opt-out.

| Method | Description |
|--------|-------------|
| `registerAll()` | Resolve endpoints from container and call `register_rest_route()` |
| `getNamespace()` | Get REST namespace (defaults to `{provider-slug}/v1`) |

Endpoints extend the abstract `Endpoint` class with four required methods:

| Method | Description |
|--------|-------------|
| `getRoute()` | Route pattern (e.g., `/health`, `/items/(?P<id>\d+)`) |
| `getMethods()` | HTTP method(s) |
| `callback($request)` | Handle request, return response |
| `getPermission($request)` | Permission check (must be explicit — no default) |

---

## Models

### Post

Extends `Timber\Post` with convenience methods.

```php
$post->title();              // Post title
$post->url();                // Permalink
$post->publishedDate();      // DateTime
$post->modifiedDate();       // DateTime
$post->isPublished();        // bool
$post->isDraft();            // bool
$post->getMeta('key');       // Meta value (ACF-aware via getField() fallback)
$post->setMeta('key', 'v'); // Set meta
$post->hasTerm('news', 'category');  // Check term
$post->refresh();            // Re-fetch from database
```

Child themes extend Post for custom post types (e.g., `ProjectPost` with `client()`, `role()`, `year()`, `technologies()`, `externalUrl()`).

### Image

Extends `Timber\Image` with a fluent resize/crop API. When class map is active, `post.thumbnail` returns an `Image` instance.

```php
$image->resize(800, 600);
$image->setWidth(400)->crop(CropDirection::CENTER);
$image->setSize('thumbnail');
$image->setLazy(false);
$image->setAttr('class', 'hero-image');
```

Twig: `{{ post.thumbnail.resize(800, 600).crop }}`

`CropDirection` is a backed string enum: `NONE`, `CENTER`, `TOP`, `BOTTOM`, `LEFT`, `RIGHT`.

---

## Repositories

Query layer over Timber/WP_Query with an expressive API.

```php
$repo->find(123);                    // By ID
$repo->findBySlug('hello-world');    // By slug
$repo->findOne([...]);               // Single match
$repo->findMany([1, 2, 3]);          // Multiple (preserves order)
$repo->all(limit: 10);              // All posts
$repo->latest(5);                    // Latest N
$repo->byAuthor($userId);           // By author
$repo->drafts();                     // Draft posts
$repo->whereMetaEquals('k', 'v');    // By meta
$repo->whereTerm('category', 'news'); // By taxonomy term
$repo->whereTermIds([1,2], 'cat');   // By term IDs
$repo->count([...]);                 // Count
$repo->exists([...]);                // Existence check
$repo->query([...]);                 // Custom WP_Query args
$repo->save($post);                 // Insert or update
$repo->delete($post);               // Delete
```

Repositories auto-exclude the current post on singular views (override with `$excludeCurrentPost = false`). Extend for custom post types by setting `protected string $model = ProjectPost::class`.

---

## Services

| Class | Purpose |
|-------|---------|
| `IconServiceFactory` | Factory for `IconService` instances. Methods: `create()`, `all()`, `options()`, `contentMap()` |
| `SvgSanitizerService` | Wraps `enshrined/svg-sanitize` for SVG upload security |

Services are autowired — declare as constructor parameters, no container definitions needed.

---

## Parent Theme Features

### ThemeProvider

Registers theme supports: `automatic-feed-links`, `title-tag`, `post-thumbnails`, `menus`, `html5`, `editor-styles`, `wp-block-styles`, `layout`, `custom-spacing`, `align-wide`.

Class map (`timber/post/classmap`): `post` → `Post`, `page` → `Post`, image attachments → `Image`.

### DisableBlocks

Removes blocks and embed variations from the editor. Filterable via `theme/disabled_block_types` and `theme/disabled_embed_variations`.

Default disabled: template/site blocks, query/loop blocks, post blocks, comment blocks, widget blocks, layout blocks (`spacer`, `separator`, `nextpage`, `more`), content blocks (`cover`, `media-text`, `verse`, `details`, `quote`, `freeform`). All embed providers except YouTube.

### DisableComments

Removes comment support from all post types, closes comments/pings, removes admin menu entry, redirects edit-comments.php.

### DisablePosts

Hides the Posts menu from the WordPress admin.

### EnableSvgUploads

Adds `svg`/`svgz` to allowed mime types with sanitization (removes `<script>` elements, event handlers, `javascript:` URLs).

---

## Child Theme

### ThemeProvider

Extends parent ThemeProvider. Adds:

**Hooks (always-active):**
- `AccordionIconEnhancer` — Replaces default +/- text in `core/accordion-heading` with SVG icon
- `ButtonIconEnhancer` — Adds icon picker to `core/button` (stores `selectedIcon` + `iconPosition` attributes, injects SVG on render)
- `CoverBlockStyles` — Registers "Animated" style for `core/cover`
- `SocialIconChoices` — Custom social icon options

**Blocks:**
- `child-theme/shutter-cards` — Container for interactive expanding cards
- `child-theme/shutter-card` — Individual card within the container

**Assets:** `theme.css` (frontend + editor), JS modules for dropdown, header, button, scroll animation, frontend initialization.

### ProjectProvider

**Post type:** `project` (registered from `config/post-type.json`, `has_archive: false`)

**Model:** `ProjectPost` extends `Post` with: `client()`, `role()`, `year()`, `technologies()`, `technologyList()`, `externalUrl()`, `hasProjectDetails()`, `relatedProjects()`.

**Block:** `child-theme/projects` — Asymmetric grid of project posts with three display modes:
- **Latest** — 5 most recent projects
- **Curated** — Hand-picked via ACF relationship field
- **All** — Every project with sort dropdown and scroll-reveal animation

The projects archive is CMS-managed: a WordPress Page at `/projects/` uses the Projects block in "All" mode.

---

## Custom Blocks

### Shutter Cards (`child-theme/shutter-cards`)

Container block for interactive cards that expand/collapse with a "shutter" effect.

**Grid layout** uses a 10-column CSS Grid system:
- 4 cards: Row 1 = 40%/60%, Row 2 = 60%/40%
- 5 cards: Row 1 = ~33% each, Row 2 = 60%/40%

**Responsive:** Desktop = grid with active/inactive states. Mobile = single column accordion. Uses CSS container queries (`container: shutter-cards / inline-size`).

**Preload:** Renders with `shutter-cards--preload` class that hides content until JS initializes (prevents layout shift).

Supports: `align` (wide, full), `spacing` (padding, margin).

### Shutter Card (`child-theme/shutter-card`)

Individual card inside Shutter Cards. Attributes: `title`, `subtitle`, `description`, `cardIndex`.

**States:** Active (`.is-active` — full background, content visible) and inactive (`.is-inactive` — muted, content faded/collapsed, toggle rotated as "+").

**Accessibility:** `aria-expanded` on toggle, `role="button"` + `tabindex="0"` on inactive cards, keyboard nav (Enter/Space), `prefers-reduced-motion` support.

Supports light mode via `.light-mode` ancestor class.

### Projects (`child-theme/projects`)

Asymmetric project grid. Alternating 35%/65% layout via CSS Grid `nth-child` selectors on a 10-column system. Three modes (latest/curated/all) controlled by ACF `display_mode` field. "All" mode includes a sort dropdown and scroll-reveal (IntersectionObserver).

---

## Design System

Design tokens defined in `theme.json`, available as CSS custom properties:

**Colors:** `--wp--preset--color--{base, contrast, accent-1, accent-2, grey-2}`

**Spacing (8px grid):**

| Token | Value | Use |
|-------|-------|-----|
| `--spacing--20` | 0.5rem (8px) | Tight |
| `--spacing--30` | 1rem (16px) | Small |
| `--spacing--40` | 1.5rem (24px) | Medium |
| `--spacing--50` | 2.5rem (40px) | Large |
| `--spacing--60` | 4rem (64px) | Section gaps |
| `--spacing--70` | 10rem (160px) | Page-level gaps |

**Typography:** Roobert (body), Reckless Neue (headings), Fira Code (monospace).

**Container query breakpoints:** `sm` (576px), `md` (768px), `lg` (992px), `xl` (1440px) — via `@include container(name, breakpoint)` mixin. Requires explicit `@use 'common/breakpoints' as *;` in each SCSS file.

**BEM methodology:** `.block__element--modifier`.

---

## Build System

Single canonical `build-providers.js` in `ix/scripts/`. Auto-discovers providers with assets or blocks. Child theme runs the same script (`node ../ix/scripts/build-providers.js`); script uses `process.cwd()` as theme root.

**Output mapping:**

| Source | Output |
|--------|--------|
| `src/Providers/*/assets/scss/index.scss` | `dist/css/{slug}.css` |
| `src/Providers/*/assets/js/*.js` | `dist/js/{slug}/*.js` |
| `blocks/*/editor/index.js` | `dist/js/{block}.js` |
| `blocks/*/view.js` | `dist/js/{block}-view.js` |
| `blocks/*/style.scss` | `dist/css/{block}.css` |
| `blocks/*/editor/editor.scss` | `dist/css/{block}-editor.css` |

Slug derived from class name minus "Provider" suffix: `ThemeProvider` → `theme`.

Optional `scripts/build-providers.config.js` exports `sassLoadPaths` (child config adds parent SCSS directory for shared breakpoint mixins).

---

## Testing

PHPUnit 9 with WorDBless (WordPress without a database). Tests mirror source structure.

```bash
make test              # Both themes
composer test          # Single theme (from theme directory)
composer test:unit     # Unit tests only
composer test:integration  # Integration only
```

**Structure:**

```
tests/
├── Unit/              # Isolated tests, no WordPress side effects
│   ├── Models/
│   ├── Providers/Support/  # AssetManager, BlockManager, FeatureManager tests
│   ├── Repositories/
│   └── Services/
├── Integration/       # Tests with WordPress hooks/filters
│   ├── Providers/
│   └── ThemeTest.php
└── Support/
    └── HasContainer.php    # buildTestContainer() for tests needing DI
```

---

## Contributing

See [CLAUDE.md](CLAUDE.md) for conventions, design patterns, naming rules, and common pitfalls.

## License

MIT
