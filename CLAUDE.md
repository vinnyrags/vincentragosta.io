# CLAUDE.md

> **About this document.** This file is a guide, not a contract. It documents how things work and why they ended up that way, but nothing here is beyond question. If a pattern described below doesn't fit the problem at hand, or if there's a better approach, say so — pushing back on assumptions in this document is encouraged. The architecture is still evolving, and this file should evolve with it. When a change to the codebase shifts a pattern or introduces a new convention, suggest updating CLAUDE.md to reflect it. Never update this file automatically — always prompt first so the change can be reviewed and discussed.

## Project Overview

This is a WordPress parent-child theme built on Timber 2.x and Twig for templating. The parent theme (`wp-content/themes/parent-theme`) provides reusable infrastructure — base classes, support managers, contracts, and shared features. The child theme (`wp-content/themes/child-theme`) is site-specific and extends the parent for the vincentragosta.io website.

- **PHP 8.4+** with strict types
- **PHP-DI 7.0** for dependency injection (autowiring-first)
- **Timber 2.x / Twig** for templating
- **DDEV** for local development
- **esbuild + sass** for asset compilation

## Architecture

### The Provider Pattern

Providers are the primary organizational unit. Each provider is a self-contained domain — it owns its PHP classes, assets (SCSS, JS), blocks, config files, and tests. Think of them like Laravel service providers scoped to a feature domain.

The hierarchy:

```
Registrable (interface)
  ├── Feature (marker) — toggleable, $features array, opt-out via => false
  ├── Hook (marker) — always-active, $hooks array, additive only
  └── Provider (abstract base)
        ├── ThemeProvider — core theme setup, supports, global assets
        ├── PostTypeProvider — custom post types via JSON config
        └── ProjectProvider (child only) — projects CPT + block
```

Each provider composes three managers rather than inheriting them:

- **AssetManager** — enqueues CSS/JS from `dist/`, derives a kebab-case slug from the provider class name (e.g., `ThemeProvider` → `theme`, `PostTypeProvider` → `post-type`)
- **BlockManager** — registers dynamic blocks from the provider's `blocks/` directory
- **FeatureManager** — resolves and registers toggleable `Feature` classes via the DI container

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

**Composition over inheritance** — Providers compose AssetManager, BlockManager, and FeatureManager as internal collaborators. The managers are not part of any inheritance chain.

**Constructors are for DI only** (injectables) — No initialization logic in constructors of container-managed classes (providers, services, features). Setup happens in `setup()` (manager creation), `register()` (hook binding), or `bootstrap()` (Theme entry point). This is especially important because `Theme` extends `Timber\Site`, and calling `init()` on it would collide with Timber's method. **Newables** — short-lived objects created via `new` with runtime parameters (e.g., `IconService` instances) — may do initialization work in their constructor, since the container doesn't create them.

**Features vs Hooks** — Features (in `$features` array, `Features/` directory) are toggleable — child providers can opt out via `ClassName::class => false`. They implement the `Feature` interface. Hooks (in `$hooks` array, `Hooks/` directory) are always-active structural behavior — inheritance is additive only, no opt-out. They implement the `Hook` interface. Both `Feature` and `Hook` extend `Registrable`. Placing a class in the wrong array triggers a runtime warning.

**Autowiring-first** — PHP-DI's autowiring resolves everything by default. To add explicit definitions when autowiring can't figure it out, override `getContainerDefinitions()` in Theme.php and create a `src/Config/container.php` file that returns an array of definitions.

**Lazy initialization** — `Provider::setup()` is idempotent and defers manager setup until actually needed. Multiple calls are safe.

**Silent asset failures** — If a CSS or JS file doesn't exist in `dist/`, the enqueue call silently skips it. This prevents errors when a provider has PHP logic but no compiled assets.

**Infrastructure in parent, specifics in child** — The parent theme contains no site-specific code. All website functionality lives in the child theme.

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
└── config/
    └── post-type.json     # JSON config loaded via loadConfig()
