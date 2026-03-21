<?php

declare(strict_types=1);

namespace IX\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;

class TermsQuerySupports implements Hook
{
    public function register(): void
    {
        add_filter('register_block_type_args', [$this, 'addSpacingSupports'], 10, 2);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function addSpacingSupports(array $args, string $name): array
    {
        if ($name !== 'core/terms-query') {
            return $args;
        }

        $args['supports']['spacing'] = array_merge(
            $args['supports']['spacing'] ?? [],
            ['margin' => true],
        );

        return $args;
    }
}