# Shutter Card Block

An individual card item for use within the Shutter Cards container block.

## Use Case

Each Shutter Card represents a single content item with a title, subtitle, description, and toggle button. Cards can only be used inside the Shutter Cards container and work together to create an interactive expanding/collapsing experience.

## Implementation

### Block Structure

```html
<div class="wp-block-child-theme-shutter-card">
  <div class="shutter-card">
    <span class="shutter-card__id">01</span>
    <h3 class="shutter-card__title">Title</h3>
    <p class="shutter-card__subtitle">Subtitle</p>
    <div class="shutter-card__description">Description text...</div>
    <button class="shutter-card__toggle">...</button>
  </div>
</div>
```

### Files

| File | Purpose |
|------|---------|
| `block.json` | Block metadata and attributes |
| `editor/index.js` | Block registration |
| `editor/edit.js` | Editor component with RichText fields |
| `frontend/render.php` | Server-side rendering |
| `frontend/style.scss` | Card styles (shared by editor and frontend) |
| `templates/card.twig` | Twig template for card markup |

### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `title` | string | `""` | Card heading (h3) |
| `subtitle` | string | `""` | Subtitle text below title |
| `description` | string | `""` | Main content area |
| `cardIndex` | string | `"00"` | Auto-generated index (01, 02, etc.) |

### States

Cards have two visual states controlled by the parent container:

**Active (`.is-active`):**
- Full background color
- Content visible
- Toggle button filled

**Inactive (`.is-inactive`):**
- Muted background color
- Content faded (desktop) or collapsed (mobile)
- Toggle button rotated 45° (appears as "+")
- Clickable to activate

### Accessibility

- Toggle button has `aria-label` and `aria-expanded` attributes
- Card wrapper gets `role="button"` and `tabindex="0"` when inactive
- Keyboard navigation with Enter/Space to activate
- Focus-visible outlines for keyboard users
- `prefers-reduced-motion` support disables transitions

### Light Mode

The block supports light mode via `.light-mode` class on an ancestor, with inverted color schemes for both active and inactive states.

## Usage

This block can only be inserted inside a Shutter Cards container. The container automatically:
- Assigns card indices (01, 02, 03...)
- Manages active/inactive states
- Limits to 5 cards maximum

## Editor Behavior

- Clicking a card in the editor makes it active
- First card is active by default when no card is selected
- Active state shows full content preview
- Inactive cards show muted styling to preview frontend appearance
