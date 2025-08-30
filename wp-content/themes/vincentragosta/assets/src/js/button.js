import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { InspectorControls, RichText, BlockControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, BaseControl } from '@wordpress/components';

const NAMESPACE = 'vincentragosta-theme/button-icon';

// This function remains the same
const addButtonIconAttributes = (settings, name) => {
    if (name === 'core/button') {
        const newAttributes = {
            ...settings.attributes,
            selectedIcon: {
                type: 'string',
                default: '',
            },
            iconPosition: {
                type: 'string',
                default: 'left',
            },
        };
        // Clean up old, unused attributes if they exist
        if (newAttributes.hasOwnProperty('hasIcon')) {
            delete newAttributes.hasIcon;
        }
        if (newAttributes.hasOwnProperty('iconSvgString')) {
            delete newAttributes.iconSvgString;
        }
        return { ...settings, attributes: newAttributes };
    }
    return settings;
};

addFilter(
    'blocks.registerBlockType',
    `${NAMESPACE}/add-button-icon-attributes`,
    addButtonIconAttributes
);

// This Higher-Order Component logic remains the same
const withButtonIconEdit = createHigherOrderComponent((OriginalBlockEdit) => {
    return (props) => {
        // Since this HOC is now ONLY applied to core/button,
        // we no longer need the `if (props.name !== 'core/button')` check.

        const { attributes, setAttributes } = props;
        const { selectedIcon, iconPosition, text, placeholder } = attributes;

        const [iconData, setIconData] = useState({
            options: [{ label: __('— No Icon —', 'vincentragosta'), value: '' }],
            contentMap: {},
        });

        useEffect(() => {
            if (window.vincentragostaButtonIconData) {
                setIconData({
                    options: window.vincentragostaButtonIconData.iconOptions || [],
                    contentMap: window.vincentragostaButtonIconData.iconContentMap || {},
                });
            }
        }, []);

        const handleIconChange = (newIconFile) => {
            setAttributes({ selectedIcon: newIconFile || '' });
        };

        const currentSvgContent = selectedIcon && iconData.contentMap[selectedIcon] ? iconData.contentMap[selectedIcon] : null;

        return (
            <Fragment>
                <InspectorControls>
                    <PanelBody title={__('Icon Settings', 'vincentragosta')} initialOpen={true}>
                        <SelectControl
                            label={__('Select Icon', 'vincentragosta')}
                            value={selectedIcon}
                            options={iconData.options}
                            onChange={handleIconChange}
                        />
                        {selectedIcon && (
                            <SelectControl
                                label={__('Icon Position', 'vincentragosta')}
                                value={iconPosition}
                                options={[
                                    { label: __('Left', 'vincentragosta'), value: 'left' },
                                    { label: __('Right', 'vincentragosta'), value: 'right' },
                                ]}
                                onChange={(val) => setAttributes({ iconPosition: val })}
                            />
                        )}
                    </PanelBody>
                </InspectorControls>

                {/* We can now safely render the original block edit component */}
                <OriginalBlockEdit {...props} />

                {/* Optional: Add an icon preview inside the button in the editor */}
                {currentSvgContent && (
                    <div className="vrt-button-icon-editor-preview-wrapper">
                        <span
                            className={`vrt-button-icon-editor-svg is-position-${iconPosition}`}
                            dangerouslySetInnerHTML={{ __html: currentSvgContent }}
                        />
                    </div>
                )}
            </Fragment>
        );
    };
}, 'withButtonIconEdit');

// --- THIS IS THE CRUCIAL CHANGE ---
// We change the filter from the global 'editor.BlockEdit'
// to the targeted 'blocks.BlockEdit'
addFilter(
    'blocks.BlockEdit',
    `${NAMESPACE}/with-button-icon-edit`,
    (BlockEditComponent, block) => {
        // We only apply our modification to the 'core/button' block
        if (block.name === 'core/button') {
            return withButtonIconEdit(BlockEditComponent);
        }
        // For all other blocks, return the original component unmodified.
        return BlockEditComponent;
    }
);