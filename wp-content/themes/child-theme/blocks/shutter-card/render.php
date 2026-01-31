<?php
/**
 * Server-side rendering for the Shutter Card block.
 */

use Timber\Timber;

$context = Timber::context();
$context['title'] = $attributes['title'] ?? '';
$context['subtitle'] = $attributes['subtitle'] ?? '';
$context['description'] = $attributes['description'] ?? '';
$context['card_index'] = $attributes['cardIndex'] ?? '00';

// Get block wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
Timber::render('blocks/shutter-card/shutter-card.twig', $context);
echo '</div>';
