# Vincent Ragosta Theme

A WordPress child theme extending `parent-theme` with custom blocks, Timber/Twig templating, and a modern build system.

## Requirements

- PHP 8.1+
- WordPress 6.0+
- Node.js 18+
- Composer
- Docker/DDEV (for local development)

## Quick Start

```bash
# Install all dependencies (both parent and child themes)
make install

# Build assets
make build

# Start development mode (watch for changes)
make watch
```

## Available Commands

| Command | Description |
|---------|-------------|
| `make install` | Install Composer & npm dependencies for both themes |
| `make build` | Build all child theme assets |
| `make test` | Run test suite for both themes |
| `make watch` | Start watch mode for development |
| `make clean` | Remove all generated files |
| `make autoload` | Regenerate Composer autoloaders |

## Project Structure

```
child-theme/
├── dist/                          # Compiled assets (gitignored)
├── src/
│   ├── Providers/
│   │   ├── Theme/                 # Theme supports, blocks, features, assets
│   │   └── Project/               # Project post type and projects block
│   ├── Services/
│   │   └── IconService.php        # SVG icon handling
│   ├── config/
│   │   └── container.php          # DI container definitions
│   └── Theme.php                  # Main theme class
├── views/                         # Twig templates
├── theme.json                     # Block editor configuration
└── style.css
```

---

## Architecture

### Hybrid Theme Approach

This theme combines traditional Timber/Twig templating with the Block Editor:

```
┌─────────────────────────────────────────────────────────┐
│                     WordPress                           │
├─────────────────────────────────────────────────────────┤
│  Timber/Twig Templates          │  Block Editor Content │
│  ─────────────────────          │  ───────────────────  │
│  • base.twig (layout)           │  • Page content       │
│  • header.twig                  │  • Custom blocks      │
│  • footer.twig                  │  • Core blocks        │
├─────────────────────────────────────────────────────────┤
│                    theme.json                           │
│            (Shared design tokens & settings)            │
└─────────────────────────────────────────────────────────┘
```

### Parent Theme Relationship

This theme extends `parent-theme` which provides:

- **Base Classes:** `Provider`, `Theme`
- **Support:** `AssetManager`, `BlockManager`, `FeatureManager`
- **Contracts:** `Registrable`
- **Features:** DisableBlocks, DisableComments, DisablePosts, EnableSvgUploads
- **Models:** `Post` base model (extends `Timber\Post`)
- **Services:** `IconService`

### Bootstrap Flow

```php
// functions.php
$parent_autoloader = get_template_directory() . '/vendor/autoload.php';
require_once $parent_autoloader;
require_once __DIR__ . '/vendor/autoload.php';

(new \ChildTheme\Theme())->bootstrap();
```

### Service Providers

| Provider | Purpose |
|----------|---------|
| `ThemeProvider` | Theme supports, frontend/editor assets, blocks (shutter-cards), Twig functions (`icon()`), features |
| `ProjectProvider` | Project post type, projects block |

---

## Custom Blocks

### Available Blocks

| Block | Name | Description |
|-------|------|-------------|
| Projects | `child-theme/projects` | Asymmetric project grid |
| Shutter Cards | `child-theme/shutter-cards` | Accordion card container |
| Shutter Card | `child-theme/shutter-card` | Individual expanding card |

### Block File Structure

Blocks live inside their provider's `blocks/` directory:

```
src/Providers/Theme/blocks/shutter-cards/
├── block.json                # Block metadata (required)
├── editor/
│   ├── index.js              # Block registration
│   ├── edit.js               # Editor component (React)
│   └── editor.scss           # Editor-only styles
├── frontend/
│   ├── render.php            # Server-side rendering
│   ├── style.scss            # Frontend + editor styles
│   └── view.js               # Frontend-only script
└── templates/
    └── container.twig        # Twig template for markup
```

### Creating a New Block

1. Create block directory: `src/Providers/MyProvider/blocks/{block-name}/`

2. Create `block.json`:
```json
{
  "apiVersion": 3,
  "name": "child-theme/my-block",
  "title": "My Block",
  "category": "design",
  "render": "file:./frontend/render.php"
}
```

3. Create edit component (`editor/edit.js`):
```javascript
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {
    return (
        <div {...useBlockProps()}>
            <div className="my-block">
                {/* Editor UI */}
            </div>
        </div>
    );
}
```

4. Create server render (`frontend/render.php`):
```php
<?php
$wrapper_attributes = get_block_wrapper_attributes();
?>
<div <?= $wrapper_attributes; ?>>
    <div class="my-block">
        <!-- Content -->
    </div>
</div>
```

