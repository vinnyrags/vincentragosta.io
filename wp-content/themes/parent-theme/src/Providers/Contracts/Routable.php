<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Contracts;

/**
 * Interface for classes that define a REST API route.
 */
interface Routable
{
    /**
     * The route pattern (e.g. '/health', '/projects/(?P<id>\d+)').
     */
    public function getRoute(): string;

    /**
     * Assemble the args array for register_rest_route().
     */
    public function toRouteArgs(): array;
}
