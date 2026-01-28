# Styling Guide

This document covers the CSS/SCSS methodology, conventions, and patterns used in this theme.

## Table of Contents

- [Architecture](#architecture)
- [BEM Methodology](#bem-methodology)
- [Breakpoints & Container Queries](#breakpoints--container-queries)
- [Design Tokens](#design-tokens)
- [Specificity Guidelines](#specificity-guidelines)
- [Color Modes](#color-modes)
- [Common Patterns](#common-patterns)

## Architecture

### SCSS Directory Structure

```
assets/src/scss/
├── common/
│   ├── _breakpoints.scss    # Breakpoint variables & mixins
│   ├── _color-mode.scss     # Light/dark mode styles
│   ├── _function.scss       # SCSS functions
│   ├── _layout.scss         # Site layout (main content area)
│   └── _animation.scss      # Animation utilities
├── elements/
│   ├── _headings.scss       # Heading styles
│   └── _block-text.scss     # Text block styles
├── layout/
│   └── _header.scss         # Header component
├── utilities/
│   ├── _display.scss        # Display utilities
│   └── _text.scss           # Text utilities
└── main.scss                # Entry point
```

### Compilation

All compiled assets output to the `dist/` directory:

| Source | Output | Command |
|--------|--------|---------|
| `main.scss` | `style.css` | `npm run compile-theme-css` |
| `blocks/*/style.scss` | `dist/blocks/style-index.css` | `npm run build-blocks` |
| `src/Providers/*/assets/scss/` | `dist/css/{provider}.css` | `npm run compile-providers` |

### Auto-Injection

Block stylesheets automatically have access to breakpoint mixins via webpack configuration. No `@use` statement needed:

```scss
// blocks/hero/style.scss
// Breakpoint mixins are auto-injected

.hero {
    @include container(hero, md) {
        // This works without importing breakpoints
    }
}
```

## BEM Methodology

### Naming Convention

```
.block__element--modifier
```

| Part | Description | Example |
|------|-------------|---------|
| Block | Standalone component | `.card` |
| Element | Part of a block | `.card__title` |
| Modifier | Variation or state | `.card--featured` |

### Examples

```scss
// Block
.project-card {
    display: flex;
    flex-direction: column;

    // Element
    &__image-link {
        display: block;
        overflow: hidden;
    }

    &__title {
        font-size: var(--wp--preset--font-size--body-large);
    }

    &__category {
        text-transform: uppercase;
    }

    // Modifier
    &--featured {
        border: 2px solid var(--wp--preset--color--accent-1);
    }
}
```

### BEM Nesting Rules

1. **Elements nest inside blocks** using `&__`:

```scss
.card {
    &__title { }      // .card__title
    &__content { }    // .card__content
}
```

2. **Modifiers nest inside blocks** using `&--`:

```scss
.card {
    &--large { }      // .card--large
    &--featured { }   // .card--featured
}
```

3. **Never nest elements inside elements**:

```scss
// Wrong
.card__header__title { }

// Correct
.card__header-title { }
// or just
.card__title { }
```

## Breakpoints & Container Queries

### Breakpoint Values

| Name | Value | Common Use |
|------|-------|------------|
| `sm` | 576px | Large phones |
| `md` | 768px | Tablets |
| `lg` | 992px | Desktops |
| `xl` | 1440px | Large screens |

### Container Query Mixin

For block-level responsive styles:

```scss
// Syntax
@include container($container-name, $size) { ... }

// Example
.hero {
    container: hero / inline-size;  // Define container
    flex-direction: column;

    @include container(hero, md) {
        flex-direction: row;
    }

    @include container(hero, lg) {
        padding: var(--wp--preset--spacing--60);
    }
}
```

### Media Query Mixin

For global/layout styles that should respond to viewport:

```scss
// Syntax
@include media($size) { ... }

// Example
.site-header {
    padding: var(--wp--preset--spacing--40);

    @include media(lg) {
        padding: var(--wp--preset--spacing--60);
    }
}
```

### Reduced Motion Mixin

```scss
.animated-element {
    animation: fadeIn 0.3s ease;

    @include reduced-motion {
        animation: none;
    }
}
```

### Container Setup Pattern

Every block that needs responsive styles should:

1. Define a named container
2. Use container queries for responsive rules

```scss
.my-block {
    // 1. Define container (shorthand)
    container: my-block / inline-size;

    // 2. Base styles (mobile-first)
    display: grid;
    grid-template-columns: 1fr;

    // 3. Responsive styles
    @include container(my-block, md) {
        grid-template-columns: repeat(2, 1fr);
    }

    @include container(my-block, lg) {
        grid-template-columns: repeat(3, 1fr);
    }
}
```

## Design Tokens

### Using theme.json Tokens

Always prefer CSS custom properties from theme.json:

```scss
// Colors
var(--wp--preset--color--base)           // #FFFFFF
var(--wp--preset--color--contrast)       // #151515
var(--wp--preset--color--accent-1)       // #ceff00
var(--wp--preset--color--accent-2)       // #686868
var(--wp--preset--color--grey-1)         // #1c1c1f
var(--wp--preset--color--grey-2)         // #313135
var(--wp--preset--color--grey-3)         // #afafb6
var(--wp--preset--color--grey-4)         // #494950

// Spacing (8px grid)
var(--wp--preset--spacing--20)           // 0.5rem (8px)  - tight
var(--wp--preset--spacing--30)           // 1rem (16px)   - small
var(--wp--preset--spacing--40)           // 1.5rem (24px) - medium
var(--wp--preset--spacing--50)           // 2.5rem (40px) - large
var(--wp--preset--spacing--60)           // 4rem (64px)   - section gaps

// Typography
var(--wp--preset--font-size--body-tiny)       // 0.875rem
var(--wp--preset--font-size--body-small)      // 1rem
var(--wp--preset--font-size--body-default)    // 1rem - 1.5rem (fluid)
var(--wp--preset--font-size--body-large)      // 2rem - 2.4rem (fluid)
var(--wp--preset--font-size--heading-medium)  // 3.125rem - 4.5rem (fluid)
var(--wp--preset--font-size--heading-large)   // 4rem - 5.5rem (fluid)

// Font Families
var(--wp--preset--font-family--roobert)
var(--wp--preset--font-family--reckless-neue)
var(--wp--preset--font-family--fira-code)
```

### Why Design Tokens?

1. **Consistency**: Same values everywhere
2. **Maintainability**: Change once, update everywhere
3. **Editor Sync**: Block editor uses same values
4. **Dark Mode**: Tokens can be swapped for color modes

## Specificity Guidelines

### Keep Specificity Low

1. **Avoid IDs** in selectors
2. **Avoid nesting** beyond 2-3 levels
3. **Use `:is()`** to group selectors without increasing specificity

### Reducing Specificity Patterns

```scss
// Instead of deeply nested selectors
.card {
    &__content {
        &__title {
            // Specificity: 0,3,0
        }
    }
}

// Use flat selectors
.card__title {
    // Specificity: 0,1,0
}
```

### Modifier State Patterns

Keep modifier combinations outside the main block for lower specificity:

```scss
.shutter-card {
    // Base styles
    background: var(--wp--preset--color--base);

    &--inactive {
        // Simple modifier styles
        cursor: pointer;
    }
}

// Complex modifier states (outside block for lower specificity)
.shutter-card--inactive .shutter-card__close-icon {
    transform: rotate(45deg);
}

.shutter-card--inactive:hover .shutter-card__close-icon {
    transform: rotate(0);
}
```

### Using :is() for Grouping

```scss
// Group selectors without increasing specificity
.hero :is(.wp-block-heading, .wp-block-paragraph) {
    text-shadow: 0 0 10px var(--color-bg);
}

// Instead of
.hero .wp-block-heading,
.hero .wp-block-paragraph {
    // Same specificity as above, but more verbose
}
```

## Color Modes

### Light Mode Pattern

The theme defaults to dark mode. Light mode is applied via `.light-mode` class:

```scss
// Default (dark mode)
.shutter-card {
    background-color: var(--wp--preset--color--base);
    color: var(--wp--preset--color--contrast);
}

// Light mode override
.light-mode .shutter-card {
    background-color: var(--wp--preset--color--contrast);
    color: var(--wp--preset--color--base);
}
```

### Color Mode Variables

For complex components, define local variables that swap:

```scss
.component {
    --component-bg: var(--wp--preset--color--base);
    --component-text: var(--wp--preset--color--contrast);

    background: var(--component-bg);
    color: var(--component-text);
}

.light-mode .component {
    --component-bg: var(--wp--preset--color--contrast);
    --component-text: var(--wp--preset--color--base);
}
```

## Common Patterns

### Responsive Grid

```scss
.grid {
    container: grid / inline-size;
    display: grid;
    gap: var(--wp--preset--spacing--50);
    grid-template-columns: 1fr;

    @include container(grid, md) {
        grid-template-columns: repeat(2, 1fr);
    }

    @include container(grid, lg) {
        grid-template-columns: repeat(3, 1fr);
    }
}
```

### Card Component

```scss
.card {
    background-color: var(--wp--preset--color--base);
    border: 1px solid var(--wp--preset--color--grey-2);
    padding: var(--wp--preset--spacing--50);
    transition: transform 0.3s ease, box-shadow 0.3s ease;

    &:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    &__title {
        font-family: var(--wp--preset--font-family--reckless-neue);
        font-size: var(--wp--preset--font-size--body-large);
        font-weight: 300;
        margin: 0 0 var(--wp--preset--spacing--40);
    }

    &__content {
        font-size: var(--wp--preset--font-size--body-small);
        line-height: 1.5;
    }
}
```

### Underline Link Animation

```scss
.animated-link {
    position: relative;
    text-decoration: none;

    &::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 100%;
        height: 2px;
        background-color: var(--wp--preset--color--accent-1);
        transform: scaleX(0);
        transform-origin: bottom left;
        transition: transform 0.3s ease-out;
    }

    &:hover::after {
        transform: scaleX(1);
    }
}
```

### Preload Pattern (Prevent FOUC)

For JavaScript-enhanced components:

```scss
.component {
    opacity: 1;
    visibility: visible;
    transition: opacity 0.6s ease-in;

    &--preload {
        opacity: 0;
        visibility: hidden;
    }
}
```

```javascript
// Remove preload class after JS initializes
element.classList.remove('component--preload');
```
