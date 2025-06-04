<?php
/**
 * Server-side rendering for the Hero block.
 *
 * This file is referenced in block.json in the 'render' key.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    The block content (serialized HTML of inner blocks).
 * @param WP_Block $block      The block instance.
 * @return string HTML markup for the hero block.
 */

/** @var array $attributes */
/** @var string $content */
/** @var WP_Block $block */

$title     = $attributes['title'] ?? '';
$svg_asset = $attributes['svgAsset'] ?? '';

// Prepare wrapper attributes, including alignment
$wrapper_attributes_array = [];
// The 'hero' class will be added to the main div.
// Alignment classes like 'alignwide' or 'alignfull' are handled by get_block_wrapper_attributes().
// We'll ensure 'hero' is also part of the class list.
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'hero']);


?>
<div <?= $wrapper_attributes; ?>>
    <div class="hero__svg">
        <?php if (!empty($svg_asset)) : ?>
            <?php
            // Call the global function directly
            if (function_exists('get_theme_svg')) {
                // Assuming hero SVGs are not in 'svg-sprite', so $is_sprite is false.
                echo get_theme_svg($svg_asset, false);
            } else {
                error_log('Error in hero/render.php: Global function get_theme_svg() not found.');
            }
            ?>
        <?php endif; ?>
    </div>
    <div class="hero__content">
        <?php if (!empty($title)) : ?>
            <div class="hero__mask">
                <h1 class="hero__title"><?= $title; ?></h1>
            </div>
        <?php endif; ?>

        <?php if (!empty($content)) : // $content here is the InnerBlocks_HTML ?>
            <div class="hero__links">
                <?= $content; ?>
            </div>
        <?php endif; ?>
    </div>
</div>