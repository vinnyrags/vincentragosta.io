<?php
/**
 * Server-side rendering for the Hero block.
 *
 * This file is referenced in block.json in the 'render' key.
 *
 * @param array $attributes Block attributes.
 * @param string $content The block content (serialized HTML of inner blocks).
 * @param WP_Block $block The block instance.
 * @return string HTML markup for the hero block.
 */

// --- IDE Hints (for static analysis only) ---
// These @var tags tell PHPStorm (and other tools) that these variables
// exist in this file's scope and what their types are, even though
// they are not defined using the '=' operator within this file.
/** @var array $attributes */
/** @var string $content */ // This $content contains the rendered InnerBlocks HTML
/** @var WP_Block $block */


$title = $attributes['title'] ?? '';
$subtitle = $attributes['subtitle'] ?? '';
$svg_asset = $attributes['svgAsset'] ?? '';

// get_block_wrapper_attributes automatically includes alignment and InnerBlocks classes
$wrapper_attributes = get_block_wrapper_attributes();
// Manually add the 'hero' class to the wrapper attributes.
// get_block_wrapper_attributes() returns a string like 'class="wp-block-vincentragosta-hero alignfull"'
// We find the class attribute and insert our custom class.
$wrapper_attributes = str_replace(
    'class="',
    'class="hero ',
    $wrapper_attributes
);

?>
<div <?= $wrapper_attributes; ?>>
    <div class="hero__svg">
        <?php if (!empty($svg_asset)): ?>
            <?= get_theme_svg($svg_asset); ?>
        <?php endif; ?>
    </div>
    <div class="hero__content">
        <!-- TODO: add "taxonomy" segment -->
        <?php if (!empty($title)) : ?>
            <div class="hero__mask">
                <h1 class="hero__title"><?= $title; ?></h1>
            </div>
        <?php endif; ?>

        <?php if (!empty($content)): ?>
            <div class="hero__links">
                <?= $content; ?>
            </div>
        <?php endif; ?>
    </div>
</div>