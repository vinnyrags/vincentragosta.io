<?php
/**
 * Timber starter-theme
 * https://github.com/timber/starter-theme
 */

// Load Composer dependencies.
require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/src/StarterSite.php';

Timber\Timber::init();

// Sets the directories (inside your theme) to find .twig files.
// Add 'blocks' directory for block-specific templates
Timber::$dirname = [ 'templates', 'views', 'blocks' ];

new StarterSite();

// --- NEW: ACF Block Render Callback ---
/**
 * Generic Render Callback for ACF Blocks.
 * Renders the Twig file based on the block's name.
 *
 * @param array    $block      The block settings and attributes.
 * @param string   $content    The block inner content (empty unless using <InnerBlocks>).
 * @param bool     $is_preview True during editor preview.
 * @param int      $post_id    The post ID this block is saved to.
 * @param WP_Block $wp_block   The block instance (since WP 5.5).
 * @param array    $acf_context The ACF context provided block values (since ACF 6.0).
 */
function theme_acf_block_render_callback( $block, $content = '', $is_preview = false, $post_id = 0, $wp_block = null, $acf_context = null ) {
    // Use Timber context as a base
    $context = Timber\Timber::context();

    // Store block values.
    $context['block']      = $block; // Contains block name, ID, alignment, etc.
    $context['post_id']    = $post_id;

    // Store field values.
    // Use $acf_context if available (ACF 6.0+), otherwise fallback to get_fields().
    $context['fields'] = $acf_context['fields'] ?? get_fields();

    // Store whether previewing in the editor.
    $context['is_preview'] = $is_preview;

    // Get the block slug (e.g., 'my-block' from 'acf/my-block')
    $slug = str_replace( 'acf/', '', $block['name'] );

    // Construct the path to the Twig file within the block's folder
    // Example: 'blocks/my-block/my-block.twig'
    $template_path = $slug . '/' . $slug . '.twig';

    // Check if the template file exists before rendering
    $full_template_path = get_template_directory() . '/blocks/' . $template_path;

    if ( file_exists( $full_template_path ) ) {
        Timber\Timber::render( $template_path, $context );
    } else {
        // Optional: Output an error if the template is missing
        if ( current_user_can( 'edit_posts' ) ) {
            echo '<p style="color: red; font-weight: bold;">Error: Block template not found at <code>' . esc_html( 'blocks/' . $template_path ) . '</code></p>';
        }
        // Or render a fallback template or nothing
        // Timber\Timber::render( 'blocks/fallback-block.twig', $context );
    }
}
// --- END NEW ---