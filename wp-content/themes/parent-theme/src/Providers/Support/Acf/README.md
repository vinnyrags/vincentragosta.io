# AcfManager

Manages ACF JSON sync paths for providers.

## Overview

Each provider gets its own `AcfManager` instance during `setup()`. If the provider has an `acf-json/` directory, the manager automatically registers it as an ACF JSON load path. Silent if the directory doesn't exist.

## Directory Structure

Place ACF field group JSON files in an `acf-json/` directory alongside your provider class:

```
Project/
├── ProjectProvider.php
├── acf-json/
│   └── group_project_details.json
└── ...
```

## API

| Method | Description |
|--------|-------------|
| `hasAcfJson()` | Whether the provider has an `acf-json/` directory |
| `getAcfJsonPath()` | Get the absolute path to the `acf-json/` directory |
| `initializeHooks()` | Register the `acf/settings/load_json` filter (no-op without directory) |
| `addLoadPath($paths)` | Filter callback that adds the path to ACF's load paths |
| `registerSavePath()` | Optional — sets `acf/settings/save_json` to this provider's directory |

## Usage

`AcfManager` is not used directly. Any provider with an `acf-json/` directory automatically gets ACF JSON sync. To also make the provider the save target:

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
