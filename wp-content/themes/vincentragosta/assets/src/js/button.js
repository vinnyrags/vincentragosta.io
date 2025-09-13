import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';

console.log('button.js script is loaded.');

const withButtonIconAttributes = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        const {
            name,
            attributes,
            setAttributes,
            isSelected,
        } = props;

        // Ensure we're targeting the core/button block
        if (name !== 'core/button') {
            return <BlockEdit {...props} />;
        }

        console.log('Editing the core/button block.');

        // Get the icon data from the global object
        const iconData = window.vincentragostaButtonIconData;
        console.log('Icon Data from global object:', iconData);

        const { selectedIcon, iconPosition } = attributes;

        // Make sure we have the necessary data to render the UI
        if (!iconData || !iconData.iconOptions) {
            console.error('vincentragostaButtonIconData is not available. Check `localize_block_editor_data` in StarterSite.php.');
            return <BlockEdit {...props} />;
        }

        const iconOptions = iconData.iconOptions;
        const iconContentMap = iconData.iconContentMap;

        // Function to create and insert an icon element
        const renderIcon = (iconName) => {
            if (!iconName || !iconContentMap[iconName]) {
                return null;
            }
            // Temporarily insert a div to see if the content is there
            return (
                <div
                    dangerouslySetInnerHTML={{ __html: iconContentMap[iconName] }}
                    style={{ display: 'inline-block', verticalAlign: 'middle', marginRight: '5px' }}
                />
            );
        };

        const iconLabel = selectedIcon ?
            iconOptions.find(option => option.value === selectedIcon)?.label : '— No Icon —';

        console.log('Selected Icon:', selectedIcon);
        console.log('Icon Position:', iconPosition);

        return (
            <Fragment>
                <BlockEdit {...props} />
                {isSelected && (
                    <InspectorControls>
                        <PanelBody title="Button Icon" initialOpen={true}>
                            <SelectControl
                                label="Select Icon"
                                value={selectedIcon}
                                options={iconOptions}
                                onChange={(value) => {
                                    setAttributes({ selectedIcon: value });
                                    console.log('Icon selected:', value);
                                }}
                            />
                            <SelectControl
                                label="Icon Position"
                                value={iconPosition || 'left'}
                                options={[
                                    { label: 'Left', value: 'left' },
                                    { label: 'Right', value: 'right' },
                                ]}
                                onChange={(value) => {
                                    setAttributes({ iconPosition: value });
                                    console.log('Icon position changed:', value);
                                }}
                            />
                            {selectedIcon && (
                                <p>
                                    Preview: {renderIcon(selectedIcon)} {iconLabel}
                                </p>
                            )}
                        </PanelBody>
                    </InspectorControls>
                )}
            </Fragment>
        );
    };
}, 'withButtonIconAttributes');

addFilter(
    'editor.BlockEdit',
    'vincentragosta/with-button-icon-attributes',
    withButtonIconAttributes
);

const addIconAttributes = (settings) => {
    // Ensure we're targeting the core/button block.
    if (settings.name !== 'core/button') {
        return settings;
    }

    console.log('Adding custom attributes to core/button settings.');

    // Add our custom attributes.
    return {
        ...settings,
        attributes: {
            ...settings.attributes,
            selectedIcon: {
                type: 'string',
                default: '',
            },
            iconPosition: {
                type: 'string',
                default: 'left',
            },
        },
    };
};

addFilter(
    'blocks.registerBlockType',
    'vincentragosta/add-button-icon-attributes',
    addIconAttributes
);