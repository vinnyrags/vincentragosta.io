import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {
    const { title, subtitle, description } = attributes;
    const blockProps = useBlockProps();

    return (
        <div {...blockProps}>
            <div className="shutter-card">
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
            </div>
        </div>
    );
}