5. Add to your provider's `$blocks` array:
```php
protected array $blocks = ['my-block'];
```

6. Register editor entry point (`editor/index.js`):
```javascript
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from '../block.json';

registerBlockType(metadata.name, { edit: Edit, save: () => null });
```

### Wrapper Pattern

Separate WordPress wrapper from BEM inner container:

```php
// WordPress manages outer wrapper
<div <?= get_block_wrapper_attributes(); ?>>
    <!-- BEM inner container -->
    <div class="my-block">
        <div class="my-block__content">...</div>
    </div>
</div>
```

Target only BEM classes in stylesheets:

```scss
// Good
.my-block { }
.my-block__content { }

// Avoid
.wp-block-child-theme-my-block { }
```

---

## Styling

### Design Tokens

Defined in `theme.json`, available as CSS custom properties:

```scss
// Colors
var(--wp--preset--color--base)           // #FFFFFF
var(--wp--preset--color--contrast)       // #151515
var(--wp--preset--color--accent-1)       // #ceff00
var(--wp--preset--color--accent-2)       // #686868

// Spacing (8px grid)
var(--wp--preset--spacing--20)           // 0.5rem (8px)  - tight
var(--wp--preset--spacing--30)           // 1rem (16px)   - small
var(--wp--preset--spacing--40)           // 1.5rem (24px) - medium
var(--wp--preset--spacing--50)           // 2.5rem (40px) - large
var(--wp--preset--spacing--60)           // 4rem (64px)   - section gaps
var(--wp--preset--spacing--70)           // 10rem (160px) - page-level gaps

// Typography
var(--wp--preset--font-size--body-tiny)
var(--wp--preset--font-size--body-small)
var(--wp--preset--font-size--body-default)
var(--wp--preset--font-size--body-large)
var(--wp--preset--font-size--heading-medium)
var(--wp--preset--font-size--heading-large)

// Font Families
var(--wp--preset--font-family--roobert)
var(--wp--preset--font-family--reckless-neue)
var(--wp--preset--font-family--fira-code)
```

### BEM Methodology

```scss
.block__element--modifier

// Example
.project-card { }           // Block
.project-card__title { }    // Element
.project-card--featured { } // Modifier
```

### Container Queries

Blocks respond to their container width, not the viewport:

```scss
.my-block {
    container: my-block / inline-size;
    flex-direction: column;

    @include container(my-block, lg) {
        flex-direction: row;
    }
}
```

Breakpoints: `sm` (576px), `md` (768px), `lg` (992px), `xl` (1440px)

### Asset Compilation

Assets live inside each provider's directory. The build system auto-discovers them:

| Source | Output |
|--------|--------|
| `src/Providers/*/assets/scss/index.scss` | `dist/css/{slug}.css` |
| `src/Providers/*/assets/js/*.js` | `dist/js/{slug}/*.js` |
| `src/Providers/*/blocks/*/editor/index.js` | `dist/js/{block}.js` |
| `src/Providers/*/blocks/*/frontend/view.js` | `dist/js/{block}-view.js` |
| `src/Providers/*/blocks/*/frontend/style.scss` | `dist/css/{block}.css` |

---

## Testing

Uses PHPUnit 9 with WorDBless for WordPress function stubs.

```bash
composer test              # All tests
composer test:unit         # Unit tests only
composer test:integration  # Integration tests only
make test                  # Both themes
```

### Test Structure

```
tests/
├── bootstrap.php
├── Fixtures/
│   └── svg/                          # Test SVG fixtures
├── Integration/
│   ├── Providers/
│   │   ├── Features/                 # ButtonIconEnhancer, CoverBlockStyles
│   │   ├── ProjectProviderTest.php
│   │   └── ThemeProviderTest.php
│   └── ThemeTest.php
```

---

## Development

### Adding Icons

- **Sprite icons:** Add SVG to `src/Providers/Theme/assets/images/svg-sprite/`
- **Standalone SVGs:** Add to `src/Providers/Theme/assets/images/svg/`
- **In Twig:** `{{ icon('icon-name') }}`
- **In PHP:** `new IconService('icon-name')`

### Adding a Provider with Assets

1. Create `src/Providers/MyFeature/MyFeatureProvider.php`
2. Add `assets/scss/index.scss` and/or `assets/js/*.js`
3. Run `make build` — assets are auto-discovered

---

## License

MIT
