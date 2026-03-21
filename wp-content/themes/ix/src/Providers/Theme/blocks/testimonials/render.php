<?php
/**
 * Server-side rendering for the Testimonials block.
 */

use Timber\Timber;

$inner_blocks = $block?->inner_blocks ?? [];

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
Timber::render(__DIR__ . '/testimonials.twig', $context);
echo '</div>';
