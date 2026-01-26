/**
 * Button Icon Enhancement
 *
 * Adds icon selection controls to the core/button block in the editor.
 * Uses global wp object instead of ES6 imports for direct loading without compilation.
 */

(function () {
    const { createHigherOrderComponent } = wp.compose;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, BaseControl } = wp.components;
    const { addFilter } = wp.hooks;
    const { createElement, Fragment } = wp.element;

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

        return createElement(
            BaseControl,
            { label: 'Preview' },
            createElement('div', { dangerouslySetInnerHTML: { __html: html } })
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
                    return createElement(BlockEdit, props);
                }

                const { options, content } = getIconData();
                const { selectedIcon, iconPosition = 'right' } = attributes;
                const iconHtml = selectedIcon ? content[selectedIcon] : null;

                return createElement(
                    Fragment,
                    null,
                    createElement(BlockEdit, props),
                    isSelected && createElement(
                        InspectorControls,
                        null,
                        createElement(
                            PanelBody,
                            { title: 'Button Icon', initialOpen: true },
                            createElement(SelectControl, {
                                label: 'Icon',
                                value: selectedIcon || '',
                                options: options,
                                onChange: (value) => setAttributes({ selectedIcon: value }),
                            }),
                            selectedIcon && createElement(
                                Fragment,
                                null,
                                createElement(SelectControl, {
                                    label: 'Position',
                                    value: iconPosition,
                                    options: POSITION_OPTIONS,
                                    onChange: (value) => setAttributes({ iconPosition: value }),
                                }),
                                createElement(IconPreview, { html: iconHtml })
                            )
                        )
                    )
                );
            },
            'withButtonIconControls'
        )
    );
})();
