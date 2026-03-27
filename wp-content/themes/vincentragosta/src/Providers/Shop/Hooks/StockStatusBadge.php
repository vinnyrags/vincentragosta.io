<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * Adds visual stock status indicators to product cards.
 */
class StockStatusBadge implements Hook
{
    public function register(): void
    {
        // Visual indicator logic will be added in the polish phase.
    }
}
