# Custom Blocks

This document covers the patterns and conventions for developing custom Gutenberg blocks in this theme.

## Table of Contents

- [Block Architecture](#block-architecture)
- [Available Blocks](#available-blocks)
- [Creating a New Block](#creating-a-new-block)
- [Wrapper Pattern](#wrapper-pattern)
- [Container Queries](#container-queries)
- [Block Styling](#block-styling)

## Block Architecture

### File Structure

Each custom block follows this structure:

```
blocks/
├── hero/
│   ├── block.json       # Block metadata (required)
│   ├── index.js         # Block registration
│   ├── edit.js          # Editor component
│   ├── save.js          # Save function (or returns null)
│   ├── render.php       # Server-side rendering
│   ├── style.scss       # Frontend + editor styles
│   └── editor.scss      # Editor-only styles
├── index.js             # Registers all blocks
├── index-view.js        # Frontend scripts entry
└── build/               # Compiled output
```

### Registration Flow

```javascript
// blocks/index.js
import './hero';
import './projects';
import './shutter-cards';
import './shutter-card';

// blocks/hero/index.js
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';
import './editor.scss';

registerBlockType(metadata.name, {
    edit: Edit,
    save,
});
```

## Available Blocks

### Hero Block

A full-width hero section with optional SVG background or video.

**Block name:** `child-theme/hero`

**Features:**
- SVG asset selector
- Video URL support
- Inner blocks for content (headings, paragraphs, buttons)
- Animated mask reveal for SVG

**Usage:**
```html
<!-- wp:child-theme/hero {"svgAsset":"home-page","align":"full"} -->
<!-- wp:heading -->
<h1>Welcome</h1>
<!-- /wp:heading -->
<!-- wp:buttons -->
<!-- wp:button -->
<a href="/about">Learn More</a>
<!-- /wp:button -->
<!-- /wp:buttons -->
<!-- /wp:child-theme/hero -->
```

### Shutter Cards

An accordion-style card container with expand/collapse behavior.

**Block name:** `child-theme/shutter-cards`

**Features:**
- Contains multiple shutter-card blocks
- Automatic width calculation for collapsed cards
- Smooth transitions
- Preload state to prevent FOUC

### Shutter Card

Individual card within shutter-cards container.

**Block name:** `child-theme/shutter-card`

**Attributes:**
- `title` - Card title
- `subtitle` - Category/subtitle text
- `description` - Rich text content
- `cardIndex` - Display index (e.g., "01")

### Projects

A grid layout for project cards with asymmetric sizing.

**Block name:** `child-theme/projects`

**Features:**
- 10-column grid at large sizes
- Cards span different column counts
- Responsive single-column on mobile

## Creating a New Block

### Step 1: Create Block Directory

```bash
mkdir blocks/my-block
```

### Step 2: Create block.json

```json
{
  "apiVersion": 3,
  "name": "child-theme/my-block",
  "title": "My Block",
  "category": "design",
  "icon": "block-default",
  "description": "Description of my block.",
  "attributes": {
    "content": {
      "type": "string",
      "default": ""
    }
  },
  "supports": {
    "html": false,
    "align": ["wide", "full"]
  },
  "textdomain": "child-theme",
  "render": "file:./render.php"
}
```

### Step 3: Create Edit Component

```javascript
// edit.js
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps();

    return (
        <div {...blockProps}>
            <div className="my-block">
                <RichText
                    tagName="p"
                    value={attributes.content}
                    onChange={(content) => setAttributes({ content })}
                    placeholder="Enter content..."
                />
            </div>
        </div>
    );
}
```

### Step 4: Create Save Function

For dynamic blocks (server-rendered), return `null`:

```javascript
// save.js
export default function save() {
    return null;
}
```

For static blocks, return the markup:

```javascript
// save.js
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save({ attributes }) {
    const blockProps = useBlockProps.save();

    return (
        <div {...blockProps}>
            <div className="my-block">
                <RichText.Content tagName="p" value={attributes.content} />
            </div>
        </div>
    );
}
```

### Step 5: Create Server Render (for dynamic blocks)

```php
<?php
// render.php
$wrapper_attributes = get_block_wrapper_attributes();
$content = $attributes['content'] ?? '';
?>
<div <?= $wrapper_attributes; ?>>
    <div class="my-block">
        <p><?= esc_html($content); ?></p>
    </div>
</div>
```

### Step 6: Register the Block

```javascript
// blocks/my-block/index.js
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType(metadata.name, {
    edit: Edit,
    save,
});
```

Add to main index:

```javascript
// blocks/index.js
import './my-block';
```

### Step 7: Register in PHP

Add to `BlockServiceProvider.php`:

```php
$blocks = ['hero', 'projects', 'shutter-cards', 'shutter-card', 'my-block'];
```

## Wrapper Pattern

### The Problem

WordPress applies wrapper classes (`wp-block-child-theme-*`) to blocks. Mixing WordPress classes with custom BEM classes creates specificity issues.

### The Solution

Separate WordPress wrapper from BEM inner container:

```php
// render.php

// WordPress manages the outer wrapper
$wrapper_attributes = get_block_wrapper_attributes();

?>
<div <?= $wrapper_attributes; ?>>
    <!-- BEM inner container -->
    <div class="my-block">
        <div class="my-block__content">
            <?= $content; ?>
        </div>
    </div>
</div>
```

### Benefits

1. WordPress classes handle alignment, spacing, and editor features
2. BEM classes handle component styling
3. No specificity conflicts
4. Clean separation of concerns

### CSS Selectors

Target only BEM classes in stylesheets:

```scss
// Good - targets BEM class
.my-block {
    display: flex;
}

.my-block__content {
    flex: 1;
}

// Avoid - targets WordPress wrapper
.wp-block-child-theme-my-block {
    // Don't do this
}
```

## Container Queries

### Why Container Queries?

Blocks can appear in different contexts (full-width, sidebar, nested). Container queries respond to the block's actual width, not the viewport.

### Setting Up Container Queries

1. Define a named container on the BEM root:

```scss
.my-block {
    container: my-block / inline-size;
}
```

2. Use the container mixin for responsive styles:

```scss
.my-block {
    container: my-block / inline-size;
    display: flex;
    flex-direction: column;

    @include container(my-block, lg) {
        flex-direction: row;
    }
}
```

### Available Breakpoints

| Size | Value | Usage |
|------|-------|-------|
| `sm` | 576px | Small tablets |
| `md` | 768px | Tablets |
| `lg` | 992px | Desktops |
| `xl` | 1440px | Large screens |

### Mixin Reference

```scss
// Container query (for blocks)
@include container($name, $size) { ... }

// Example
@include container(hero, md) {
    padding-top: 20%;
}

// Compiles to:
@container hero (min-width: 768px) {
    padding-top: 20%;
}
```

## Block Styling

### File Organization

```
blocks/my-block/
├── style.scss      # Frontend + editor (auto-loaded)
└── editor.scss     # Editor-only styles
```

### Style Conventions

```scss
// style.scss

// 1. Define container (required for container queries)
.my-block {
    container: my-block / inline-size;

    // 2. Base styles (mobile-first)
    display: flex;
    flex-direction: column;
    gap: var(--wp--preset--spacing--40);

    // 3. Responsive styles using container queries
    @include container(my-block, lg) {
        flex-direction: row;
    }

    // 4. BEM elements
    &__header {
        font-size: var(--wp--preset--font-size--body-large);
    }

    &__content {
        flex: 1;
    }

    // 5. BEM modifiers
    &--featured {
        border: 2px solid var(--wp--preset--color--accent-1);
    }
}

// 6. Complex state selectors (outside main block for lower specificity)
.my-block--featured .my-block__header {
    color: var(--wp--preset--color--accent-1);
}
```

### Using Design Tokens

Always use theme.json CSS custom properties:

```scss
// Good - uses design tokens
.my-block {
    color: var(--wp--preset--color--base);
    font-size: var(--wp--preset--font-size--body-default);
    padding: var(--wp--preset--spacing--50);
    font-family: var(--wp--preset--font-family--reckless-neue);
}

// Avoid - hardcoded values
.my-block {
    color: #ffffff;
    font-size: 1.5rem;
    padding: 1.5rem;
}
```

### Editor Styles

Editor-specific styles go in `editor.scss`:

```scss
// editor.scss

// Visual indicators for the editor
.my-block {
    border: 1px dashed var(--wp--preset--color--grey-3);
    min-height: 100px;
}

// Placeholder styling
.my-block--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--wp--preset--color--grey-3);
}
```
