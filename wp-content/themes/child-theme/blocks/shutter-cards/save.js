import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save() {
    // We get the block props to apply the necessary classes and attributes.
    const blockProps = useBlockProps.save();

    // The structure here should mirror the render.php file for consistency.
    return (
        <div {...blockProps}>
            <div className="shutter-cards-container">
                {/* This crucial component renders and saves the HTML of all child blocks. */}
                <InnerBlocks.Content />
            </div>
        </div>
    );
}