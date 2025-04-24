// file: wp-content/themes/vincentragosta/blocks/hero/edit.js

import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    RichText,
    InspectorControls,
    URLInputButton,
    BlockControls,
    AlignmentToolbar,
} from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl, // Keep TextControl for URL
    SelectControl,
    Button,
    ToggleControl,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { plus, trash } from '@wordpress/icons';

import './editor.scss';

// --- Main Edit Component ---
export default function Edit({ attributes, setAttributes, clientId }) {
    // Note: This code reverts to the non-sprite version based on your provided file
    const { title, subtitle, links = [], svgAsset, align } = attributes;
    const blockProps = useBlockProps({
        className: `hero-block-editor-wrapper`,
    });

    // State for localized data (non-sprite version)
    const [blockData, setBlockData] = useState({
        svgOptions: [{ label: __('Loading...', 'vincentragosta'), value: '' }],
        svgContent: {},
    });

    // useEffect to load localized data (non-sprite version)
    useEffect(() => {
        if (window.vincentragostaHeroBlockData) {
            const options = Array.isArray(window.vincentragostaHeroBlockData.svgOptions)
                ? window.vincentragostaHeroBlockData.svgOptions
                : [{ label: __('Error loading options', 'vincentragosta'), value: '' }];

            const content = typeof window.vincentragostaHeroBlockData.svgContent === 'object' && window.vincentragostaHeroBlockData.svgContent !== null
                ? window.vincentragostaHeroBlockData.svgContent
                : {};

            setBlockData({
                svgOptions: options,
                svgContent: content,
            });
        } else {
            console.error('Error: vincentragostaHeroBlockData not found on window.');
            setBlockData(prevData => ({
                ...prevData,
                svgOptions: [{ label: __('Error: Data unavailable', 'vincentragosta'), value: '' }]
            }));
        }
    }, []);

    const svgOptions = blockData.svgOptions;
    const svgContentMap = blockData.svgContent;

    // --- Event Handlers ---
    const onChangeTitle = (newTitle) => setAttributes({ title: newTitle });
    const onChangeSubtitle = (newSubtitle) => setAttributes({ subtitle: newSubtitle });
    const onChangeSvgAsset = (newSvg) => setAttributes({ svgAsset: newSvg });
    const onChangeAlign = ( newAlign ) => setAttributes( { align: newAlign === undefined ? null : newAlign } );

    const handleAddLink = () => {
        const newLinks = [
            ...links,
            {
                id: `link-${clientId}-${Date.now()}`,
                url: '',
                text: __('Button Text', 'vincentragosta'), // Default text for new button
                opensInNewTab: false,
            },
        ];
        setAttributes({ links: newLinks });
    };

    // This function now handles updates for URL and opensInNewTab from sidebar
    // AND text updates from the inline RichText component
    const handleUpdateLink = (index, key, value) => {
        const newLinks = links.map((link, i) => {
            if (i === index) {
                return { ...link, [key]: value };
            }
            return link;
        });
        setAttributes({ links: newLinks });
    };

    // This specific handler is still useful for the URLInputButton interaction
    const handleUpdateLinkUrl = (index, newUrl, post) => {
        const newLinks = links.map((link, i) => {
            if (i === index) {
                // Only auto-fill text if it's currently empty or the default placeholder
                const currentText = link.text || '';
                const newText = (currentText === '' || currentText === __('Button Text', 'vincentragosta')) && post && post.title
                    ? post.title
                    : currentText;
                return { ...link, url: newUrl, text: newText };
            }
            return link;
        });
        setAttributes({ links: newLinks });
    };

    const handleRemoveLink = (index) => {
        const newLinks = links.filter((_, i) => i !== index);
        setAttributes({ links: newLinks });
    };

    // Get SVG content (non-sprite version)
    const currentSvgContent = svgAsset && typeof svgContentMap[svgAsset] === 'string' && svgContentMap[svgAsset].trim() !== ''
        ? svgContentMap[svgAsset]
        : null;

    return (
        <>
            <BlockControls>
                <AlignmentToolbar value={align} onChange={onChangeAlign} />
            </BlockControls>

            <InspectorControls>
                {/* Link Panel - TextControl for link text is REMOVED */}
                <PanelBody title={__('Links Configuration', 'vincentragosta')} initialOpen={true}>
                    {links.map((link, index) => (
                        <div key={link.id || `hero-link-${index}`} className="hero-block-editor__link-item">
                            {/* TextControl for Link Text is REMOVED - Edit inline now */}
                            <p><strong>{__('Link #', 'vincentragosta')}{index + 1}:</strong> {link.text || <em>{__('Empty', 'vincentragosta')}</em>}</p>
                            <div className="hero-block-editor__link-url-wrapper">
                                <TextControl
                                    label={__('URL', 'vincentragosta')}
                                    value={ link.url || '' }
                                    onChange={ ( value ) => handleUpdateLink( index, 'url', value ) } // Use handleUpdateLink
                                    placeholder="https://"
                                />
                                <URLInputButton
                                    url={ link.url }
                                    onChange={ ( url, post ) => handleUpdateLinkUrl( index, url, post ) } // Specific handler for URLInput
                                />
                            </div>
                            <ToggleControl
                                label={__('Open in new tab', 'vincentragosta')}
                                checked={!!link.opensInNewTab}
                                onChange={(isChecked) => handleUpdateLink(index, 'opensInNewTab', isChecked)} // Use handleUpdateLink
                            />
                            <Button
                                label={__('Remove Link', 'vincentragosta')}
                                icon={trash}
                                isDestructive
                                isSmall
                                onClick={() => handleRemoveLink(index)}
                                className="hero-block-editor__remove-link"
                            />
                            <hr/>
                        </div>
                    ))}
                    <Button
                        icon={plus}
                        variant="secondary"
                        onClick={handleAddLink}
                        className="hero-block-editor__add-link"
                    >
                        {__('Add Link', 'vincentragosta')}
                    </Button>
                </PanelBody>
                {/* SVG Panel */}
                <PanelBody title={__('SVG Asset', 'vincentragosta')} initialOpen={true}>
                    <SelectControl
                        label={__('Select SVG', 'vincentragosta')}
                        value={svgAsset}
                        options={svgOptions}
                        onChange={onChangeSvgAsset}
                    />
                </PanelBody>
            </InspectorControls>

            {/* Block Content Area */}
            <div {...blockProps}>
                <div className="hero-block__content">
                    <RichText
                        tagName="h1"
                        className="hero-block__title"
                        value={title}
                        onChange={onChangeTitle}
                        placeholder={__('Enter Hero Title...', 'vincentragosta')}
                        allowedFormats={['core/bold', 'core/italic']}
                        withoutInteractiveFormatting
                    />
                    <RichText
                        tagName="p"
                        className="hero-block__subtitle"
                        value={subtitle}
                        onChange={onChangeSubtitle}
                        placeholder={__('Enter subtitle...', 'vincentragosta')}
                        allowedFormats={['core/bold', 'core/italic', 'core/link']}
                    />
                    {/* Links Area - NOW USES RichText for editing */}
                    <div className="hero-block__links">
                        {links.length === 0 && <p className="hero-block-editor__links-placeholder">{__('[Add links in sidebar]', 'vincentragosta')}</p>}
                        {links.map((link, index) => (
                            <RichText
                                key={link.id || `hero-link-rt-${index}`}
                                tagName="div" // Use div or span - doesn't represent actual link here
                                className="wp-block-button__link hero-block__link is-editable" // Add classes to style like a button
                                value={link.text}
                                onChange={(newText) => handleUpdateLink(index, 'text', newText)} // Update text in the array
                                placeholder={__('Button Text...', 'vincentragosta')}
                                allowedFormats={[]} // No bold/italic within button text usually
                                withoutInteractiveFormatting
                                keepPlaceholderOnFocus
                            />
                        ))}
                        {/* Optionally add a visual cue to add buttons via sidebar if needed */}
                    </div>
                </div>
                {/* SVG Preview Area (non-sprite version) */}
                <div className="hero-block__svg">
                    {currentSvgContent ? (
                        <div
                            className="hero-block-editor__svg-preview is-loaded"
                            dangerouslySetInnerHTML={{ __html: currentSvgContent }}
                        />
                    ) : (
                        <div className="hero-block-editor__svg-placeholder">
                            {svgAsset
                                ? __('SVG preview unavailable.', 'vincentragosta')
                                : __('[No SVG Selected]', 'vincentragosta')}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}