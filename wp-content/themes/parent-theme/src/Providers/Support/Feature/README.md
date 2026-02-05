# FeatureManager

Manages feature registration for service providers with support for inheritance and opt-out.

## Overview

`FeatureManager` receives a normalized map of `[class-string => bool]` and handles filtering and registration. It works with `ServiceProvider::collectFeatures()`, which walks the class hierarchy to merge parent and child feature arrays.

## Feature Inheritance

Features declared in a parent provider's `$features` array are automatically inherited by child providers. Child providers only need to declare their own features:

```php
// Parent ThemeProvider
protected array $features = [
    DisableBlocks::class,
    DisableComments::class,
    DisablePosts::class,
    EnableSvgUploads::class,
];

// Child ThemeProvider -- only child-specific features needed
protected array $features = [
    ButtonIconEnhancer::class,
    CoverBlockStyles::class,
];
```

Result: all 6 features are registered.

## Opt-Out

To disable an inherited parent feature, use the associative `=> false` syntax:

```php
// Child ThemeProvider
protected array $features = [
    ButtonIconEnhancer::class,
    CoverBlockStyles::class,
    DisablePosts::class => false,  // opt out of parent feature
];
```

Result: `DisablePosts` is excluded. The remaining 5 features are registered.

## API

### Static Methods

| Method | Description |
|--------|-------------|
| `normalize(array $features)` | Convert a mixed features array into `[class => bool]`. Indexed entries become `true`, associative entries preserve their value. |

### Instance Methods

| Method | Description |
|--------|-------------|
| `isEnabled(string $feature)` | Check if a specific feature is enabled (returns `false` for unknown features) |
| `getEnabled()` | Get all enabled feature class names |
| `getDisabled()` | Get all disabled feature class names |
| `registerAll()` | Instantiate and call `register()` on all enabled features |

## How It Works

1. `ServiceProvider::init()` calls `collectFeatures()`, which walks the class hierarchy via reflection
2. Each level's `$features` array is normalized via `FeatureManager::normalize()`
3. Arrays are merged bottom-up so child entries override parent entries for the same class
4. The merged map is passed to the `FeatureManager` constructor
5. `registerFeatures()` calls `$this->featureManager->registerAll()`, which instantiates and registers only the enabled features
