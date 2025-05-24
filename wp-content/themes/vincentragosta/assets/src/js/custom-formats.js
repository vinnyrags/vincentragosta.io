import { __ } from '@wordpress/i18n';
import { registerFormatType } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { toggleFormat } from '@wordpress/rich-text'; // Ensure toggleFormat is imported

// A simple SVG icon for inline-block (you can replace this with a Dashicon name or a more suitable SVG)
// This example uses a generic "layout" or "box" like icon.
const inlineBlockIcon = (
    <svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M4 4h16v2H4V4zm0 4h10v2H4V8zm0 4h16v2H4v-2zm0 4h10v2H4v-2z" />
    </svg>
);


const FORMAT_TYPE_NAME = 'vincentragosta/inline-block';
const FORMAT_CLASS_NAME = 'd-inline-block'; // This is the class that will be applied

const InlineBlockFormatButton = ({ isActive, value, onChange }) => {
    return (
        <RichTextToolbarButton
            icon={inlineBlockIcon} // Using the custom SVG icon
            // Alternatively, use a Dashicon: icon="editor-insertmore" or "format-aside"
            title={__('Inline Block', 'vincentragosta')}
            onClick={() => {
                onChange(
                    toggleFormat(value, {
                        type: FORMAT_TYPE_NAME,
                        // No attributes needed for a simple class toggle
                    })
                );
            }}
            isActive={isActive}
            // className="my-custom-format-button-class" // Optional: if you need to style the button itself
        />
    );
};

registerFormatType(FORMAT_TYPE_NAME, {
    tagName: 'span', // The HTML tag to wrap the text with
    className: FORMAT_CLASS_NAME, // The CSS class to apply to the span
    title: __('Inline Block', 'vincentragosta'), // Title for the format type (appears in UI)
    edit: InlineBlockFormatButton, // The component for the toolbar button
});