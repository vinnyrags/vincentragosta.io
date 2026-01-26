import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save({ attributes }) {
    const { title, subtitle, description, cardIndex } = attributes;
    const blockProps = useBlockProps.save();

    return (
        <div {...blockProps}>
            <div className="shutter-card">
                <span className="shutter-card__id">{cardIndex}</span>
                {title && (
                    <RichText.Content
                        tagName="h3"
                        className="shutter-card__title"
                        value={title}
                    />
                )}
                {subtitle && (
                    <RichText.Content
                        tagName="p"
                        className="shutter-card__subtitle"
                        value={subtitle}
                    />
                )}
                {description && (
                    <RichText.Content
                        tagName="div"
                        multiline="p"
                        className="shutter-card__description"
                        value={description}
                    />
                )}
                <div className="shutter-card__close-icon">
                    <svg className="icon icon-close" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 48 48">
                        <path fill="currentColor" d="m32.166 17.479-1.645-1.645L24 22.356l-6.522-6.522-1.645 1.645 6.522 6.522-6.522 6.521 1.645 1.645L24 25.646l6.521 6.521 1.645-1.645-6.521-6.521 6.521-6.522Z"></path>
                    </svg>
                </div>
            </div>
        </div>
    );
}