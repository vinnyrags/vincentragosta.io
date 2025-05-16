import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    RichText,
    InnerBlocks, // Import InnerBlocks
} from '@wordpress/block-editor';

import './editor.scss';

// Define which blocks are allowed inside this block.
// We are explicitly allowing only the 'core/buttons' block.
const ALLOWED_BLOCKS = [ 'core/buttons' ];

// Define a template for the inner blocks (optional, provides a default state)
// This template will automatically add a 'core/buttons' block when the parent block is inserted.
// You can remove this template if you prefer the InnerBlocks area to start empty.
const MY_TEMPLATE = [
    [ 'core/buttons', {}, [
        [ 'core/button', { text: __( 'Add Button', 'vincentragosta' ) } ]
    ] ]
];


export default function Edit({ attributes, setAttributes }) {
    const { heading, content } = attributes;

    // useBlockProps automatically handles attributes needed for block saving/serialization,
    // including attributes required for InnerBlocks to function correctly.
    const blockProps = useBlockProps();

    const onChangeHeading = (newHeading) => {
        setAttributes({ heading: newHeading });
    };

    const onChangeContent = (newContent) => {
        setAttributes({ content: newContent });
    };

    return (
        // Apply blockProps to the outermost element of your block's editor structure.
        <div {...blockProps}>
            <RichText
                tagName="h2" // Use an appropriate tag for the heading
                value={heading}
                onChange={onChangeHeading}
                placeholder={__('Enter Block Heading', 'vincentragosta')}
                // Optional: restrict formatting
                allowedFormats={[ 'core/bold', 'core/italic' ]}
            />
            <RichText
                tagName="p" // Use an appropriate tag for the content
                value={content}
                onChange={onChangeContent}
                placeholder={__('Enter some content', 'vincentragosta')}
                // Optional: restrict formatting
                allowedFormats={[ 'core/bold', 'core/italic', 'core/link' ]}
            />

            {/* The area where InnerBlocks will be managed */}
            <div className="inner-block-test-buttons-wrapper"> {/* Optional wrapper for styling */}
                <InnerBlocks
                    allowedBlocks={ALLOWED_BLOCKS} // Restrict allowed block types
                    template={MY_TEMPLATE} // Apply the default template (optional)
                    templateLock={false} // Allow adding/removing blocks within the allowed types (e.g., multiple buttons within core/buttons)
                />
            </div>
        </div>
    );
}