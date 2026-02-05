# Parent/Child Theme Refactor Plan

This document outlines the plan to separate core WordPress infrastructure into a reusable parent theme, keeping site-specific code in the vincentragosta child theme.

## Overview

**Goal:** Create a clean separation between:
- **Parent Theme** - Reusable WordPress/Timber infrastructure
- **Child Theme** - Site-specific content, blocks, and styling

---

## Files to Move to Parent Theme

### PHP Source (`src/`)

| File | Description |
|------|-------------|
| `Contracts/Registrable.php` | Base interface for registrable classes |
| `Contracts/HasAssets.php` | Interface for classes that enqueue assets |
| `Traits/HasAssets.php` | Default implementation for asset enqueueing |
| `Providers/ServiceProvider.php` | Abstract base class using HasAssets trait |
| `Providers/AssetServiceProvider.php` | Generic frontend/editor asset enqueueing |
| `Providers/PostTypeServiceProvider.php` | Generic custom post type registration |
| `Providers/TwigServiceProvider.php` | Timber/Twig setup and custom functions |
| `Providers/ThemeService/Provider.php` | Core theme supports (title-tag, thumbnails, etc.) |
| `Providers/ThemeService/Features/DisableComments.php` | Reusable feature to disable comments |
| `Theme.php` | Base theme bootstrap class (child will extend) |

### Twig Templates (`views/`)

| File | Description |
|------|-------------|
| `base.twig` | Base HTML structure with block regions |
| `head.twig` | HTML head section with WP hooks |
| `index.twig` | Default post listing template |
| `archive.twig` | Archive page template |
| `author.twig` | Author archive template |
| `search.twig` | Search results template |
| `404.twig` | 404 error page template |
| `comment.twig` | Single comment display |
| `comment-form.twig` | Comment form template |
| `single-password.twig` | Password-protected post template |
| `page-plugin.twig` | Plugin page template |
| `tease.twig` | Generic post teaser |
| `tease-post.twig` | Post-specific teaser |
| `menu.twig` | Navigation menu template |
| `sidebar.twig` | Sidebar template |
| `partial/pagination.twig` | Pagination component |

### Root PHP Files

| File | Description |
|------|-------------|
| `functions.php` | Timber initialization and theme bootstrap |
| `header.php` | WP header template wrapper |
| `footer.php` | WP footer template wrapper |
| `sidebar.php` | WP sidebar template wrapper |
| `archive.php` | Archive template delegator |
| `author.php` | Author template delegator |
| `search.php` | Search template delegator |
| `single.php` | Single post template delegator |
| `page.php` | Page template delegator |
| `404.php` | 404 template delegator |
| `index.php` | Main template fallback |

### SCSS Assets (`src/Providers/Theme/assets/scss/`)

| Directory/File | Description |
|----------------|-------------|
| `common/_breakpoints.scss` | Responsive breakpoint variables and mixins |
| `common/_function.scss` | SCSS utility functions (fluid typography, etc.) |
| `common/_animation.scss` | Base keyframe animations |
| `common/_layout.scss` | Core layout structure (.site-main, alignments) |
| `utilities/_display.scss` | Display utility classes |
| `utilities/_text.scss` | Text utility classes |
| `elements/_headings.scss` | Base heading styles |
| `elements/_block-text.scss` | Base text block styles |

### Build Configuration

| File | Description |
|------|-------------|
| `composer.json` | PHP dependencies (Timber, etc.) |
| `package.json` | NPM dependencies and build scripts |
| `webpack.config.js` | Webpack configuration for blocks |
| `scripts/build-providers.js` | Provider asset auto-discovery (SCSS → CSS, JS → JS) |
| `scripts/build-block-views.js` | Block view.js auto-discovery compiler |

---

## Files to Keep in Child Theme

### PHP Source (`src/`)

| File | Description |
|------|-------------|
| `Theme.php` | Extends parent Theme, registers child-specific providers |
| `Services/IconService.php` | Portfolio-specific icon system |
| `Providers/BlockService/BlockServiceProvider.php` | Custom block registration |
| `Providers/BlockService/Features/ButtonIconEnhancer.php` | Button icon picker (uses IconService) |
| `Providers/BlockService/assets/` | Block service source JS and SCSS |

