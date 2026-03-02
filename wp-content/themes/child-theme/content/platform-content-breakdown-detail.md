# Platform — Full Page Content

> Reference copy for the dedicated `/platform` page. Each section maps to a content block on the page.

---

## Hero / Intro

### Headline
A WordPress framework built to ship faster — without cutting corners.

### Subheadline
Six years of refinement. Hundreds of hours of architecture decisions. One reusable platform that powers every project from day one — with strict types, automated testing, dependency injection, and accessibility baked into the foundation.

---

## Architecture Overview

### Headline
Modular by design.

### Body
The platform is built on a parent-child theme model. The parent theme provides reusable infrastructure — base classes, support managers, contracts, and shared features. The child theme extends it with site-specific functionality. Every new project starts with the same battle-tested foundation.

At the core is the **Provider pattern** — self-contained domains that own their PHP classes, assets, blocks, configuration, and tests. Similar to Laravel service providers, each provider is scoped to a feature and composes its own managers for assets, blocks, features, ACF fields, and REST endpoints. No deep inheritance chains, no tangled dependencies.

### Key Points
- **Parent-child theme model** — Infrastructure in the parent, specifics in the child. Clean separation that scales across projects.
- **Provider pattern** — Each provider is a self-contained domain owning its PHP, SCSS, JS, blocks, config, and tests.
- **Composition over inheritance** — Providers compose managers as internal collaborators rather than inheriting from a monolithic base.
- **Autowiring-first DI** — PHP-DI resolves dependencies automatically. Explicit definitions only where autowiring can't.

---

## Tech Stack

### Headline
Modern tooling. No legacy baggage.

### Body
Every dependency is chosen deliberately — nothing is included because "that's what WordPress projects use." The stack prioritizes type safety, fast builds, and developer confidence.

### Stack Items

| Tool | Purpose |
|------|---------|
| PHP 8.4+ | Strict types throughout — every file declares `strict_types=1` |
| Timber 2.x / Twig | Clean templating with separation of logic and presentation |
| PHP-DI 7.0 | Autowiring-first dependency injection — no service locators |
| esbuild + Sass | Fast, parallel asset compilation with watch mode |
| WordPress Block Editor | Custom dynamic blocks with React editor components and Twig server-side rendering |
| Advanced Custom Fields Pro | Structured content with JSON-synced field groups |
| DDEV | Containerized local development with database snapshots |

---

## Feature System

### Headline
Toggleable by default. Additive when it matters.

### Body
Functionality is organized into two categories — **Features** and **Hooks** — each with distinct inheritance rules that prevent accidental breakage across projects.

### Features (Toggleable)
Capabilities that child themes can opt in or out of with a single line. Disable comments, restrict block types, enable SVG uploads, activate scroll animations — all controlled declaratively. The DI container resolves each feature and autowires its dependencies automatically.

```
DisableComments::class,          // enabled
DisablePosts::class => false,    // opted out in child
ScrollReveal::class,             // opted in from parent
```

### Hooks (Always-Active)
Structural behavior that should never be accidentally disabled — icon enhancements, block style variants, social icon configurations. Inheritance is purely additive: parent and child hooks merge automatically with no opt-out syntax.

### Runtime Validation
Place a Feature in the hooks array or a Hook in the features array, and the system warns you at runtime and skips the class. Wrong marker interfaces are caught immediately — not silently ignored.

---

## Block Architecture

### Headline
Editor to frontend. One block, full stack.

### Body
Custom blocks are built as WordPress dynamic blocks with the full editor-to-frontend pipeline: React/JSX components for the block editor, PHP + Twig for server-side rendering on the frontend, and scoped SCSS for styling in both contexts.

### How a Block is Built

Each block lives inside its provider's `blocks/` directory with a predictable file structure:

- **`block.json`** — Standard WordPress block metadata. Declares the block name, category, supports, and rendering entry point.
- **`editor/`** — React/JSX components for the block editor. WordPress package imports (`@wordpress/blocks`, `@wordpress/element`) map to globals at build time — no bundling of WordPress internals.
- **`render.php`** — Server-side entry point. Receives block attributes, builds a Timber context, and renders a Twig template.
- **`style.scss`** — Block styles applied on both the frontend and within the editor.
- **`{name}.twig`** — The Twig template consumed by the renderer.
- **`view.js`** — Optional frontend interactivity (vanilla JS, no framework).

