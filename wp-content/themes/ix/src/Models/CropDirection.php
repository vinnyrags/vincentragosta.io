<?php

declare(strict_types=1);

namespace IX\Models;

/**
 * Crop direction for image resizing.
 *
 * Maps to Timber's ImageHelper::resize() crop parameter values.
 */
enum CropDirection: string
{
    case NONE = 'default';
    case CENTER = 'center';
    case TOP = 'top';
    case BOTTOM = 'bottom';
    case LEFT = 'left';
    case RIGHT = 'right';
}
