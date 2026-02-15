<?php

namespace ChildTheme\Tests\Support;

use DI\Container;
use DI\ContainerBuilder;

/**
 * Provides a DI container builder for test cases.
 *
 * Builds a real PHP-DI container with autowiring enabled, loads the child
 * theme's container definitions, and applies optional overrides for
 * stubbing or replacing services in tests.
 */
trait HasContainer
{
    /**
     * Build a PHP-DI container configured for testing.
     *
     * @param array<string, mixed> $overrides DI definitions to override autowired defaults.
     */
    protected function buildTestContainer(array $overrides = []): Container
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        $definitionsFile = dirname(__DIR__, 2) . '/src/Config/container.php';
        if (file_exists($definitionsFile)) {
            $builder->addDefinitions($definitionsFile);
        }

        if (!empty($overrides)) {
            $builder->addDefinitions($overrides);
        }

        return $builder->build();
    }
}
