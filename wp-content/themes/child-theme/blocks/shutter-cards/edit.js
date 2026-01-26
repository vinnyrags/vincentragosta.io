import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from 'react';
import './editor.scss';

const ALLOWED_BLOCKS = ['child-theme/shutter-card'];
const TEMPLATE = [['child-theme/shutter-card']];
const MAX_CARDS = 4;

export default function Edit({ clientId }) {
    const blockProps = useBlockProps();
    const { updateBlockAttributes } = useDispatch('core/block-editor');

    const { innerBlocks } = useSelect((select) => ({
        innerBlocks: select('core/block-editor').getBlocks(clientId),
    }));

    useEffect(() => {
        innerBlocks.forEach((block, index) => {
            const formattedIndex = (index + 1).toString().padStart(2, '0');
            if (block.attributes.cardIndex !== formattedIndex) {
                updateBlockAttributes(block.clientId, { cardIndex: formattedIndex });
            }
        });
    }, [innerBlocks, updateBlockAttributes]);

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