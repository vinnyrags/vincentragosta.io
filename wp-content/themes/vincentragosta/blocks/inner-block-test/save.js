import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

// The save function defines the markup that is saved to the post content.
// For blocks using server-side rendering with InnerBlocks, this function
// outputs a minimal structural signature, including the wrapper and InnerBlocks.Content.
export default function save() {
    // Apply block props for save, which includes necessary data attributes
    // for WordPress to recognize this as the block's outer wrapper on the frontend.
    const blockProps = useBlockProps.save();

    return (
        // Output the main wrapper div with saved block props.
        // InnerBlocks.Content renders the *saved markup* of the inner blocks.
        // This provides the editor with the minimal structure needed to re-parse
        // the block correctly on page load, especially with InnerBlocks present.
        // Attributes like 'heading' and 'content' are NOT output here, as they
        // are handled dynamically by the server-side render.php.
        <div {...blockProps}>
            <InnerBlocks.Content />
        </div>
    );
}