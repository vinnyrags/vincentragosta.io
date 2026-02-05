<?php

namespace ParentTheme\Tests\Support;

use DI\Container;
use DI\ContainerBuilder;

trait HasContainer
{
    protected function buildTestContainer(array $overrides = []): Container
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        $parentDefs = dirname(__DIR__, 2) . '/src/config/container.php';
        if (file_exists($parentDefs)) {
            $builder->addDefinitions($parentDefs);
        }

        if (!empty($overrides)) {
            $builder->addDefinitions($overrides);
        }

        return $builder->build();
    }
}
