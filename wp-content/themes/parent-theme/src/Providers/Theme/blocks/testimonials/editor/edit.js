import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const ALLOWED_BLOCKS = ['core/quote', 'core/pullquote'];
const TEMPLATE = [['core/quote']];

export default function Edit() {
    const blockProps = useBlockProps();

    return (
        <div {...blockProps}>
            <InnerBlocks
                allowedBlocks={ALLOWED_BLOCKS}
                template={TEMPLATE}
                renderAppender={() => <InnerBlocks.ButtonBlockAppender />}
            />
        </div>
    );
}
