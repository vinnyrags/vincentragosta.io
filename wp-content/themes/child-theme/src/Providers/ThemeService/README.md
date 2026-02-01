# ThemeServiceProvider

Extends the parent theme's ThemeServiceProvider to add site-specific configuration, custom blocks, and block enhancements.

## Registered Blocks

| Block | Slug | Description |
|-------|------|-------------|
| Shutter Cards | `child-theme/shutter-cards` | Container for shutter card items |
| Shutter Card | `child-theme/shutter-card` | Individual expandable card within the container |

Block files are located in `blocks/shutter-cards/` and `blocks/shutter-card/`.

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

### Theme Service Styles

Enqueues `theme-service.css` for frontend styling related to theme services.

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

All features from the parent ThemeServiceProvider are inherited:

- **Theme Supports** - Standard WordPress theme supports
- **DisableBlocks** - Gutenberg block restrictions
- **DisableComments** - Comment functionality disabled
- **EnableSvgUploads** - SVG upload support with sanitization

## Overriding Parent Features

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

To add features specific to this site, add them to the `$features` array:

```php
protected array $features = [
    ButtonIconEnhancer::class,
    CoverBlockStyles::class,
    MyCustomFeature::class, // Add here
];
```

Or create features in `Features/` directory and register them.

## Directory Structure

```
ThemeService/
├── ThemeServiceProvider.php
├── README.md
├── Features/
│   ├── ButtonIconEnhancer.php
│   └── CoverBlockStyles.php
├── assets/
│   ├── js/
│   │   └── button.js          → dist/js/theme-service/button.js
│   └── scss/
│       ├── index.scss         → dist/css/theme-service.css
│       ├── _button-icon.scss
│       ├── _color-mode.scss
│       └── blocks/
│           └── _wp-block-cover.scss
└── blocks/
    ├── shutter-cards/
    │   ├── block.json
    │   ├── editor/
    │   │   ├── index.js
    │   │   └── editor.scss
    │   └── frontend/
    │       └── style.scss
    └── shutter-card/
        ├── block.json
        ├── editor/
        │   └── index.js
        └── frontend/
            └── style.scss
```
