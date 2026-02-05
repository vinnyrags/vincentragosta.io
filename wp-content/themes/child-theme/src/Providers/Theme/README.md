# ThemeProvider

Extends the parent theme's ThemeProvider to add site-specific configuration, custom blocks, and block enhancements.

## Registered Blocks

| Block | Slug | Description |
|-------|------|-------------|
| Shutter Cards | `child-theme/shutter-cards` | Container for shutter card items |
| Shutter Card | `child-theme/shutter-card` | Individual expandable card within the container |

Block files are located in `blocks/shutter-cards/` and `blocks/shutter-card/`.

### Block Architecture

Both blocks are **dynamic blocks** with server-side rendering:

- **Editor**: React components in `editor/index.js` and `editor/edit.js`
- **Frontend**: PHP rendering via `frontend/render.php` with Twig templates in `templates/`

The shutter-cards container uses `InnerBlocks` and must return `<InnerBlocks.Content />` from its save function to persist child blocks.

## Features

### ButtonIconEnhancer

Enhances the core `core/button` block with icon support on the frontend.

#### How It Works

1. Editor: JavaScript adds icon picker to button block sidebar
2. Save: Icon selection stored in block attributes (`selectedIcon`, `iconPosition`)
3. Render: PHP filter injects SVG icon into button HTML

#### Block Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `selectedIcon` | string | `''` | Icon name from sprite |
| `iconPosition` | string | `'right'` | `'left'` or `'right'` |

#### CSS Classes Added

When a button has an icon, these classes are added to the wrapper:

- `has-icon` - Indicates button has an icon
- `icon-pos-left` or `icon-pos-right` - Icon position

### CoverBlockStyles

Registers custom block styles for `core/cover`:

- **Animated** - Animated background style

## What It Adds

### Admin Bar Hidden

Hides the WordPress admin bar on the frontend:

```php
add_filter('show_admin_bar', '__return_false');
```

### Theme Styles

Enqueues `theme.css` for frontend styling related to the theme provider.

## Assets

### Block Assets

- **Frontend + Editor**: `shutter-cards.css`, `shutter-card.css`, `block-service.css`
- **Editor only**: `shutter-cards.js`, `shutter-card.js`, `button.js`, `shutter-cards-editor.css`, `shutter-card-editor.css`

### Localized Data

Data passed to the block editor via `wp_localize_script`:

#### Button Icons (`childThemeButtonIconData`)

| Key | Description |
|-----|-------------|
| `iconOptions` | Dropdown options for icon picker |
| `iconContentMap` | Map of icon names to SVG content |

## Inherited from Parent

All features from the parent ThemeProvider are inherited automatically via `collectFeatures()`:

- **Theme Supports** - Standard WordPress theme supports
- **DisableBlocks** - Gutenberg block restrictions
- **DisableComments** - Comment functionality disabled
- **DisablePosts** - Hides Posts from admin menu (uses Projects instead)
- **EnableSvgUploads** - SVG upload support with sanitization

## Overriding Parent Features

### Opt Out of an Inherited Feature

Features are inherited automatically from parent providers via `collectFeatures()`. To disable an inherited feature, use the `=> false` syntax in the child's `$features` array:

```php
protected array $features = [
    ButtonIconEnhancer::class,
    CoverBlockStyles::class,
    DisablePosts::class => false,  // opt out of parent feature
];
```

### Re-enable a Disabled Block

Use the filter in your child theme's `functions.php`:

```php
add_filter('theme/disabled_block_types', function (array $blocks): array {
    // Re-enable the cover block
    return array_diff($blocks, ['core/cover']);
});
```

### Re-enable an Embed Provider

```php
add_filter('theme/disabled_embed_variations', function (array $variations): array {
    // Re-enable Twitter embeds
    return array_diff($variations, ['twitter']);
});
```

## Adding Site-Specific Features

Parent features are inherited automatically — only add child-specific features to the `$features` array:

```php
protected array $features = [
    ButtonIconEnhancer::class,
    CoverBlockStyles::class,
    MyCustomFeature::class, // Add here
];
```

Create feature classes in `Features/` implementing the `Registrable` interface.

## Directory Structure

```
Theme/
├── ThemeProvider.php
├── README.md
├── Features/
│   ├── ButtonIconEnhancer.php
│   └── CoverBlockStyles.php
├── assets/
│   ├── fonts/                     # Custom web fonts
│   │   ├── reckless-neue/
│   │   └── roobert/
│   ├── images/                    # SVG icons and images
│   │   ├── svg-sprite/            # Sprite icons (icon-arrow, etc.)
│   │   └── svg/                   # Standalone SVGs
│   ├── js/
│   │   ├── button.js          → dist/js/theme/button.js
│   │   ├── frontend.js        → dist/js/theme/frontend.js
│   │   └── header.js             # Imported by frontend.js
│   └── scss/
│       ├── index.scss         → dist/css/theme.css
│       ├── _button-icon.scss
│       ├── _color-mode.scss
│       └── blocks/
│           └── _wp-block-cover.scss
└── blocks/
    ├── shutter-cards/
    │   ├── block.json
    │   ├── editor/
    │   │   ├── index.js
    │   │   ├── edit.js
    │   │   └── editor.scss
    │   ├── frontend/
    │   │   ├── render.php
    │   │   ├── style.scss
    │   │   └── view.js
    │   └── templates/
    │       └── container.twig
    └── shutter-card/
        ├── block.json
        ├── editor/
        │   ├── index.js
        │   └── edit.js
        ├── frontend/
        │   ├── render.php
        │   └── style.scss
        └── templates/
            └── card.twig
```