### Twig Templates (`views/`)

| File | Description |
|------|-------------|
| `header.twig` | Site header with logo and mode toggle |
| `footer.twig` | Site footer with branding |
| `page.twig` | Page template (site-specific layout) |
| `single.twig` | Single post/project template |

### Custom Blocks (`blocks/`)

| Block | Description |
|-------|-------------|
| `hero/` | Hero banner with title, buttons, SVG |
| `projects/` | Projects grid display |
| `shutter-cards/` | Shutter card container |
| `shutter-card/` | Individual shutter card item |
| `index.js` | Block registration entry point |

### Site-Specific Assets

| File | Description |
|------|-------------|
| `src/Providers/Theme/assets/scss/layout/_header.scss` | Header styling |
| `src/Providers/Theme/assets/js/header.js` | Header behavior (mode toggle, menu) |
| `src/Providers/Theme/assets/js/formats.js` | Custom editor formats |
| `src/Providers/Theme/assets/scss/index.scss` | Main stylesheet entry (imports parent + overrides) |

### Configuration

| File | Description |
|------|-------------|
| `style.css` | Theme header + site-specific styles |
| `theme.json` | Design system (colors, fonts, spacing) - version 3 |
| `config/project.json` | Project CPT configuration |

---

## Compiled Output (`dist/`)

All compiled assets are output to a centralized `dist/` directory:

```
dist/
├── blocks/                    # Block editor and view scripts
│   ├── index.js              # Compiled block editor JS
│   ├── index.asset.php       # WP dependencies manifest
│   ├── index.css             # Block editor styles
│   ├── style-index.css       # Block frontend styles
│   └── {block}-view.js       # Auto-discovered view scripts
├── css/                       # Provider stylesheets
│   └── {provider-name}.css   # e.g., block-service.css
└── js/                        # Provider and theme scripts
    ├── main.js               # Editor main script
    ├── frontend.js           # Frontend script
    └── {provider-name}/      # Provider scripts
        └── {script}.js       # e.g., block-service/button.js
```

**Build Pipeline:**
- `npm run build-providers` → `dist/js/theme/frontend.js`, `dist/css/theme.css`
- `npm run build-blocks` → `dist/blocks/index.js`, `dist/blocks/style-index.css`
- `npm run build-block-views` → `dist/blocks/{block}-view.js` (auto-discovered)
- `npm run build-providers` → `dist/css/{provider}.css`, `dist/js/{provider}/*.js`
- `npm run compile-theme-css` → `style.css` (root, required by WP)

---

## Resulting Directory Structure

### Parent Theme

```
parent-theme/
├── functions.php
├── index.php
├── header.php
├── footer.php
├── sidebar.php
├── archive.php
├── author.php
├── search.php
├── single.php
├── page.php
├── 404.php
├── style.css
├── theme.json                 # Base theme.json (version 3)
├── src/
│   ├── Contracts/
│   │   ├── Registrable.php
│   │   └── HasAssets.php
│   ├── Traits/
│   │   └── HasAssets.php
│   ├── Providers/
│   │   ├── ServiceProvider.php
│   │   ├── AssetServiceProvider.php
│   │   ├── PostTypeServiceProvider.php
│   │   ├── TwigServiceProvider.php
│   │   └── ThemeService/
│   │       ├── Provider.php
│   │       └── Features/
│   │           └── DisableComments.php
│   └── Theme.php
├── views/
│   ├── base.twig
│   ├── head.twig
│   ├── index.twig
│   ├── archive.twig
│   ├── author.twig
│   ├── search.twig
│   ├── 404.twig
│   ├── comment.twig
│   ├── comment-form.twig
│   ├── single-password.twig
│   ├── page-plugin.twig
│   ├── tease.twig
│   ├── tease-post.twig
│   ├── menu.twig
│   ├── sidebar.twig
│   └── partial/
│       └── pagination.twig
├── scripts/
│   └── build-providers.js
├── dist/                      # Compiled output (gitignored)
├── composer.json
├── package.json
└── webpack.config.js
```

