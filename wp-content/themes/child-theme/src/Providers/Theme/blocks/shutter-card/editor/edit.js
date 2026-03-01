import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

export default function Edit({ attributes, setAttributes, clientId }) {
    const { title, subtitle, description, cardIndex } = attributes;

    const isActive = useSelect((select) => {
        const {
            getSelectedBlockClientId,
            hasSelectedInnerBlock,
            getBlockParents,
            getBlocks,
        } = select('core/block-editor');

        const selectedId = getSelectedBlockClientId();

        // If this card or its children are selected, it's active
        if (selectedId === clientId || hasSelectedInnerBlock(clientId, true)) {
            return true;
        }

        // Find parent shutter-cards block
        const parents = getBlockParents(clientId);
        const parentId = parents[parents.length - 1];

        if (!parentId) return cardIndex === '01'; // Fallback: first card active

        // Get all sibling cards
        const siblings = getBlocks(parentId);

        // Check if any sibling is selected
        const anySiblingSelected = siblings.some(
            (block) =>
                getSelectedBlockClientId() === block.clientId ||
                hasSelectedInnerBlock(block.clientId, true)
        );

        // If no sibling is selected, first card is active
        if (!anySiblingSelected) {
            return siblings[0]?.clientId === clientId;
        }

        return false;
    }, [clientId, cardIndex]);

    const blockProps = useBlockProps({
        className: isActive ? 'is-active' : 'is-inactive',
    });

    return (
        <div {...blockProps}>
            <div className="shutter-card">
                <span className="shutter-card__id">{cardIndex}</span>
                <RichText
                    tagName="h3"
                    className="shutter-card__title"
                    value={title}
                    onChange={(newTitle) => setAttributes({ title: newTitle })}
                    placeholder={__('Enter title here...', 'child-theme')}
                    allowedFormats={['core/bold', 'core/italic']}
                />
                <RichText
                    tagName="p"
                    className="shutter-card__subtitle"
                    value={subtitle}
                    onChange={(newSubtitle) => setAttributes({ subtitle: newSubtitle })}
                    placeholder={__('Enter subtitle here...', 'child-theme')}
                    allowedFormats={['core/bold', 'core/italic']}
                />
                <RichText
                    tagName="p"
                    className="shutter-card__description"
                    value={description}
                    onChange={(newDescription) => setAttributes({ description: newDescription })}
                    placeholder={__('Enter description here...', 'child-theme')}
                    allowedFormats={['core/bold', 'core/italic', 'core/link']}
                />
                <button type="button" className="shutter-card__toggle" aria-label="Toggle card" tabIndex={-1}>
                    <svg className="icon icon-close" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 48 48">
                        <path fill="currentColor" d="m32.166 17.479-1.645-1.645L24 22.356l-6.522-6.522-1.645 1.645 6.522 6.522-6.522 6.521 1.645 1.645L24 25.646l6.521 6.521 1.645-1.645-6.521-6.521 6.521-6.522Z"></path>
                    </svg>
                </button>
            </div>
        </div>
    );
}
