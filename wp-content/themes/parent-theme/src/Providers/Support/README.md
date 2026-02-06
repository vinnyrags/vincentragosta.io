# Support

Internal infrastructure classes that power the provider system.

## Overview

Support classes are the composition layer between providers and WordPress. Each provider composes manager instances that handle a specific concern — assets, blocks, features, or REST endpoints. Managers are created with `new` in `Provider::setup()` because they receive provider-specific runtime arguments (paths, slugs, feature lists) that are computed from reflection.

## Structure

```
Support/
├── AbstractRegistry.php       # Base class for registry managers
├── Asset/
│   └── AssetManager.php       # CSS/JS path resolution and enqueueing
├── Block/
│   └── BlockManager.php       # Block registration and editor script hooks
├── Feature/
│   └── FeatureManager.php     # Feature resolution via DI container
└── Rest/
    ├── Endpoint.php           # Abstract base for REST endpoints
    └── RestManager.php        # REST route registration via DI container
```

Each subdirectory has its own README with detailed API documentation.

## AbstractRegistry

Base class shared by `FeatureManager` and `RestManager`. Provides the normalized `[class-string => bool]` map pattern with `normalize()`, `isEnabled()`, `getEnabled()`, and `getDisabled()`. Subclasses implement `registerAll()` to define how enabled items are resolved and registered.

## How Providers Use Managers

Managers are internal collaborators — providers expose wrapper methods that delegate to them:

```php
// Provider::setup() creates the managers
$this->assets = new AssetManager($slug, $distPath, $distUri);
$this->blockManager = new BlockManager($blocksPath, $blocksUri, ...);
$this->featureManager = new FeatureManager($this->collectFeatures(), $this->container);
$this->restManager = new RestManager($this->collectRoutes(), $this->container, ...);

// Provider::register() wires them into WordPress
$this->featureManager->registerAll();
$this->blockManager->initializeHooks($this);
```

## Why `new` Instead of DI

Managers are instantiated directly because:

1. **Provider-specific arguments** — each provider's managers receive different slugs, paths, and feature lists computed at runtime from reflection
2. **Not shared** — each provider creates its own manager instances
3. **No external dependencies** — managers depend on scalar args and WordPress APIs, not other services
