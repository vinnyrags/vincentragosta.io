<?php
/**
 * Server-side rendering for the Shutter Cards container block.
 */

$wrapper_attributes = get_block_wrapper_attributes();
?>
<div <?php echo $wrapper_attributes; ?>>
    <div class="shutter-cards-container">
        <?php echo $content; ?>
    </div>
</div>