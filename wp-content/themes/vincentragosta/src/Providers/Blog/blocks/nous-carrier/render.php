<?php
/**
 * Server-side rendering for the Nous Signal Carrier block.
 *
 * Every transmission as a bar on a day axis running to today, so a silent
 * feed shows as a flat line; a readout of the archive beneath (view model:
 * NousCarrier; hover readout and bar links: view.js).
 */

declare(strict_types=1);

use ChildTheme\Providers\Blog\NousCarrier;
use IX\Providers\Blog\BlogRepository;
use IX\Theme;
use Timber\Timber;

$repository = Theme::container()->get(BlogRepository::class);
$posts = $repository->query([
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);

$carrier = (new NousCarrier($posts, current_datetime()))->toArray();

if (!$carrier) {
    return;
}

$context = Timber::context();
$context['carrier'] = $carrier;
$context['width'] = NousCarrier::WIDTH;
$context['height'] = NousCarrier::HEIGHT;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() is escaped by core.
echo '<div ' . get_block_wrapper_attributes(['class' => 'nous-carrier' . ($carrier['live'] ? ' is-live' : '')]) . '>';
Timber::render(__DIR__ . '/carrier.twig', $context);
echo '</div>';
