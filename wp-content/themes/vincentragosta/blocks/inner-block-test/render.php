<?php
/**
 * ... (PHPDoc and @var tags) ...
 */
/** @var array $attributes */
/** @var string $content */ // Inner blocks HTML
/** @var WP_Block $block */

//error_log('DEBUG: render.php for inner-block-test is executing.'); // Add this line

$heading = $attributes['heading'] ?? '';
$paragraph_content = $attributes['content'] ?? ''; // Attribute from block.json

$wrapper_attributes = get_block_wrapper_attributes();

//ob_start();
?>
    <div <?php echo $wrapper_attributes; ?>>
        <?php if ( ! empty( $heading ) ) : ?>
            <h2><?php echo wp_kses_post( $heading ); ?></h2>
        <?php endif; ?>

        <?php if ( ! empty( $paragraph_content ) ) : ?>
            <p><?php echo wp_kses_post( $paragraph_content ); ?></p>
        <?php endif; ?>

        <?php
        // This should output the buttons HTML
        echo $content;
        ?>
    </div>
<?php
// error_log('DEBUG: render.php output: ' . ob_get_contents()); // Optional: log the generated HTML (be cautious with very long HTML)
//echo ob_get_clean();