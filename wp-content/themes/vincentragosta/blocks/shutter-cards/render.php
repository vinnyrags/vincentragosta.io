<?php
/**
 * Server-side rendering for the Shutter Card block.
 */

$title       = $attributes['title'] ?? '';
$subtitle    = $attributes['subtitle'] ?? '';
$description = $attributes['description'] ?? '';

$wrapper_attributes = get_block_wrapper_attributes();

?>
<div <?php echo $wrapper_attributes; ?>>
    <div class="shutter-card">
        <?php if (!empty($title)) : ?>
            <h3 class="shutter-card__title"><?php echo wp_kses_post($title); ?></h3>
        <?php endif; ?>

        <?php if (!empty($subtitle)) : ?>
            <p class="shutter-card__subtitle"><?php echo wp_kses_post($subtitle); ?></p>
        <?php endif; ?>

        <?php if (!empty($description)) : ?>
            <div class="shutter-card__description">
                <?php echo wp_kses_post($description); ?>
            </div>
        <?php endif; ?>
    </div>
</div>