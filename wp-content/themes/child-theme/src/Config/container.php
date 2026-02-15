<?php

declare(strict_types=1);

use ParentTheme\Services\IconServiceFactory;

return [
    IconServiceFactory::class => DI\autowire()
        ->constructorParameter('svgDir', '/src/Providers/Theme/assets/images/svg/'),
];
