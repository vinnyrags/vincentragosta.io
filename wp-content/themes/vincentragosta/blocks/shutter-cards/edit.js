import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import './editor.scss';

const ALLOWED_BLOCKS = ['vincentragosta/shutter-card'];
const TEMPLATE = [['vincentragosta/shutter-card']];
const MAX_CARDS = 4;

export default function Edit({ clientId }) {
    const blockProps = useBlockProps();

    const { cardCount } = useSelect((select) => ({
        cardCount: select('core/block-editor').getBlockCount(clientId),
    }));

    return (
        <div {...blockProps}>
            <InnerBlocks
                allowedBlocks={ALLOWED_BLOCKS}
                template={TEMPLATE}
                renderAppender={
                    cardCount < MAX_CARDS
                        ? () => <InnerBlocks.ButtonBlockAppender />
                        : () => null
                }
            />
        </div>
    );
}