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
  └── Provider (abstract base)
        ├── ThemeProvider — core theme setup, supports, global assets
        ├── PostTypeProvider — custom post types via JSON config
        └── ProjectProvider (child only) — projects CPT + block
```

Each provider composes three managers rather than inheriting them:

- **AssetManager** — enqueues CSS/JS from `dist/`, derives a kebab-case slug from the provider class name (e.g., `ThemeProvider` → `theme`, `PostTypeProvider` → `post-type`)
- **BlockManager** — registers dynamic blocks from the provider's `blocks/` directory
- **FeatureManager** — resolves and registers small `Registrable` feature classes via the DI container

Managers are instantiated in `Provider::init()`, which runs lazily (idempotent, deferred until first access).

### Provider Registration Flow

```
functions.php → (new Theme())->bootstrap()
  → Timber::init()
  → registerAll($providers)
    → container->get(ThemeProvider::class)  // autowired
    → provider->register()
      → init()          // creates managers, sets configPath
      → registerFeatures()  // FeatureManager resolves + registers all
      → blockManager->initializeHooks()
```

### Feature Inheritance

Child providers extend parent providers using aliases:

```php
use ParentTheme\Providers\Theme\ThemeProvider as BaseThemeProvider;

class ThemeProvider extends BaseThemeProvider
{
    protected array $features = [
        ButtonIconEnhancer::class,
        CoverBlockStyles::class,
    ];
}
```

The child only declares its own features. `collectFeatures()` walks the class hierarchy and merges parent + child features automatically. To opt out of a parent feature:

```php
protected array $features = [
    DisablePosts::class => false,  // removes parent's DisablePosts
    NewChildFeature::class,
];
```

## Design Patterns

**Composition over inheritance** — Providers compose AssetManager, BlockManager, and FeatureManager as internal collaborators. The managers are not part of any inheritance chain.

**Constructors are for DI only** — No initialization logic in constructors. Setup happens in `init()` (manager creation), `register()` (hook binding), or `bootstrap()` (Theme entry point). This is especially important because `Theme` extends `Timber\Site`, and calling `init()` on it would collide with Timber's method.

**Autowiring-first** — Container definition files (`src/config/container.php`) exist in both themes but stay empty. PHP-DI's autowiring resolves everything. Only add explicit definitions when autowiring can't figure it out.

**Lazy initialization** — `Provider::init()` is idempotent and defers manager setup until actually needed. Multiple calls are safe.

**Silent asset failures** — If a CSS or JS file doesn't exist in `dist/`, the enqueue call silently skips it. This prevents errors when a provider has PHP logic but no compiled assets.

**Infrastructure in parent, specifics in child** — The parent theme contains no site-specific code. All website functionality lives in the child theme.

## Directory Structure

### Provider Layout

```
src/Providers/{Name}/
├── {Name}Provider.php     # Provider class
├── README.md              # Documents the provider
├── Features/              # Small Registrable classes
│   ├── DisableComments.php
│   └── EnableSvgUploads.php
├── assets/
│   ├── js/                # Provider-scoped scripts
│   └── scss/
│       └── index.scss     # Entry point for provider CSS
├── blocks/
│   └── {block-name}/
│       ├── block.json     # Block metadata
│       ├── editor/        # React/JSX editor UI
│       │   ├── index.js
│       │   ├── edit.js
│       │   └── editor.scss
│       ├── frontend/      # Server-side rendering
│       │   ├── render.php
│       │   ├── style.scss
│       │   └── view.js    # Optional frontend interactivity
│       └── templates/     # Twig templates for render.php
│           └── grid.twig
└── config/
    └── post-type.json     # JSON config loaded via loadConfig()
