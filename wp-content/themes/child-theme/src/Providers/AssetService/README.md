# AssetServiceProvider

Extends the parent theme's AssetServiceProvider to add child theme-specific assets.

## What It Adds

### Google Fonts

Enqueues Fira Code from Google Fonts:

```
https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600;700&display=swap
```

### Preconnect Hints

Adds preconnect links in `<head>` for faster font loading:

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```

## Handle Prefix

All asset handles are prefixed with `child-theme`:

- `child-theme-style`
- `child-theme-frontend-js`
- `child-theme-blocks-js`
- etc.

## Inherited from Parent

The parent provider handles:

- Main stylesheet (`dist/css/main.css`)
- Frontend JavaScript (`dist/js/frontend.js`)
- Block scripts and styles
- Editor scripts

## Customizing

To add more assets, override the appropriate method:

```php
public function enqueueFrontendAssets(): void
{
    // Add your assets first
    wp_enqueue_style('my-custom-style', ...);

    // Then call parent
    parent::enqueueFrontendAssets();
}
```
