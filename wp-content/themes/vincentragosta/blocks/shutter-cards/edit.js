import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Button, TextControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { cards } = attributes;
    const blockProps = useBlockProps();

    const handleCardChange = (index, field, value) => {
        // Create a new array using .map()
        const newCards = cards.map((card, i) => {
            // If it's the card we want to update, return a new object
            if (i === index) {
                return {
                    ...card, // Copy existing properties
                    [field]: value, // Overwrite the one that changed
                };
            }
            // Otherwise, return the original card
            return card;
        });
        setAttributes({ cards: newCards });
    };

    const addCard = () => {
        if (cards.length >= 5) return;
        const newCard = {
            id: `card-${new Date().getTime()}`,
            title: 'New Card Title',
            subtitle: 'New Card Subtitle',
            description: '<p>New card description.</p>',
        };
        // Create a new array with the new card added
        setAttributes({ cards: [...cards, newCard] });
    };

    const removeCard = (index) => {
        // Create a new array excluding the card at the specified index
        const newCards = cards.filter((_, i) => i !== index);
        setAttributes({ cards: newCards });
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Card Management', 'vincentragosta')}>
                    <p>{__('You can add up to 5 cards.', 'vincentragosta')}</p>
                    <Button
                        variant="primary"
                        onClick={addCard}
                        disabled={cards.length >= 5}
                    >
                        {__('Add Card', 'vincentragosta')}
                    </Button>
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <div className="shutter-cards-editor">
                    {cards.map((card, index) => (
                        <div key={card.id} className="shutter-card-editor-item">
                            <div className="shutter-card-editor-item__header">
                                <strong>{`Card ${index + 1}`}</strong>
                                <Button isDestructive onClick={() => removeCard(index)}>
                                    {__('Remove', 'vincentragosta')}
                                </Button>
                            </div>
                            <TextControl
                                label={__('Title', 'vincentragosta')}
                                value={card.title}
                                onChange={(value) => handleCardChange(index, 'title', value)}
                            />
                            <TextControl
                                label={__('Subtitle', 'vincentragosta')}
                                value={card.subtitle}
                                onChange={(value) => handleCardChange(index, 'subtitle', value)}
                            />
                            <p className="components-base-control__label">
                                {__('Description', 'vincentragosta')}
                            </p>
                            <RichText
                                tagName="div"
                                multiline="p"
                                className="shutter-card-editor-item__description"
                                value={card.description}
                                onChange={(value) => handleCardChange(index, 'description', value)}
                                placeholder={__('Enter description here...', 'vincentragosta')}
                            />
                        </div>
                    ))}
                    {cards.length === 0 && (
                        <p>{__('No cards yet. Add one from the sidebar!', 'vincentragosta')}</p>
                    )}
                </div>
            </div>
        </>
    );
}