```

### Key Conventions

- Assets live inside providers, never at the theme root
- `dist/` is git-ignored and rebuilt via `npm run build`
- Tests mirror source structure: `tests/Unit/Providers/Support/{Asset,Block,Feature}/`
- PSR-4 autoloading: namespace path matches directory path exactly
- Support classes live at `src/Providers/Support/{Asset,Block,Feature}/` — each has a Manager class and README

## Block Architecture

Blocks are WordPress dynamic blocks with server-side rendering:

1. **`block.json`** — standard WordPress block metadata, references `render.php`
2. **`editor/`** — React/JSX components for the block editor. WordPress package imports (`@wordpress/blocks`, etc.) map to globals at build time via esbuild externals
3. **`frontend/render.php`** — receives `$attributes` and `$content`, builds a Timber context, renders a Twig template
4. **`frontend/style.scss`** — block styles for both frontend and editor
5. **`templates/`** — Twig templates consumed by `render.php`

Blocks are declared in the provider's `$blocks` array and live in the provider's `blocks/` subdirectory. The BlockManager handles registration and hook wiring.

## Build System

A single `build-providers.js` script lives in `parent-theme/scripts/`. It auto-discovers all providers with assets or blocks and compiles them.

The child theme runs the same script: `node ../parent-theme/scripts/build-providers.js`. The script uses `process.cwd()` as the theme root, so it works for any theme that invokes it.

**Output mapping:**
- Provider SCSS: `src/Providers/{Name}/assets/scss/index.scss` → `dist/css/{slug}.css`
- Provider JS: `src/Providers/{Name}/assets/js/*.js` → `dist/js/{slug}/*.js`
- Block editor JS: `blocks/{name}/editor/index.js` → `dist/js/{name}.js`
- Block frontend style: `blocks/{name}/frontend/style.scss` → `dist/css/{name}.css`
- Block editor style: `blocks/{name}/editor/editor.scss` → `dist/css/{name}-editor.css`

**Theme-specific SCSS config** is optional via `scripts/build-providers.config.js`, which can export `sassImports` (prepended to block SCSS) and `sassLoadPaths` (extra directories for the Sass compiler). The child uses this to share the parent's breakpoints.

## Commands

From the project root:

| Command | What it does |
|---------|-------------|
| `make install` | Install composer + npm dependencies for both themes |
| `make build` | Build child theme assets (runs parent build first) |
| `make watch` | Start watch mode for development |
| `make test` | Run PHPUnit test suite for both themes |
| `make clean` | Remove vendor, node_modules, and dist from both themes |
| `make autoload` | Regenerate composer autoloaders for both themes |

From a theme directory:

| Command | What it does |
|---------|-------------|
| `composer test` | Run that theme's PHPUnit tests |
| `composer dump-autoload` | Regenerate PSR-4 autoloader (after adding/moving classes) |
| `npm run build` | Compile that theme's assets |
| `npm run start` | Watch mode for that theme |

## Testing

New PHP code should include tests where applicable. The codebase uses **PHPUnit 9** with **WorDBless** (a WordPress test harness that loads WordPress without a database). Tests run with `make test` from the project root or `composer test` from either theme directory.

### Structure

Tests are organized into two suites:

- **Unit** (`tests/Unit/`) — isolated tests with no WordPress side effects. Most manager and utility tests live here.
- **Integration** (`tests/Integration/`) — tests that exercise WordPress hooks, filters, or the registration lifecycle.

Test directories mirror source structure. A class at `src/Providers/Support/Asset/AssetManager.php` has tests at `tests/Unit/Providers/Support/Asset/AssetManagerTest.php`.

### Conventions

- Test classes extend `WorDBless\BaseTestCase`
- The `HasContainer` trait (`tests/Support/HasContainer.php`) provides `buildTestContainer()` for tests that need DI — it builds a real container with autowiring and optional definition overrides
- Tests are method-per-behavior, named `test{Behavior}` (e.g., `testSlugifyRemovesProviderSuffix`)
- When adding a new feature class, manager method, or provider behavior, include corresponding tests
- Run `composer dump-autoload` in the theme directory if tests can't find new classes

### When to Write Tests

- New support classes (managers, services, utilities) — always
- New feature classes — when they contain logic beyond simple hook registration
- Bug fixes — a regression test that reproduces the bug before the fix
- Refactors that change behavior boundaries — verify the new boundaries

Tests aren't expected for pure WordPress hook wiring (e.g., a feature that only calls `add_filter`), Twig templates, or SCSS/JS assets.

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
- **Initialization in constructors** — Constructors take DI parameters only. Setup logic goes in `init()`, `register()`, or `bootstrap()`
- **Explicit container definitions for autowirable classes** — If PHP-DI can resolve it automatically, don't add a definition. The container config files should stay minimal
- **Calling `init()` on Theme** — The entry point is `bootstrap()`. Using `init()` conflicts with `Timber\Site::init()`
- **`new` for feature classes** — Features are resolved through the container via `FeatureManager`. This enables autowiring of their dependencies
