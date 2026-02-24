# BlockManager

Manages block registration and editor script enqueueing for providers.

## Overview

Each provider gets its own `BlockManager` instance during `setup()`. The manager handles WordPress `register_block_type` calls and hooks for block asset enqueueing.

## Block Directory Structure

Blocks live in a `blocks/` subdirectory relative to the provider class file. Each block directory must contain a `block.json` file:

```
Theme/
├── ThemeProvider.php
└── blocks/
    ├── shutter-cards/
    │   ├── block.json
    │   ├── render.php
    │   ├── style.scss
    │   ├── container.twig
    │   └── editor/
    └── shutter-card/
        ├── block.json
        ├── render.php
        ├── style.scss
        ├── card.twig
        └── editor/
```

## API

| Method | Description |
|--------|-------------|
| `getBlocks()` | Get the list of block directory names |
| `getBlocksPath()` | Get the absolute path to the blocks directory |
| `getBlocksUri()` | Get the URI to the blocks directory |
| `registerBlocks()` | Register all blocks via `register_block_type` (skips blocks without `block.json`) |
| `enqueueEditorScript($handle, $filename, $deps)` | Enqueue a block editor script from `dist/js/` with default WordPress dependencies |
| `initializeHooks($provider)` | Register `init`, `enqueue_block_assets`, and `enqueue_block_editor_assets` hooks |

### Default Editor Script Dependencies

`enqueueEditorScript` automatically includes:

- `wp-blocks`
- `wp-element`
- `wp-block-editor`
- `wp-components`
- `wp-i18n`
- `wp-data`

Additional dependencies can be passed as the third argument.

### Hook Initialization

`initializeHooks` is called during `Provider::register()` and is a no-op when the provider has no blocks. When blocks are present, it registers:

- `init` -> `registerBlocks()`
- `enqueue_block_assets` -> `$provider->enqueueBlockAssets()`
- `enqueue_block_editor_assets` -> `$provider->enqueueBlockEditorAssets()`

## Usage

`BlockManager` is not used directly -- providers declare blocks via the `$blocks` property and override the asset methods:

```php
class MyProvider extends Provider
{
    protected array $blocks = ['my-block'];

    public function enqueueBlockAssets(): void
    {
        $this->enqueueStyle('my-block-style', 'my-block.css');
    }

    public function enqueueBlockEditorAssets(): void
    {
        $this->enqueueEditorScript('my-block-editor', 'my-block.js');
    }
}
```
