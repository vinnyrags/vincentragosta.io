import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';

const withButtonIconAttributes = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        const {
            name,
            attributes,
            setAttributes,
            isSelected
        } = props;

        if (name !== 'core/button') {
            return <BlockEdit {...props} />;
        }

        const iconData = window.vincentragostaButtonIconData;

        const { selectedIcon, iconPosition } = attributes;

        if (!iconData || !iconData.iconOptions) {
            console.error('vincentragostaButtonIconData is not available. Check `localize_block_editor_data` in StarterSite.php.');
        }

        const iconOptions = iconData ? iconData.iconOptions : [];
        const iconContentMap = iconData ? iconData.iconContentMap : {};

        const renderIcon = (iconName) => {
            if (!iconName || !iconContentMap[iconName]) {
                return null;
            }

            return (
                <span className="wp-block-button__icon">
                    <span dangerouslySetInnerHTML={{ __html: iconContentMap[iconName] }} />
                </span>
            );
        };

        const iconLabel = selectedIcon ?
            iconOptions.find(option => option.value === selectedIcon)?.label : '— No Icon —';

        return (
            <Fragment>
                <BlockEdit {...props}>
                    {/* Check if an icon is selected and render it inside the button content */}
                    {selectedIcon && iconPosition === 'left' && (
                        renderIcon(selectedIcon)
                    )}
                    {/* The original block content is passed as children. We'll render it here. */}
                    {props.children}
                    {selectedIcon && iconPosition === 'right' && (
                        renderIcon(selectedIcon)
                    )}
                </BlockEdit>
                {isSelected && (
                    <InspectorControls>
                        <PanelBody title="Button Icon" initialOpen={true}>
                            <SelectControl
                                label="Select Icon"
                                value={selectedIcon}
                                options={iconOptions}
                                onChange={(value) => {
                                    setAttributes({ selectedIcon: value });
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
                                }}
                            />
                            {selectedIcon && (
                                <p>
                                    Preview: {iconLabel}
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
    if (settings.name !== 'core/button') {
        return settings;
    }


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