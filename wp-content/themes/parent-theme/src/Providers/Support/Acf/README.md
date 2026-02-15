# AcfManager

Manages ACF JSON sync paths and options page auto-discovery for providers.

## Overview

Each provider gets its own `AcfManager` instance during `setup()`. If the provider has an `acf-json/` directory, the manager automatically registers it as an ACF JSON load path and discovers options page definitions. Silent if the directory doesn't exist.

## Directory Structure

Place ACF field group JSON and options page JSON files in an `acf-json/` directory alongside your provider class:

```
Project/
├── ProjectProvider.php
├── acf-json/
│   ├── group_project_details.json       # Field group (auto-loaded)
│   └── options-page-site-settings.json  # Options page (auto-registered)
└── ...
```

Files are distinguished by filename prefix:

| Prefix | Type | Registration |
|--------|------|-------------|
| `group_*.json` | ACF field group | Auto-loaded via `acf/settings/load_json` |
| `options-page-*.json` | Options page | Auto-registered via `acf/init` |

## Options Page JSON Schema

Options page files use the same keys as `acf_add_options_page()`:

```json
{
    "page_title": "Site Settings",
    "menu_title": "Site Settings",
    "menu_slug": "site-settings",
    "capability": "edit_posts",
    "redirect": false,
    "icon_url": "dashicons-admin-settings",
    "position": 59
}
```

- `menu_slug` is **required** — files without it are skipped with a log warning
- `page_title` and `menu_title` are automatically translated using the provider's text domain
- All other keys are passed through to `acf_add_options_page()` as-is

### Sub-Pages

To register a sub-page, include a `parent_slug` key. The manager will call `acf_add_options_sub_page()` instead:

```json
{
    "page_title": "Advanced Settings",
    "menu_title": "Advanced",
    "menu_slug": "advanced-settings",
    "parent_slug": "site-settings",
    "capability": "manage_options"
}
```

## API

| Method | Description |
|--------|-------------|
| `hasAcfJson()` | Whether the provider has an `acf-json/` directory |
| `getAcfJsonPath()` | Get the absolute path to the `acf-json/` directory |
| `initializeHooks()` | Register `acf/settings/load_json` filter and `acf/init` action (no-op without directory) |
| `addLoadPath($paths)` | Filter callback that adds the path to ACF's load paths |
| `registerSavePath()` | Optional — sets `acf/settings/save_json` to this provider's directory |
| `registerOptionsPages()` | Discovers `options-page-*.json` files and registers them (called on `acf/init`) |

## Usage

`AcfManager` is not used directly. Any provider with an `acf-json/` directory automatically gets ACF JSON sync and options page auto-discovery. To also make the provider the save target:

```php
class MyProvider extends Provider
{
    public function register(): void
    {
        $this->acfManager->registerSavePath();

        parent::register();
    }
}
```
