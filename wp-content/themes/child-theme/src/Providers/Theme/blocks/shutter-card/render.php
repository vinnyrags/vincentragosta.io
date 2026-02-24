<?php
/**
 * Server-side rendering for the Shutter Card block.
 */

use Timber\Timber;

$context = Timber::context();

// Sanitize block attributes - use wp_kses_post for fields that may contain simple HTML,
// esc_html for plain text fields to prevent XSS.
$context['title'] = esc_html($attributes['title'] ?? '');
$context['subtitle'] = esc_html($attributes['subtitle'] ?? '');
$context['description'] = wp_kses_post($attributes['description'] ?? '');
$context['card_index'] = esc_html($attributes['cardIndex'] ?? '00');

// Get block wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
Timber::render(__DIR__ . '/card.twig', $context);
echo '</div>';
