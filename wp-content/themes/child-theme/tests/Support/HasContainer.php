<?php

namespace ChildTheme\Tests\Support;

use DI\Container;
use DI\ContainerBuilder;

trait HasContainer
{
    protected function buildTestContainer(array $overrides = []): Container
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        if (!empty($overrides)) {
            $builder->addDefinitions($overrides);
        }

        return $builder->build();
    }
}
