# FeatureManager

Manages feature registration for providers with support for inheritance and opt-out.

## Overview

`FeatureManager` extends `AbstractRegistry` and receives a normalized map of `[class-string => bool]`. It only implements `registerAll()` — the shared registry logic (`normalize`, `isEnabled`, `getEnabled`, `getDisabled`) is inherited from the base class. It works with `Provider::collectFeatures()`, which walks the class hierarchy to merge parent and child feature arrays.

## Features vs Hooks

The provider system distinguishes two kinds of registrable classes:

- **Features** (`$features` array, `Features/` directory) — toggleable capabilities that implement the `Feature` interface (which extends `Registrable`). Child providers can opt out via `ClassName::class => false`. Managed by `FeatureManager`.
- **Hooks** (`$hooks` array, `Hooks/` directory) — always-active structural behavior that implements the `Hook` interface (not `Feature`). Additive only — no opt-out. Registered directly by `Provider::registerHooks()`, which validates the `Hook` interface symmetrically.

`FeatureManager::registerAll()` validates that each class implements the `Feature` interface. Classes that only implement `Registrable` are skipped with a warning — they belong in `$hooks` instead.

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

// Child ThemeProvider -- parent features inherited, child hooks separate
protected array $hooks = [
    ButtonIconEnhancer::class,
    CoverBlockStyles::class,
];
```

Result: all 4 parent features are registered via FeatureManager, and the 2 child hooks are registered via `registerHooks()`.

## Opt-Out

To disable an inherited parent feature, use the associative `=> false` syntax in `$features`:

```php
// Child ThemeProvider
protected array $features = [
    DisablePosts::class => false,  // opt out of parent feature
];
```

Result: `DisablePosts` is excluded. The remaining 3 parent features are registered. Note: hooks cannot be opted out of — they are always-active.

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
| `registerAll()` | Resolve each enabled feature from the DI container, validate it implements `Feature`, and call `register()` |

## How It Works

1. `Provider::setup()` calls `collectFeatures()`, which walks the class hierarchy via reflection
2. Each level's `$features` array is normalized via `AbstractRegistry::normalize()`
3. Arrays are merged bottom-up so child entries override parent entries for the same class
4. The merged map is passed to the `FeatureManager` constructor
5. `registerFeatures()` calls `$this->featureManager->registerAll()`, which validates and registers only the enabled features
6. Classes not implementing `Feature` are skipped with a log warning
