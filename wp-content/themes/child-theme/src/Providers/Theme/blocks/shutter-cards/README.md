# Shutter Cards Block

A container block for interactive card components that expand and collapse with a "shutter" effect.

## Use Case

Shutter Cards are ideal for presenting related content items (like service offerings, process steps, or feature highlights) in an engaging, interactive format. On desktop, cards display in a grid with one card expanded at a time. On mobile, they function as an accordion.

## Implementation

### Block Structure

```
shutter-cards (container)
├── shutter-card (item)
├── shutter-card (item)
├── shutter-card (item)
└── ...
```

### Files

| File | Purpose |
|------|---------|
| `block.json` | Block metadata, attributes, and configuration |
| `render.php` | Server-side rendering via Timber/Twig |
| `style.scss` | Frontend styles with responsive grid layout |
| `view.js` | Frontend JavaScript for interactivity |
| `container.twig` | Twig template for the container markup |
| `editor/index.js` | Block registration for the editor |
| `editor/edit.js` | Editor component with InnerBlocks |
| `editor/editor.scss` | Editor-specific styles (grid layout matching frontend) |

### Grid Layout

The container uses CSS Grid with a 10-column system:

**4 Cards:**
- Row 1: 40% / 60% (span 4 + span 6)
- Row 2: 60% / 40% (span 6 + span 4)

**5 Cards:**
- Row 1: ~33% each (span 3 + span 4 + span 3)
- Row 2: 60% / 40% (span 6 + span 4)

### Responsive Behavior

- **Desktop (lg+):** Grid layout with visual active/inactive states
- **Mobile:** Single column with accordion behavior (content collapses for inactive cards)

### CSS Container Queries

The block uses CSS container queries (`container: shutter-cards / inline-size`) for responsive behavior, allowing it to adapt based on its container width rather than viewport width.

### Preload State

The container renders with a `shutter-cards--preload` class that hides content until JavaScript initializes, preventing layout shift.

## Usage

1. Add the "Shutter Cards" block to your page
2. Add 2-5 Shutter Card items inside
3. Each card auto-numbers based on its position
4. First card is active by default

## Extending

The block supports:
- `align`: wide, full
- `spacing`: padding, margin

Child themes can customize via CSS custom properties defined in the theme's design tokens.
