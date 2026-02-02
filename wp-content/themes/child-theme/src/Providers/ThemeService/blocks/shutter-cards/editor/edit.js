import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from 'react';

const ALLOWED_BLOCKS = ['child-theme/shutter-card'];
const TEMPLATE = [['child-theme/shutter-card']];
const MAX_CARDS = 5;

export default function Edit({ clientId }) {
    const blockProps = useBlockProps();
    const { updateBlockAttributes } = useDispatch('core/block-editor');
    const [activeCardId, setActiveCardId] = useState(null);

    const { innerBlocks, cardCount, selectedBlockId } = useSelect((select) => {
        const { getBlocks, getBlockCount, getSelectedBlockClientId } = select('core/block-editor');
        return {
            innerBlocks: getBlocks(clientId),
            cardCount: getBlockCount(clientId),
            selectedBlockId: getSelectedBlockClientId(),
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

        // Set first card as active by default
        if (innerBlocks.length > 0 && !activeCardId) {
            setActiveCardId(innerBlocks[0].clientId);
        }

        // If active card was removed, activate first card
        if (activeCardId && !innerBlocks.find(b => b.clientId === activeCardId)) {
            setActiveCardId(innerBlocks[0]?.clientId || null);
        }
    }, [innerBlocks, updateBlockAttributes, activeCardId]);

    // When a shutter-card is selected, make it active
    useEffect(() => {
        const isShutterCard = innerBlocks.some(b => b.clientId === selectedBlockId);
        if (isShutterCard && selectedBlockId !== activeCardId) {
            setActiveCardId(selectedBlockId);
        }
    }, [selectedBlockId, innerBlocks, activeCardId]);

    // Calculate inactive width
    const inactiveWidth = cardCount > 1 ? `${60 / cardCount}%` : '20%';

    return (
        <div
            {...blockProps}
            style={{ '--card-inactive-width': inactiveWidth }}
            data-active-card={activeCardId}
        >
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
