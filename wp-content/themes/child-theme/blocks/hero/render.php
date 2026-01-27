<?php
/**
 * Server-side rendering for the Hero block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    The block content (inner blocks HTML).
 * @param WP_Block $block      The block instance.
 */

use ChildTheme\Services\IconService;

$svg_asset = $attributes['svgAsset'] ?? '';
$video_url = $attributes['videoUrl'] ?? '';

// Inner container classes (BEM)
$inner_classes = ['hero'];
if (!empty($video_url)) {
    $inner_classes[] = 'hero--has-video';
}

// Top-level wrapper: only WordPress-managed attributes
$wrapper_attributes = get_block_wrapper_attributes();

?>
<div <?= $wrapper_attributes; ?>>
    <div class="<?= esc_attr(implode(' ', $inner_classes)); ?>">
        <?php if (!empty($video_url)) : ?>
            <video class="hero__video" src="<?= esc_url($video_url); ?>" autoplay muted loop playsinline></video>
        <?php endif; ?>

        <div class="hero__svg">
            <?php if (empty($video_url) && !empty($svg_asset)) : ?>
                <?= new IconService('squiggle/' . $svg_asset); ?>
            <?php endif; ?>
        </div>

        <div class="hero__content">
            <?= $content; ?>
        </div>
    </div>
</div>
