import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, BaseControl } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';

const BLOCK_NAME = 'core/button';

const POSITION_OPTIONS = [
    { label: 'Left', value: 'left' },
    { label: 'Right', value: 'right' },
];

/**
 * Cached icon data from PHP.
 */
let iconDataCache = null;

const getIconData = () => {
    if (iconDataCache) {
        return iconDataCache;
    }

    const data = window.vincentragostaButtonIconData || {};
    iconDataCache = {
        options: data.iconOptions || [],
        content: data.iconContentMap || {},
    };

    return iconDataCache;
};

/**
 * Icon preview component for inspector.
 */
const IconPreview = ({ html }) => {
    if (!html) return null;

    return (
        <BaseControl label="Preview">
            <div dangerouslySetInnerHTML={{ __html: html }} />
        </BaseControl>
    );
};

/**
 * Add icon attributes to core/button.
 */
addFilter('blocks.registerBlockType', 'theme/button-icon-attributes', (settings) => {
    if (settings.name !== BLOCK_NAME) {
        return settings;
    }

    return {
        ...settings,
        attributes: {
            ...settings.attributes,
            selectedIcon: { type: 'string', default: '' },
            iconPosition: { type: 'string', default: 'right' },
        },
    };
});

/**
 * Add icon controls to inspector.
 */
addFilter(
    'editor.BlockEdit',
    'theme/button-icon-controls',
    createHigherOrderComponent(
        (BlockEdit) => (props) => {
            const { name, attributes, setAttributes, isSelected } = props;

            if (name !== BLOCK_NAME) {
                return <BlockEdit {...props} />;
            }

            const { options, content } = getIconData();
            const { selectedIcon, iconPosition = 'right' } = attributes;
            const iconHtml = selectedIcon ? content[selectedIcon] : null;

            return (
                <>
                    <BlockEdit {...props} />
                    {isSelected && (
                        <InspectorControls>
                            <PanelBody title="Button Icon" initialOpen>
                                <SelectControl
                                    label="Icon"
                                    value={selectedIcon || ''}
                                    options={options}
                                    onChange={(value) => setAttributes({ selectedIcon: value })}
                                />
                                {selectedIcon && (
                                    <>
                                        <SelectControl
                                            label="Position"
                                            value={iconPosition}
                                            options={POSITION_OPTIONS}
                                            onChange={(value) => setAttributes({ iconPosition: value })}
                                        />
                                        <IconPreview html={iconHtml} />
                                    </>
                                )}
                            </PanelBody>
                        </InspectorControls>
                    )}
                </>
            );
        },
        'withButtonIconControls'
    )
);
