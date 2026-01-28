# BlockServiceProvider

Handles custom block registration, block editor data localization, and block enhancements.

## Registered Blocks

Custom blocks are registered from the `/blocks` directory:

| Block | Slug | Description |
|-------|------|-------------|
| Hero | `child-theme/hero` | Full-width hero section with SVG animations |
| Projects | `child-theme/projects` | Project grid display |
| Shutter Cards | `child-theme/shutter-cards` | Container for shutter card items |
| Shutter Card | `child-theme/shutter-card` | Individual expandable card |

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

#### Styling Icons

```scss
.wp-block-button.has-icon {
  .wp-block-button__link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .wp-block-button__icon {
    width: 1em;
    height: 1em;

    svg {
      width: 100%;
      height: 100%;
    }
  }
}
```

## Localized Data

Data passed to the block editor via `wp_localize_script`:

### Hero Block (`childThemeHeroBlockData`)

| Key | Description |
|-----|-------------|
| `svgOptions` | Dropdown options for squiggle selector |
| `svgContent` | Map of SVG names to content |

### Button Icons (`childThemeButtonIconData`)

| Key | Description |
|-----|-------------|
| `iconOptions` | Dropdown options for icon picker |
| `iconContentMap` | Map of icon names to SVG content |

## Assets

| Asset | Hook | Description |
|-------|------|-------------|
| `block-service.css` | Frontend + Editor | Button icon styles |
| `button.js` | Editor only | Icon picker UI component |

## Adding New Blocks

1. Create block directory in `/blocks/{block-name}/`
2. Add `block.json`, `edit.js`, `index.js`, `style.scss`
3. Add block name to `$blocks` array:

```php
protected array $blocks = [
    'hero',
    'projects',
    'shutter-cards',
    'shutter-card',
    'my-new-block', // Add here
];
```

4. Run `npm run build` to compile
