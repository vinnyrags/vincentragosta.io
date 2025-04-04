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

/**
 * Populates ACF select field choices with SVG filenames from the theme's assets/images directory.
 *
 * @param array $field The ACF field array.
 * @return array The modified ACF field array.
 */
function populate_svg_choices( $field ) {
    // Reset choices
    $field['choices'] = array();

    // Path to the SVG directory
    $svg_dir = get_template_directory() . '/assets/images/';

    // Check if the directory exists
    if ( is_dir( $svg_dir ) ) {
        // Scan the directory for .svg files
        $svg_files = glob( $svg_dir . '*.svg' );

        if ( $svg_files ) {
            foreach ( $svg_files as $file_path ) {
                $filename = basename( $file_path );
                // Use filename as both value and label (or create a nicer label)
                $label = ucwords( str_replace( ['-', '_', '.svg'], ' ', $filename ) );
                $field['choices'][ $filename ] = $label;
            }
        }
    }

    // Add a default "None" option if needed
    // $field['choices'] = array_merge( array( '' => '- None -' ), $field['choices'] );

    return $field;
}
// Hook the function to the specific ACF field name used in Step 1
add_filter('acf/load_field/name=hero_svg_asset', 'populate_svg_choices');


/**
 * Safely retrieves the content of an SVG file from the theme's assets/images directory.
 *
 * @param string $filename The filename of the SVG (e.g., 'squiggle.svg').
 * @return string SVG content or an empty string if not found or invalid.
 */
function get_theme_svg( $filename ) {
    // Sanitize the filename to prevent directory traversal
    $filename = basename( $filename );

    // Construct the full path
    $svg_path = get_template_directory() . '/assets/images/' . $filename;

    // Check if the file exists and has a .svg extension
    if ( $filename && pathinfo( $svg_path, PATHINFO_EXTENSION ) === 'svg' && file_exists( $svg_path ) ) {
        // Read the file content
        $svg_content = file_get_contents( $svg_path );

        // Basic sanitization (remove script tags) - More robust sanitization might be needed
        // depending on the source of SVGs. Consider libraries like SVG Sanitizer if SVGs
        // could come from untrusted sources.
        $svg_content = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $svg_content );

        // Remove comments
        $svg_content = preg_replace('//', '', $svg_content);

        // Remove XML prolog if present
        $svg_content = preg_replace('/<\?xml(.|\s)*?\?>/', '', $svg_content);

        // You might want further sanitization here depending on trust level of SVGs

        return $svg_content ?: ''; // Return content or empty string
    }

    return ''; // Return empty string if file not found or invalid
}

// --- Make the SVG function available in Twig ---
// Option A: Add directly if StarterSite::add_to_twig is used
// Inside StarterSite class, add_to_twig method:
// $twig->addFunction(new \Timber\Twig_Function('get_theme_svg', 'get_theme_svg'));

// Option B: Add filter globally (if not using class method or for simplicity)
// Place this outside the class, e.g., after the function definitions above:
add_filter( 'timber/twig', function( $twig ) {
    $twig->addFunction( new Twig\TwigFunction( 'get_theme_svg', 'get_theme_svg' ) );
    return $twig;
});