import { __ } from '@wordpress/i18n';
import { registerFormatType, toggleFormat } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';

// --- Inline Block Format ---

const inlineBlockIcon = (
    <svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M4 4h16v2H4V4zm0 4h10v2H4V8zm0 4h16v2H4v-2zm0 4h10v2H4v-2z" />
    </svg>
);

const INLINE_BLOCK_FORMAT_TYPE = 'child-theme/inline-block';
const INLINE_BLOCK_CLASS_NAME = 'd-inline-block';

const InlineBlockFormatButton = ({ isActive, value, onChange }) => {
    return (
        <RichTextToolbarButton
            icon={inlineBlockIcon}
            title={__('Inline Block', 'child-theme')}
            onClick={() => {
                onChange(
                    toggleFormat(value, {
                        type: INLINE_BLOCK_FORMAT_TYPE,
                    })
                );
            }}
            isActive={isActive}
        />
    );
};

registerFormatType(INLINE_BLOCK_FORMAT_TYPE, {
    tagName: 'span',
    className: INLINE_BLOCK_CLASS_NAME,
    title: __('Inline Block', 'child-theme'),
    edit: InlineBlockFormatButton,
});


// --- Underline Format (Updated) ---

const UNDERLINE_FORMAT_TYPE = 'child-theme/underline';

const UnderlineFormatButton = ({ isActive, value, onChange }) => {
    return (
        <RichTextToolbarButton
            icon="editor-underline"
            title={__('Underline', 'child-theme')}
            onClick={() => {
                onChange(
                    toggleFormat(value, {
                        type: UNDERLINE_FORMAT_TYPE,
                    })
                );
            }}
            isActive={isActive}
        />
    );
};

registerFormatType(UNDERLINE_FORMAT_TYPE, {
    tagName: 'span',
    className: 'text--underline',
    title: __('Underline', 'child-theme'),
    edit: UnderlineFormatButton,
});
