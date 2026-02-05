# AssetManager

Manages asset path resolution and enqueueing for service providers.

## Overview

Each provider gets its own `AssetManager` instance during `boot()`. The manager resolves file paths and URIs within the `dist/` directory and handles WordPress `wp_enqueue_style` / `wp_enqueue_script` calls.

## Path Conventions

| Asset Type | Path Pattern |
|------------|-------------|
| Styles | `dist/css/{filename}` |
| Scripts | `dist/js/{provider-slug}/{filename}` |
| Dist styles | `dist/{path}` (any relative path) |
| Dist scripts | `dist/{path}` (any relative path) |
| Manifest scripts | `dist/{path}` with sibling `.asset.php` |

The provider slug is derived from the class name via `slugify()`:

```
ThemeProvider     -> theme
ProjectProvider   -> project
```

## API

### Static Methods

| Method | Description |
|--------|-------------|
| `slugify(string $className)` | Convert PascalCase class name to kebab-case slug, stripping `Provider` suffix |

### Instance Methods

| Method | Description |
|--------|-------------|
| `hasStyle($filename)` | Check if a style exists in `dist/css/` |
| `hasScript($filename)` | Check if a script exists in `dist/js/{slug}/` |
| `getStylePath($filename)` | Get absolute path to a style file, or null |
| `getStyleUri($filename)` | Get URI to a style file |
| `getScriptPath($filename)` | Get absolute path to a script file, or null |
| `getScriptUri($filename)` | Get URI to a script file |
| `enqueueStyle($handle, $filename, $deps)` | Enqueue a stylesheet from `dist/css/` |
| `enqueueScript($handle, $filename, $deps, $inFooter)` | Enqueue a script from `dist/js/{slug}/` |
| `enqueueDistStyle($handle, $path, $deps)` | Enqueue a stylesheet from any `dist/` path |
| `enqueueDistScript($handle, $path, $deps, $inFooter)` | Enqueue a script from any `dist/` path |
| `enqueueManifestScript($handle, $path, $extraDeps, $inFooter)` | Enqueue a script using a `.asset.php` manifest for deps and version |

All enqueue methods skip silently if the file doesn't exist on disk. Version cache-busting uses `filemtime()` (or the manifest version for `enqueueManifestScript`).

## Usage

`AssetManager` is not used directly -- `ServiceProvider` exposes wrapper methods that delegate to it:

```php
// In a provider class
$this->enqueueStyle('my-handle', 'theme.css');
$this->enqueueScript('my-handle', 'button.js', ['wp-element']);
$this->enqueueDistStyle('my-handle', 'blocks/style-index.css');
$this->enqueueManifestScript('my-handle', 'blocks/index.js');
```
