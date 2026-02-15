<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Contracts;

/**
 * Marker interface for toggleable features.
 *
 * Classes implementing Feature can be opted out of via `ClassName::class => false`
 * in a child provider's $features array. Non-toggleable behavioral hooks should
 * implement Registrable directly and use the $hooks array instead.
 */
interface Feature extends Registrable {}
