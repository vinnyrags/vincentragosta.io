<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Contracts;

/**
 * Marker interface for always-active hook classes.
 *
 * Classes implementing Hook are registered via the $hooks array and are
 * always active — inheritance is additive only, no opt-out. For toggleable
 * capabilities that support opt-out via => false, use Feature instead.
 */
interface Hook extends Registrable {}
