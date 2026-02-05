# FeatureManager

Manages feature registration for providers with support for inheritance and opt-out.

## Overview

`FeatureManager` extends `AbstractRegistry` and receives a normalized map of `[class-string => bool]`. It only implements `registerAll()` — the shared registry logic (`normalize`, `isEnabled`, `getEnabled`, `getDisabled`) is inherited from the base class. It works with `Provider::collectFeatures()`, which walks the class hierarchy to merge parent and child feature arrays.

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

### Inherited from AbstractRegistry

| Method | Description |
|--------|-------------|
| `normalize(array $items)` | Static. Convert a mixed array into `[class => bool]`. Indexed entries become `true`, associative entries preserve their value. |
| `isEnabled(string $item)` | Check if a specific feature is enabled (returns `false` for unknown features) |
| `getEnabled()` | Get all enabled feature class names |
| `getDisabled()` | Get all disabled feature class names |

### Own Methods

| Method | Description |
|--------|-------------|
| `registerAll()` | Resolve each enabled feature from the DI container as a `Registrable` and call `register()` |

## How It Works

1. `Provider::init()` calls `collectFeatures()`, which walks the class hierarchy via reflection
2. Each level's `$features` array is normalized via `AbstractRegistry::normalize()`
3. Arrays are merged bottom-up so child entries override parent entries for the same class
4. The merged map is passed to the `FeatureManager` constructor
5. `registerFeatures()` calls `$this->featureManager->registerAll()`, which instantiates and registers only the enabled features