### Child Theme (vincentragosta)

```
vincentragosta/
├── functions.php
├── front-page.php
├── style.css
├── theme.json                 # Site-specific design tokens (version 3)
├── src/
│   ├── Theme.php
│   ├── Services/
│   │   └── IconService.php
│   └── Providers/
│       └── BlockService/
│           ├── BlockServiceProvider.php
│           ├── Features/
│           │   └── ButtonIconEnhancer.php
│           └── assets/
│               ├── js/
│               │   └── button.js
│               └── scss/
│                   ├── index.scss
│                   └── _button-icon.scss
├── views/
│   ├── header.twig
│   ├── footer.twig
│   ├── page.twig
│   └── single.twig
├── blocks/
│   ├── index.js
│   ├── hero/
│   ├── projects/
│   ├── shutter-cards/
│   └── shutter-card/
├── config/
│   └── project.json
└── dist/                      # Compiled output (gitignored)
```

---

## Implementation Steps

### Phase 1: Setup Parent Theme Structure
1. Create directory structure in parent-theme
2. Set up composer.json with PSR-4 autoloading (namespace: `ParentTheme`)
3. Set up package.json with build scripts
4. Copy webpack.config.js and build scripts

### Phase 2: Move PHP Infrastructure
1. Copy Contracts, Traits, and base Providers to parent
2. Update namespaces from `ChildTheme` to `ParentTheme`
3. Create base Theme.php class in parent
4. Update child Theme.php to extend parent

### Phase 3: Move Templates
1. Copy generic Twig templates to parent
2. Ensure child templates properly extend parent's base.twig
3. Test template inheritance

### Phase 4: Move Assets
1. Copy common SCSS to parent
2. Update import paths in both themes
3. Set up build pipeline in parent
4. Configure child to import parent styles

### Phase 5: Update Autoloading
1. Update parent composer.json with PSR-4 autoload
2. Update child composer.json to work with parent
3. Run composer dump-autoload in both

### Phase 6: Testing
1. Activate parent theme - verify basic functionality
2. Activate child theme - verify full functionality
3. Test template overrides
4. Test asset compilation

---

## Namespace Changes

### Parent Theme
```php
namespace ParentTheme\Contracts;
namespace ParentTheme\Traits;
namespace ParentTheme\Providers;
namespace ParentTheme\Providers\ThemeService;
namespace ParentTheme\Providers\ThemeService\Features;
```

### Child Theme
```php
namespace ChildTheme;
namespace ChildTheme\Services;
namespace ChildTheme\Providers\BlockService;
namespace ChildTheme\Providers\BlockService\Features;
```

---

## HasAssets Contract Pattern

The `HasAssets` interface and trait provide a reusable pattern for asset enqueueing:

### Interface (`Contracts/HasAssets.php`)
```php
interface HasAssets
{
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void;
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void;
}
```

### Trait (`Traits/HasAssets.php`)
Provides default implementation that:
- Loads styles from `dist/css/{filename}`
- Loads scripts from `dist/js/{provider-slug}/{filename}`
- Auto-detects provider slug from class name (e.g., `BlockServiceProvider` → `block-service`)
- Handles file existence checks and cache busting via `filemtime()`

### Usage
```php
class BlockServiceProvider extends ServiceProvider
{
    // ServiceProvider uses HasAssets trait, so these methods are available:

    public function enqueueEditorAssets(): void
    {
        $this->enqueueStyle('my-handle', 'block-service.css');
        $this->enqueueScript('my-handle-js', 'button.js', ['wp-blocks']);
    }
}
```

---

## Notes

- Parent theme provides the foundation; child theme adds site-specific features
- Child can override any parent template by creating same-named file
- Child's Theme.php extends parent's Theme.php and adds its own providers
- All compiled assets go to `dist/` directory (gitignored)
- Build tools auto-discover provider assets - no manual config needed
- Use `get_template_directory()` (parent) vs `get_stylesheet_directory()` (child) appropriately
- theme.json uses version 3 schema with `defaultFontSizes` and `defaultSpacingSizes` set to false
