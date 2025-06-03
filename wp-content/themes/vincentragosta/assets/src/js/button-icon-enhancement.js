// In assets/src/js/button-icon-enhancement.js (or relevant part of main.js)

import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { InspectorControls, RichText, BlockControls } from '@wordpress/block-editor'; // Added BlockControls
import { PanelBody, SelectControl, BaseControl, ToolbarGroup, ToolbarButton } from '@wordpress/components'; // Added Toolbar components if needed for custom controls

const NAMESPACE = 'vincentragosta-theme/button-icon';

// addButtonIconAttributes function remains the same (without hasIcon)
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

const withButtonIconEdit = createHigherOrderComponent((OriginalBlockEdit) => {
    return (props) => {
        // Crucial: Early exit for other blocks
        if (props.name !== 'core/button') {
            return <OriginalBlockEdit {...props} />;
        }

        // console.log('VINCENTRAGOSTA_DEBUG: withButtonIconEdit HOC running for:', props.name);
        // console.log('VINCENTRAGOSTA_DEBUG: Button Icon Data available:', window.vincentragostaButtonIconData);


        const { attributes, setAttributes, className, isSelected, clientId } = props;
        const { selectedIcon, iconPosition, text, url, linkTarget, rel, placeholder, style, backgroundColor, textColor, gradient, width, justification } = attributes; // Added more core button attributes

        const [iconData, setIconData] = useState({
            options: [{ label: __('— No Icon —', 'vincentragosta'), value: '' }],
            contentMap: {},
        });

        useEffect(() => {
            if (window.vincentragostaButtonIconData) {
                let fetchedOptions = window.vincentragostaButtonIconData.iconOptions || [];
                if (!fetchedOptions.find(opt => opt.value === '')) {
                    fetchedOptions = [{ label: __('— No Icon —', 'vincentragosta'), value: '' }, ...fetchedOptions];
                } else if (fetchedOptions.length > 0 && fetchedOptions[0].value !== '') {
                    const noIconOpt = fetchedOptions.find(opt => opt.value === '');
                    fetchedOptions = fetchedOptions.filter(opt => opt.value !== '');
                    if (noIconOpt) fetchedOptions.unshift(noIconOpt);
                }
                setIconData({
                    options: fetchedOptions.length > 0 ? fetchedOptions : [{ label: __('No icons found', 'vincentragosta'), value: '' }],
                    contentMap: window.vincentragostaButtonIconData.iconContentMap || {},
                });
            } else {
                // console.warn('Button Icon Data (vincentragostaButtonIconData) not found. SVG selector will not work.');
                setIconData(prev => ({...prev, options: [{ label: __('Error loading icons', 'vincentragosta'), value: '' }]}));
            }
        }, []);

        const handleIconChange = (newIconFile) => {
            if (newIconFile === '') {
                setAttributes({ selectedIcon: '', iconPosition: 'left' });
            } else {
                setAttributes({ selectedIcon: newIconFile });
            }
        };

        const inspectorUI = (
            <InspectorControls>
                <PanelBody title={__('Icon Settings', 'vincentragosta')} initialOpen={true}>
                    <SelectControl
                        label={__('Select Icon', 'vincentragosta')}
                        value={selectedIcon}
                        options={iconData.options}
                        onChange={handleIconChange}
                        help={__('Select an SVG icon. Choose "— No Icon —" to remove.', 'vincentragosta')}
                    />
                    {selectedIcon && selectedIcon !== '' && (
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
        );

        const currentSvgContent = selectedIcon && iconData.contentMap[selectedIcon]
            ? iconData.contentMap[selectedIcon]
            : null;

        // If no icon is actively selected OR the content for it isn't found,
        // render the original BlockEdit component, but make sure to pass our inspector controls to it.
        if (!selectedIcon || !currentSvgContent) {
            return (
                <Fragment>
                    {inspectorUI} {/* Our custom controls */}
                    <OriginalBlockEdit {...props} /> {/* Original button editor */}
                </Fragment>
            );
        }

        // If an icon IS selected and content IS available, render our custom preview
        const iconElement = (
            <BaseControl className="wp-block-button__icon-editor-preview" label="" hideLabelFromVision={true}>
                <span className="vrt-button-icon-editor-svg" dangerouslySetInnerHTML={{ __html: currentSvgContent }} />
            </BaseControl>
        );

        // We pass through relevant style attributes to the link span.
        // The core button uses a generated className for styles too (e.g., `is-style-outline`).
        // `props.className` on the wrapper div contains `wp-block-button` and alignment classes.
        // The `attributes.className` is for custom classes from the "Advanced" panel.

        const linkClasses = [
            'wp-block-button__link',
            attributes.className, // Custom classes from advanced panel
            // Dynamically add style-related classes if present (core button does this)
            // This is a simplification; core button has more complex style class generation
            backgroundColor ? 'has-background' : '',
            textColor ? 'has-text-color' : '',
            gradient ? 'has-gradient' : '',
            (style && style.elements && style.elements.link && style.elements.link.border && style.elements.link.border.radius !== undefined) ? 'has-custom-border-radius' : '',
        ].filter(Boolean).join(' ');

        const linkStyle = {
            display: 'inline-flex',
            alignItems: 'center',
            gap: '0.5em',
            flexDirection: iconPosition === 'right' ? 'row-reverse' : 'row',
            ...(backgroundColor && !gradient && { backgroundColor: `var(--wp--preset--color--${backgroundColor})` }),
            ...(textColor && { color: `var(--wp--preset--color--${textColor})` }),
            ...(gradient && { background: `var(--wp--preset--gradient--${gradient})` }),
            ...(style && style.elements && style.elements.link && style.elements.link.border && style.elements.link.border.radius !== undefined && { borderRadius: style.elements.link.border.radius }),
            // Padding might also come from attributes.style.spacing.padding
            ...(style && style.spacing && style.spacing.padding && {
                paddingTop: style.spacing.padding.top,
                paddingRight: style.spacing.padding.right,
                paddingBottom: style.spacing.padding.bottom,
                paddingLeft: style.spacing.padding.left,
            }),
        };


        return (
            <Fragment>
                {/* Render BlockControls from OriginalBlockEdit if needed, or reimplement necessary ones */}
                {/* The original BlockEdit would render its own BlockControls for link, etc. */}
                {/* We need to ensure those are still available. */}
                <BlockControls>
                    {/* Example: If you needed to add a custom control to the block toolbar
                    <ToolbarGroup>
                        <ToolbarButton icon="edit" label="My Control" onClick={() => console.log('Toolbar clicked')} />
                    </ToolbarGroup>
                    */}
                </BlockControls>
                {inspectorUI} {/* Our custom controls in the sidebar */}

                {/* Pass through props to OriginalBlockEdit to allow its controls (like link popover) to function,
                    but we will visually hide its main output if our icon is active by not rendering it.
                    This is the trickiest part. The OriginalBlockEdit is responsible for the link popover.
                    If we completely replace its render, the popover context is lost.
                */}
                <div {...props.wrapperProps} className={props.className}> {/* Use wrapperProps if available, or props.className */}
                    <span
                        className={linkClasses}
                        style={linkStyle}
                        // The href, target, rel are typically managed by the LinkControl within OriginalBlockEdit.
                        // By not rendering OriginalBlockEdit's visual output, we might lose direct interaction
                        // with these if they are not attributes directly on RichText.
                        // The URL is part of the RichText format 'core/link'.
                    >
                        {iconElement}
                        <RichText
                            identifier="text" // Core button uses this
                            tagName="span" // Core button might use 'a' if url is set, or 'span' then wrap.
                            // For simplicity and flex, span is fine here, link is via format.
                            value={text}
                            onChange={(newText) => setAttributes({ text: newText })}
                            placeholder={placeholder || __('Add text…', 'vincentragosta')}
                            allowedFormats={['core/bold', 'core/italic', 'core/link']}
                            withoutInteractiveFormatting
                            className="wp-block-button__text" // Not a core class, but can be used for styling
                            keepPlaceholderOnFocus
                            // Ensure other necessary props are passed if core button's RichText uses them
                        />
                    </span>
                </div>
                {/* Render the original BlockEdit but make it visually hidden if our custom preview is active.
                  This is a common pattern to allow its internal mechanics (like LinkControl) to still work.
                  However, this can be fragile.
                  A better way for very complex core blocks is often to use their provided "slots" or filter
                  more specific, smaller parts if available, rather than replacing the whole edit render.
                  Given core/button doesn't have many slots for this kind of modification, replacing parts of
                  the visual output is often attempted.
                */}
                <div style={{ display: 'none' }}>
                    <OriginalBlockEdit {...props} />
                </div>
            </Fragment>
        );
    };
}, 'withButtonIconEdit');


// console.log('VINCENTRAGOSTA_DEBUG: Registering button icon editor.BlockEdit filter (implicit icon via dropdown).');
addFilter(
    'editor.BlockEdit',
    `${NAMESPACE}/with-button-icon-edit`,
    withButtonIconEdit
);