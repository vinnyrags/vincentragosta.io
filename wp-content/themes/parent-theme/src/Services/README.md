# Services

Shared, injectable services that providers and features consume via constructor injection.

## Overview

Services are stateless (or singleton-scoped) classes that encapsulate reusable logic. They live outside the provider hierarchy and are resolved by PHP-DI's autowiring. Any provider or feature can receive a service via its constructor without manual container configuration.

## When to Create a Service

- The logic is **shared** across multiple consumers (providers, features, Twig functions)
- The class wraps a **third-party library** with project-specific configuration
- You need to **mock** the dependency in tests
- The dependency has its **own dependencies** that benefit from autowiring

Use `new` directly instead when creating value objects, WordPress core objects (`WP_Query`), or classes with provider-specific runtime arguments.

## Current Services

| Class | Purpose |
|-------|---------|
| `IconService` | Value object that resolves and renders SVG icons from theme directories. Created via `IconServiceFactory`, not directly. |
| `IconServiceFactory` | Factory for creating `IconService` instances. Provides `create()`, `all()`, `options()`, and `contentMap()` methods. Injected into providers and features that work with icons. |
| `SvgSanitizerService` | Wraps `enshrined/svg-sanitize` with preconfigured security settings. Injected into `EnableSvgUploads` for SVG upload sanitization. |

## Usage

Services are autowired — declare them as constructor parameters:

```php
class MyFeature implements Registrable
{
    public function __construct(
        private readonly IconServiceFactory $iconFactory,
    ) {}

    public function register(): void
    {
        // Use $this->iconFactory->create('arrow') etc.
    }
}
```

No container definitions needed. PHP-DI resolves the service automatically.