### ACF Block Support
Some blocks use Advanced Custom Fields for structured content entry — giving editors a familiar field-based interface while the developer retains full control over rendering.

---

## Build System

### Headline
One script. Zero duplication.

### Body
A single canonical build script lives in the parent theme. It auto-discovers every provider's assets and blocks, compiles them in parallel, and outputs production-ready CSS and JavaScript. The child theme invokes the same script — no copies, no drift.

### How It Works
- The script uses `process.cwd()` as the theme root, so it works for any theme that calls it.
- **Sass** compiles provider and block SCSS with configurable load paths — child themes can resolve parent SCSS partials (like breakpoints) without duplication.
- **esbuild** bundles JavaScript with ES2020 targeting, minification in production, and sourcemaps in development.
- WordPress editor packages are externalized — block scripts reference `wp.blocks`, `wp.element`, etc. as globals instead of shipping them in the bundle.

### Watch Mode
Incremental recompilation during development. The script watches provider `assets/` and `blocks/` directories and rebuilds only what changed.

### Output Mapping

| Source | Output |
|--------|--------|
| Provider SCSS (`assets/scss/index.scss`) | `dist/css/{slug}.css` |
| Provider JS (`assets/js/*.js`) | `dist/js/{slug}/*.js` |
| Block editor JS (`blocks/{name}/editor/index.js`) | `dist/js/{name}.js` |
| Block frontend style (`blocks/{name}/style.scss`) | `dist/css/{name}.css` |
| Block editor style (`blocks/{name}/editor/editor.scss`) | `dist/css/{name}-editor.css` |

---

## Testing

### Headline
Three layers. Full stack. Every commit.

### Body
Most WordPress projects ship with zero automated tests. This platform has 430+ PHP tests, JavaScript unit tests with DOM assertions, and end-to-end browser automation with automated accessibility audits.

### PHP — Unit & Integration
**PHPUnit 9 + WorDBless**

WorDBless loads WordPress without a database — tests execute in milliseconds, not seconds. Two suites cover different concerns: **Unit** tests are fully isolated with no WordPress side effects, while **Integration** tests exercise hooks, filters, and the full registration lifecycle.

Test directories mirror source structure exactly. A class at `src/Providers/Support/Asset/AssetManager.php` has tests at `tests/Unit/Providers/Support/Asset/AssetManagerTest.php`. The `HasContainer` trait builds a real DI container with autowiring for realistic test scenarios — no mocking the container itself.

### JavaScript — Unit & Integration
**Vitest + Testing Library**

Frontend JavaScript is tested in a jsdom environment using Vitest and Testing Library. Tests verify real DOM behavior: ARIA attribute toggling, keyboard navigation, focus management, animation class application, and data sorting.

The parent theme provides a shared base config and test setup file — including custom mocks for browser APIs that jsdom doesn't implement (IntersectionObserver with a `trigger()` helper, `window.matchMedia`). The child theme inherits this infrastructure and adds its own test files mirroring the provider directory structure.

**What's covered:**
- Dropdown component — ARIA toggles, keyboard navigation, focus wrapping, outside-click dismissal
- Header — light/dark mode toggle, localStorage persistence, overlay focus trapping
- Shutter cards block — card activation, keyboard interaction, animation class cycling
- Projects block — sort by title/date, ascending/descending direction

### End-to-End — Browser Automation & Accessibility
**Playwright + axe-core**

Playwright tests run against the live local development site in Chromium — real browser, real rendered HTML, real interactions.

- **Smoke tests** verify every page loads with expected structure and zero console errors.
- **Interaction tests** cover dark/light mode persistence across page reloads, mobile hamburger menu behavior, overlay focus trapping, and keyboard escape handling at a 375px mobile viewport.
- **Accessibility audits** run automated WCAG 2.1 AA checks on every page using axe-core. Any violation fails the build — accessibility isn't aspirational, it's enforced.

HTML reports are generated for debugging failures, with screenshots captured automatically when a test fails.

---

## Design System

### Headline
Tokens, not magic numbers.

### Body
The visual layer is built on CSS custom properties and Sass utilities — a small, deliberate system that adapts across light mode, dark mode, and high-contrast environments without duplicating styles.

