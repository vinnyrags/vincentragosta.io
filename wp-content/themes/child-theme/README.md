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
| `make watch` | Start watch mode for development |
| `make clean` | Remove all generated files |
| `make autoload` | Regenerate Composer autoloaders |

## Project Structure

```
child-theme/
├── config/                        # JSON configuration files
│   └── *.json                     # Post type definitions
├── dist/                          # Compiled assets (gitignored)
├── src/
│   ├── Providers/                 # Service providers (assets live here)
│   ├── Services/
│   │   └── IconService.php        # SVG icon handling
│   └── Theme.php                  # Main theme class
├── views/                         # Twig templates
├── theme.json                     # Block editor configuration
└── docs/                          # Implementation plans
```

---

## Architecture

### Hybrid Theme Approach

This theme combines traditional Timber/Twig templating with the Block Editor:

```
┌─────────────────────────────────────────────────────────┐
│                     WordPress                            │
├─────────────────────────────────────────────────────────┤
│  Timber/Twig Templates          │  Block Editor Content  │
│  ─────────────────────          │  ───────────────────── │
│  • base.twig (layout)           │  • Page content        │
│  • header.twig                  │  • Custom blocks       │
│  • footer.twig                  │  • Core blocks         │
├─────────────────────────────────────────────────────────┤
│                    theme.json                            │
│            (Shared design tokens & settings)             │
└─────────────────────────────────────────────────────────┘
```

### Parent Theme Relationship

This theme extends `parent-theme` which provides:

- **Contracts:** `Registrable`, `HasAssets`
- **Traits:** `HasAssets` (asset enqueueing)
- **Base Classes:** `ServiceProvider`, `Theme`
- **Features:** DisableBlocks, DisableComments, EnableSvgUploads

### Bootstrap Flow

```php
// functions.php
$parent_autoloader = get_template_directory() . '/vendor/autoload.php';
require_once $parent_autoloader;
require_once __DIR__ . '/vendor/autoload.php';

Timber\Timber::init();
new \ChildTheme\Theme();
```

### Service Providers

| Provider | Purpose |
|----------|---------|
| `ThemeServiceProvider` | Theme setup, admin bar, theme CSS |
| `AssetServiceProvider` | Frontend/editor assets, Google Fonts |
| `BlockServiceProvider` | Block registration, editor data |
| `PostTypeServiceProvider` | CPTs from JSON config |
| `TwigServiceProvider` | Custom Twig functions (`icon()`) |

---

## Custom Blocks

### Available Blocks

| Block | Name | Description |
|-------|------|-------------|
| Hero | `child-theme/hero` | Full-width hero with SVG/video background |
| Projects | `child-theme/projects` | Asymmetric project grid |
| Shutter Cards | `child-theme/shutter-cards` | Accordion card container |
| Shutter Card | `child-theme/shutter-card` | Individual expanding card |

### Block File Structure

```
blocks/hero/
├── block.json      # Block metadata (required)
├── index.js        # Block registration
├── edit.js         # Editor component (React)
├── save.js         # Save function (or null for dynamic)
├── render.php      # Server-side rendering
├── style.scss      # Frontend + editor styles
└── editor.scss     # Editor-only styles
```

### Creating a New Block

1. Create block directory: `blocks/{block-name}/`

2. Create `block.json`:
```json
{
  "apiVersion": 3,
  "name": "child-theme/my-block",
  "title": "My Block",
  "category": "design",
  "render": "file:./render.php"
}
```

3. Create edit component (`edit.js`):
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

4. Create server render (`render.php`):
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

5. Register in `BlockServiceProvider`:
```php
protected array $blocks = ['hero', 'projects', 'my-block'];
```

6. Add to `blocks/index.js`:
```javascript
import './my-block';
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

| Source | Output |
|--------|--------|
| `src/Providers/*/assets/scss/index.scss` | `dist/css/{provider}.css` |
| `src/Providers/*/assets/js/*.js` | `dist/js/{provider}/*.js` |
| `src/Providers/*/blocks/*/editor/index.js` | `dist/js/{block}.js` |
| `src/Providers/*/blocks/*/frontend/view.js` | `dist/js/{block}-view.js` |
| `src/Providers/*/blocks/*/frontend/style.scss` | `dist/css/{block}.css` |

---

## Testing

### PHP Testing

Uses PHPUnit with WorDBless. Tests run automatically on commit.

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests only
composer test:integration

# Both themes
npm run test:php:all
```

#### Test Structure

```
tests/
├── bootstrap.php
├── Unit/
│   └── Services/
│       └── IconServiceTest.php
└── Integration/
    ├── Providers/
    │   └── AssetServiceProviderTest.php
    └── ThemeTest.php
```

#### Writing Tests

**Unit Test:**
```php
namespace ChildTheme\Tests\Unit\Services;

use ChildTheme\Services\IconService;
use PHPUnit\Framework\TestCase;

class IconServiceTest extends TestCase
{
    public function testWithClassAddsClass(): void
    {
        $service = IconService::get('test')->withClass('icon-lg');
        // assertions...
    }
}
```

**Integration Test:**
```php
namespace ChildTheme\Tests\Integration;

use ChildTheme\Theme;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class ThemeTest extends TestCase
{
    public function testProvidersAreRegistered(): void
    {
        $theme = new Theme();
        // assertions...
    }
}
```

### Pre-Commit Hook

Tests run automatically when PHP files are staged. Bypass with:

```bash
git commit --no-verify
```

---

## Development

### Adding Icons

- **Sprite icons:** Add SVG to `src/Providers/Theme/assets/images/svg-sprite/`
- **Standalone SVGs:** Add to `src/Providers/Theme/assets/images/svg/`
- **In Twig:** `{{ icon('icon-name') }}`
- **In PHP:** `new IconService('icon-name')`

### Adding a Provider with Assets

1. Create `src/Providers/YourService/YourServiceProvider.php`
2. Add `assets/scss/main.scss` and/or `assets/js/*.js`
3. Run `make build` — assets are auto-discovered

---

## Implementation Plans

See `docs/` for future implementation plans:

- `CHANGELOG-RELEASES.md` - Conventional commits and release management
- `FRONTEND-TESTING.md` - Frontend JavaScript testing strategy

---

## License

MIT
