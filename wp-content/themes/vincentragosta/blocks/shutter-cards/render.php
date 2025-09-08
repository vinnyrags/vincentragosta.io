<?php
/**
 * Server-side rendering for the Shutter Cards container block.
 */

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'shutter-cards--preload']);
$inner_blocks = isset($block) ? $block->inner_blocks : [];
$card_count = count($inner_blocks);
$container_styles = $container_data_attributes = '';

// If we have more than one card, calculate the inactive width and set it as a CSS variable.
if ($card_count > 1) {
    $inactive_width_percentage = 60 / $card_count;
    $container_styles = sprintf('style="--card-inactive-width: %f%%;"', $inactive_width_percentage);
}
?>
<div <?php echo $wrapper_attributes; ?>>
    <div class="shutter-cards-container" <?php echo $container_styles; ?>>
        <?php if (!empty($inner_blocks)) : ?>
            <?php foreach ($inner_blocks as $index => $inner_block) : ?>
                <?php
                echo $inner_block->render();
                ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>