<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Support\Rest;

use DI\Container;
use ParentTheme\Providers\Support\AbstractRegistry;

/**
 * Manages REST API endpoint registration for providers.
 *
 * Accepts a normalized route map of [class-string => bool] and handles
 * filtering and registration. Routes set to false are excluded.
 */
class RestManager extends AbstractRegistry
{
    public function __construct(
        /** @var array<class-string<Endpoint>, bool> */
        array $routes,
        Container $container,
        private readonly string $namespace,
    ) {
        parent::__construct($routes, $container);
    }

    /**
     * Get the REST namespace (e.g. 'theme/v1').
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Resolve and register all enabled endpoints via register_rest_route().
     */
    public function registerAll(): void
    {
        foreach ($this->getEnabled() as $routeClass) {
            /** @var Endpoint $endpoint */
            $endpoint = $this->container->get($routeClass);
            register_rest_route($this->namespace, $endpoint->getRoute(), $endpoint->toRouteArgs());
        }
    }
}