### Key Points
- **CSS custom properties** as the shared interface — color tokens, spacing scale, and typography variables defined once and consumed everywhere.
- **Light/dark mode** via custom property swaps — one set of component styles, two visual modes.
- **Responsive breakpoints** at 576px, 768px, 992px, and 1440px with Sass mixins (`media()`, `media-down()`, `container()` for container queries).
- **Block style variants** — Editorial choices registered as WordPress block styles (Muted paragraphs, Animated covers, styled containers) that editors select from the block toolbar.

---

## Accessibility

### Headline
Built in. Not bolted on.

### Body
Accessibility is a structural concern, not a cleanup pass before launch. It's enforced at every layer — Sass utilities, template markup, JavaScript behavior, and automated testing.

### Key Points
- **WCAG utility mixins** — `visually-hidden`, `forced-colors`, `high-contrast`, `reduced-motion` — available to every SCSS file in the system.
- **Reduced motion support** — Scroll reveal animations respect `prefers-reduced-motion`. Users who opt out of motion see no animation, not broken animation.
- **Windows High Contrast** — Forced-colors media query support ensures interfaces remain usable in high-contrast environments.
- **Semantic markup** — Twig templates use proper heading hierarchy, landmark elements (`main`, `nav`, `article`), and ARIA attributes where semantics alone aren't sufficient.
- **Icon accessibility** — SVG icons are injected with `aria-hidden="true"` to prevent redundant screen reader announcements.
- **Automated audits** — Playwright + axe-core runs WCAG 2.1 AA checks against every page as part of the E2E test suite. Violations fail the build.

---

## Performance

### Headline
Fast by default. No optimization theatre.

### Body
Performance isn't a post-launch audit item — it's built into the asset pipeline, the enqueueing strategy, and the rendering approach.

### Key Points
- **Cache busting** via `filemtime()` — assets are versioned by their last modification time. No stale CSS or JS after deploys.
- **Resource hints** — `preconnect` directives for Google Fonts domains eliminate DNS lookup latency.
- **Silent asset failures** — If a CSS or JS file doesn't exist in `dist/`, the enqueue silently skips it. No PHP errors, no broken pages.
- **Intersection Observer** — Scroll reveal animations trigger only when elements enter the viewport. Nothing loads or animates off-screen.
- **No unnecessary bundling** — WordPress globals (`wp.blocks`, `wp.element`, React, ReactDOM) are externalized in block editor scripts. The platform doesn't ship what WordPress already provides.

---

## Security

### Headline
Hardened defaults. Not optional add-ons.

### Body
Security decisions are made once in the parent theme and inherited by every project. They're not configuration options — they're the baseline.

### Key Points
- **SVG sanitization** — Every SVG upload is run through `enshrined/svg-sanitize`, stripping scripts, event handlers, and XSS vectors before the file reaches the media library. Admin-only upload restriction adds an additional gate.
- **Block allowlist** — Only approved blocks are available in the editor. No embed blocks (XSS surface), no raw HTML block. The allowlist is managed as a Feature, so child themes can adjust it if needed.
- **Comments disabled** — The entire comment subsystem is removed: admin UI, menu items, frontend output, and the edit-comments redirect. Not hidden — removed.

---

## Developer Experience

### Headline
Convention-driven. Minimal ceremony.

### Body
Adding functionality to the platform follows predictable patterns. There's no setup wizard, no boilerplate generator — just conventions that the build system and container understand automatically.

### Key Points
- **Makefile commands** — `make start`, `make build`, `make test`, `make watch`, `make clean`. One command for every workflow, from local environment setup to full test runs.
- **PSR-4 autoloading** — Namespace matches directory path exactly. Add a PHP class, run `composer dump-autoload`, and it's available everywhere.
- **Auto-discovered assets** — Create a provider directory with an `assets/scss/index.scss` file, and the build system finds it on the next compile. No manifest to update, no config to edit.
- **Auto-discovered blocks** — Add a `blocks/{name}/` directory with a `block.json`, and the BlockManager registers it. The build script compiles its styles and editor scripts automatically.
- **Feature in one line** — Create a class implementing `Feature`, add it to the provider's `$features` array, and the container autowires its dependencies and registers it.
- **Watch mode** — Real-time SCSS and JS recompilation during development. Change a file, see the result.
