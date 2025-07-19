import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Button, TextControl } from '@wordpress/components';
import { produce } from 'immer';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { cards } = attributes;
    const blockProps = useBlockProps();

    const handleCardChange = (index, field, value) => {
        const nextState = produce(cards, (draft) => {
            draft[index][field] = value;
        });
        setAttributes({ cards: nextState });
    };

    const addCard = () => {
        if (cards.length >= 5) return;
        const newCard = {
            id: `card-${new Date().getTime()}`,
            title: 'New Card Title',
            subtitle: 'New Card Subtitle',
            description: '<p>New card description.</p>',
        };
        setAttributes({ cards: [...cards, newCard] });
    };

    const removeCard = (index) => {
        const nextState = produce(cards, (draft) => {
            draft.splice(index, 1);
        });
        setAttributes({ cards: nextState });
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