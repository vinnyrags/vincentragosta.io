<?php
/**
 * Server-side rendering for the Shutter Cards container block.
 */

use Timber\Timber;

$inner_blocks = isset($block) ? $block->inner_blocks : [];
$card_count = count($inner_blocks);
$container_styles = '';

// If we have more than one card, calculate the inactive width and set it as a CSS variable.
if ($card_count > 1) {
    $inactive_width_percentage = 60 / $card_count;
    $container_styles = sprintf('--card-inactive-width: %f%%;', $inactive_width_percentage);
}

// Render inner blocks to HTML strings.
$rendered_inner_blocks = [];
foreach ($inner_blocks as $inner_block) {
    $rendered_inner_blocks[] = $inner_block->render();
}

$context = Timber::context();
$context['inner_blocks'] = $rendered_inner_blocks;
$context['container_styles'] = $container_styles;

// Get block wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
Timber::render('blocks/shutter-cards/templates/container.twig', $context);
echo '</div>';
