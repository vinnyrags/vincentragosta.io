<?php
/**
 * Server-side rendering for the Products block.
 */

use ChildTheme\Providers\Shop\ProductRepository;
use ChildTheme\Theme;
use Timber\Timber;

$mode = get_field('display_mode') ?: 'latest';
$selected_ids = get_field('selected_products') ?: [];

$repository = Theme::container()->get(ProductRepository::class);

if ($mode === 'all') {
    $products = $repository->all();
} elseif ($mode === 'curated' && !empty($selected_ids)) {
    $products = $repository->findMany($selected_ids);
} else {
    $products = $repository->latest(12);
}

// Collect unique categories for the filter dropdown (only in "all" mode).
$categories = [];
if ($mode === 'all') {
    foreach ($products as $product) {
        foreach ($product->categories() as $term) {
            if (!isset($categories[$term->slug])) {
                $categories[$term->slug] = $term->name;
            }
        }
    }
    ksort($categories);
}

$context = Timber::context();
$context['products'] = $products;
$context['show_toolbar'] = ($mode === 'all');
$context['categories'] = $categories;

$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
Timber::render(__DIR__ . '/grid.twig', $context);
echo '</div>';
