import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from 'react';

const ALLOWED_BLOCKS = ['ix/shutter-card'];
const TEMPLATE = [['ix/shutter-card']];
const MAX_CARDS = 5;

export default function Edit({ clientId }) {
    const blockProps = useBlockProps();
    const { updateBlockAttributes } = useDispatch('core/block-editor');

    const { innerBlocks, cardCount } = useSelect((select) => {
        const { getBlocks, getBlockCount } = select('core/block-editor');
        return {
            innerBlocks: getBlocks(clientId),
            cardCount: getBlockCount(clientId),
        };
    });

    // Update card indices
    useEffect(() => {
        innerBlocks.forEach((block, index) => {
            const formattedIndex = (index + 1).toString().padStart(2, '0');
            if (block.attributes.cardIndex !== formattedIndex) {
                updateBlockAttributes(block.clientId, { cardIndex: formattedIndex });
            }
        });
    }, [innerBlocks, updateBlockAttributes]);

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
