# RestManager

Manages REST API endpoint registration for providers with support for inheritance and opt-out.

## Overview

`RestManager` extends `AbstractRegistry` and receives a normalized map of `[class-string => bool]`. The shared registry logic (`normalize`, `isEnabled`, `getEnabled`, `getDisabled`) is inherited from the base class. It works with `Provider::collectRoutes()`, which walks the class hierarchy to merge parent and child route arrays.

Each endpoint is a class extending `Endpoint` (which implements the `Routable` contract) that defines its route, HTTP methods, callback, and permission logic. Endpoints are resolved from the DI container, so they can inject services and repositories.

## Endpoint Class

Every REST endpoint extends the abstract `Endpoint` base class, which implements the `Routable` contract (`getRoute()` + `toRouteArgs()`):

```php
use ParentTheme\Providers\Support\Rest\Endpoint;

class HealthEndpoint extends Endpoint
{
    public function getRoute(): string
    {
        return '/health';
    }

    public function getMethods(): string|array
    {
        return 'GET';
    }

    public function callback(\WP_REST_Request $request): \WP_REST_Response|\WP_Error|array
    {
        return ['status' => 'ok'];
    }

    public function getPermission(\WP_REST_Request $request): bool|\WP_Error
    {
        return true;
    }
}
```

Four methods are abstract: `getRoute()`, `getMethods()`, `callback()`, and `getPermission()`. Permissions are intentionally not defaulted to `true` — every endpoint must make an explicit access decision.

`getArgs()` returns an empty array by default and can be overridden to define parameter validation. `toRouteArgs()` assembles the full `register_rest_route()` argument array and can be overridden for unusual cases.

## Route Inheritance

Routes declared in a parent provider's `$routes` array are automatically inherited by child providers. Child providers only need to declare their own routes:

```php
// Parent ProjectProvider
protected array $routes = [
    ProjectsEndpoint::class,
    ProjectDetailEndpoint::class,
];

// Child ProjectProvider -- only child-specific routes needed
protected array $routes = [
    ProjectSearchEndpoint::class,
];
```

Result: all 3 endpoints are registered.

## Opt-Out

To disable an inherited parent route, use the associative `=> false` syntax:

```php
// Child ProjectProvider
protected array $routes = [
    ProjectSearchEndpoint::class,
    ProjectDetailEndpoint::class => false,  // opt out of parent route
];
```

Result: `ProjectDetailEndpoint` is excluded. The remaining 2 endpoints are registered.

## Namespace

The REST namespace defaults to the provider slug + version: e.g. `project/v1`. Override via provider properties:

```php
protected string $routeNamespace = 'my-api';  // defaults to provider slug
protected string $routeVersion = 'v2';         // defaults to 'v1'
```

This produces the namespace `my-api/v2`, so a route `/health` registers at `/wp-json/my-api/v2/health`.

## API

### Endpoint (Abstract)

| Method | Description |
|--------|-------------|
| `getRoute()` | Route pattern (e.g. `/health`, `/items/(?P<id>\d+)`) |
| `getMethods()` | HTTP methods (`string` or `array`) |
| `callback($request)` | Handle the request, return response |
| `getPermission($request)` | Permission check, return `bool` or `WP_Error` |
| `getArgs()` | Argument schema (default: empty array) |
| `toRouteArgs()` | Assemble `register_rest_route()` args array |

### RestManager

#### Inherited from AbstractRegistry

| Method | Description |
|--------|-------------|
| `normalize(array $items)` | Static. Convert a mixed array into `[class => bool]`. Indexed entries become `true`, associative entries preserve their value. |
| `isEnabled(string $item)` | Check if a specific route is enabled (returns `false` for unknown routes) |
| `getEnabled()` | Get all enabled route class names |
| `getDisabled()` | Get all disabled route class names |

#### Own Methods

| Method | Description |
|--------|-------------|
| `getNamespace()` | Get the REST namespace string |
| `registerAll()` | Resolve each enabled endpoint from the DI container and call `register_rest_route()` |

## How It Works

1. `Provider::init()` calls `collectRoutes()`, which walks the class hierarchy via reflection
2. Each level's `$routes` array is normalized via `AbstractRegistry::normalize()`
3. Arrays are merged bottom-up so child entries override parent entries for the same class
4. The merged map is passed to the `RestManager` constructor along with the namespace
5. `Provider::register()` hooks `registerRoutes()` to `rest_api_init` if any routes are enabled
6. On `rest_api_init`, `RestManager::registerAll()` resolves each endpoint from the container and calls `register_rest_route()`
