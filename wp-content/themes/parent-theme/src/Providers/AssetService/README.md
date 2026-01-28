# AssetServiceProvider

Handles enqueueing of frontend and editor assets (scripts and styles).

## Hooks Registered

| Hook | Method | Priority |
|------|--------|----------|
| `wp_enqueue_scripts` | `enqueueFrontendAssets()` | 10 |
| `enqueue_block_editor_assets` | `enqueueEditorAssets()` | 10 |
| `enqueue_block_assets` | `enqueueBlockAssets()` | 10 |

## Assets Loaded

### Frontend (`wp_enqueue_scripts`)

| Asset | Path | Condition |
|-------|------|-----------|
| Main stylesheet | `dist/css/main.css` | Always (from parent theme) |
| Frontend JS | `dist/js/frontend.js` | If file exists |

### Block Editor (`enqueue_block_editor_assets`)

| Asset | Path | Dependencies |
|-------|------|--------------|
| Blocks JS | `dist/blocks/index.js` | From `index.asset.php` |
| Main editor JS | `dist/js/main.js` | `wp-element` + asset file deps |

### Block Assets (`enqueue_block_assets`)

| Asset | Path | Context |
|-------|------|---------|
| Block styles | `dist/blocks/style-index.css` | Frontend + Editor |
| Block editor styles | `dist/blocks/index.css` | Editor only |

## Configuration

### Handle Prefix

Override `$handlePrefix` in child themes to namespace asset handles:

```php
protected string $handlePrefix = 'child-theme';
```

This prefixes all registered handles (e.g., `child-theme-style`, `child-theme-blocks-js`).

## Extending

Child themes typically extend this provider to:

1. Add Google Fonts or external assets
2. Add preconnect/preload hints
3. Enqueue child-specific scripts

```php
class AssetServiceProvider extends BaseAssetServiceProvider
{
    protected string $handlePrefix = 'child-theme';

    public function register(): void
    {
        parent::register();
        add_action('wp_head', [$this, 'addPreconnects']);
    }

    public function enqueueFrontendAssets(): void
    {
        wp_enqueue_style('google-fonts', '...');
        parent::enqueueFrontendAssets();
    }
}
```
