<?php
/**
 * Server-side rendering for the Shutter Cards container block.
 */

use Timber\Timber;

$inner_blocks = isset($block) ? $block->inner_blocks : [];

// Render inner blocks to HTML strings.
$rendered_inner_blocks = [];
foreach ($inner_blocks as $inner_block) {
    $rendered_inner_blocks[] = $inner_block->render();
}

$context = Timber::context();
$context['inner_blocks'] = $rendered_inner_blocks;

// Get block wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
Timber::render(__DIR__ . '/../templates/container.twig', $context);
echo '</div>';
