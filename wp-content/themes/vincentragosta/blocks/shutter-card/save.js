import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save({ attributes }) {
    const { title, subtitle, description } = attributes;
    const blockProps = useBlockProps.save({ className: 'shutter-card' });

    return (
        <div {...blockProps}>
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
        </div>
    );
}