```

### Key Conventions

- Assets live inside providers, never at the theme root
- `dist/` is git-ignored and rebuilt via `npm run build`
- Tests mirror source structure: `tests/Unit/Providers/Support/{Asset,Block,Feature}/`
- PSR-4 autoloading: namespace path matches directory path exactly
- Support classes live at `src/Providers/Support/{Asset,Block,Feature}/` — each has a Manager class

## Block Architecture

Blocks are WordPress dynamic blocks with server-side rendering:

1. **`block.json`** — standard WordPress block metadata, references `render.php`
2. **`editor/`** — React/JSX components for the block editor. WordPress package imports (`@wordpress/blocks`, etc.) map to globals at build time via esbuild externals
3. **`render.php`** — receives `$attributes` and `$content`, builds a Timber context, renders a Twig template
4. **`style.scss`** — block styles for both frontend and editor
5. **`{name}.twig`** — Twig template consumed by `render.php`

Blocks are declared in the provider's `$blocks` array and live in the provider's `blocks/` subdirectory. The BlockManager handles registration and hook wiring.

## Build System

A single `build-providers.js` script lives in `parent-theme/scripts/`. It auto-discovers all providers with assets or blocks and compiles them.

The child theme runs the same script: `node ../parent-theme/scripts/build-providers.js`. The script uses `process.cwd()` as the theme root, so it works for any theme that invokes it.

**Output mapping:**
- Provider SCSS: `src/Providers/{Name}/assets/scss/index.scss` → `dist/css/{slug}.css`
- Provider JS: `src/Providers/{Name}/assets/js/*.js` → `dist/js/{slug}/*.js`
- Block editor JS: `blocks/{name}/editor/index.js` → `dist/js/{name}.js`
- Block frontend style: `blocks/{name}/style.scss` → `dist/css/{name}.css`
- Block editor style: `blocks/{name}/editor/editor.scss` → `dist/css/{name}-editor.css`

**Theme-specific SCSS config** is optional via `scripts/build-providers.config.js`, which can export `sassLoadPaths` (extra directories for the Sass compiler). The child uses this so block and provider SCSS files can resolve `@use 'common/breakpoints' as *;` against the parent theme's SCSS directory. Every SCSS file that uses breakpoint mixins must include the explicit `@use` — there is no auto-injection.

## Commands

From the project root:

| Command | What it does |
|---------|-------------|
| `make start` | Start DDEV, restore latest DB snapshot, install deps, build assets |
| `make stop` | Snapshot database and stop DDEV |
| `make install` | Install composer + npm dependencies for both themes |
| `make build` | Build child theme assets (runs parent build first) |
| `make watch` | Start watch mode for development |
| `make test` | Run PHPUnit test suites for both themes |
| `make update` | Update composer dependencies (root + both themes) |
| `make clean` | Remove vendor, node_modules, and dist from both themes |
| `make autoload` | Regenerate composer autoloaders for both themes |
| `make push-staging` | Push local DDEV database + uploads to staging server |
| `make pull-staging` | Pull staging database + uploads to local DDEV |
| `make push-production` | Push local DDEV database + uploads to production server |
| `make pull-production` | Pull production database + uploads to local DDEV |

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

New PHP code should include tests where applicable. **WorDBless** is a WordPress test harness that loads WordPress without a database for fast, isolated execution. Tests run with `make test` from the project root or `composer test` from either theme directory.

#### Structure

Tests are organized into two suites:

- **Unit** (`tests/Unit/`) — isolated tests with no WordPress side effects. Most manager and utility tests live here.
- **Integration** (`tests/Integration/`) — tests that exercise WordPress hooks, filters, or the registration lifecycle.

Test directories mirror source structure. A class at `src/Providers/Support/Asset/AssetManager.php` has tests at `tests/Unit/Providers/Support/Asset/AssetManagerTest.php`.

#### Conventions

- Test classes extend `WorDBless\BaseTestCase`
- The `HasContainer` trait (`tests/Support/HasContainer.php`) provides `buildTestContainer()` for tests that need DI — it builds a real container with autowiring and optional definition overrides
- Tests are method-per-behavior, named `test{Behavior}` (e.g., `testSlugifyRemovesProviderSuffix`)
- When adding a new feature class, manager method, or provider behavior, include corresponding tests
- Run `composer dump-autoload` in the theme directory if tests can't find new classes

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

- New support classes (managers, services, utilities) — always
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
- **Wrong marker interface** — Feature classes implement `Feature`, hook classes implement `Hook`. Both extend `Registrable`. Placing a `Feature` in `$hooks` or a `Hook` in `$features` triggers a runtime warning and the class is skipped
