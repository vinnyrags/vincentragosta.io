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
    TextControl,
    SelectControl,
    Button,
    ToggleControl,
} from '@wordpress/components';
// Make sure useEffect and useState are imported from @wordpress/element
import { useState, useEffect } from '@wordpress/element';
import { plus, trash } from '@wordpress/icons';

import './editor.scss';

// --- Main Edit Component ---
export default function Edit({ attributes, setAttributes, clientId }) {
    const { title, subtitle, links = [], svgAsset, align } = attributes;
    const blockProps = useBlockProps({
        className: `hero-block-editor-wrapper`,
    });

    // --- NEW: State for localized data ---
    const [blockData, setBlockData] = useState({
        // Initial default state before localized data is confirmed
        svgOptions: [{ label: __('Loading...', 'vincentragosta'), value: '' }],
        svgContent: {},
    });

    // --- NEW: useEffect to load localized data into state ONCE after mount ---
    useEffect(() => {
        // Check if the global variable exists from wp_localize_script
        if (window.vincentragostaHeroBlockData) {
            // Validate the structure slightly before setting state
            const options = Array.isArray(window.vincentragostaHeroBlockData.svgOptions)
                ? window.vincentragostaHeroBlockData.svgOptions
                : [{ label: __('Error loading options', 'vincentragosta'), value: '' }]; // Fallback on error

            const content = typeof window.vincentragostaHeroBlockData.svgContent === 'object' && window.vincentragostaHeroBlockData.svgContent !== null
                ? window.vincentragostaHeroBlockData.svgContent
                : {}; // Fallback on error

            // Update the component state with the actual data
            setBlockData({
                svgOptions: options,
                svgContent: content,
            });
            // console.log('Localized data loaded into component state:', { options, content }); // Optional: confirm state update
        } else {
            console.error('Error: vincentragostaHeroBlockData not found on window.');
            // Set an error state if preferred
            setBlockData(prevData => ({
                ...prevData, // Keep existing svgContent potentially
                svgOptions: [{ label: __('Error: Data unavailable', 'vincentragosta'), value: '' }]
            }));
        }
    }, []); // Empty dependency array [] means this effect runs only once when the component mounts

    // --- Use state for options and content throughout the component ---
    const svgOptions = blockData.svgOptions;
    const svgContentMap = blockData.svgContent;

    // --- Event Handlers (Remain the same) ---
    const onChangeTitle = (newTitle) => setAttributes({ title: newTitle });
    const onChangeSubtitle = (newSubtitle) => setAttributes({ subtitle: newSubtitle });
    const onChangeSvgAsset = (newSvg) => setAttributes({ svgAsset: newSvg });
    const onChangeAlign = ( newAlign ) => setAttributes( { align: newAlign === undefined ? null : newAlign } ); // Allow alignment reset

    const handleAddLink = () => {
        const newLinks = [
            ...links,
            {
                id: `link-${clientId}-${Date.now()}`,
                url: '',
                text: '',
                opensInNewTab: false,
            },
        ];
        setAttributes({ links: newLinks });
    };

    const handleUpdateLink = (index, key, value) => {
        const newLinks = links.map((link, i) => {
            if (i === index) {
                return { ...link, [key]: value };
            }
            return link;
        });
        setAttributes({ links: newLinks });
    };

    const handleUpdateLinkUrl = (index, newUrl, post) => {
        const newLinks = links.map((link, i) => {
            if (i === index) {
                const newText = link.text || (post && post.title) || '';
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


    // Get SVG content for the selected asset from the state
    const currentSvgContent = svgAsset && typeof svgContentMap[svgAsset] === 'string' && svgContentMap[svgAsset].trim() !== ''
        ? svgContentMap[svgAsset]
        : null;

    return (
        <>
            <BlockControls>
                <AlignmentToolbar value={align} onChange={onChangeAlign} />
            </BlockControls>

            <InspectorControls>
                {/* Link Panel */}
                <PanelBody title={__('Links', 'vincentragosta')} initialOpen={true}>
                    {links.map((link, index) => (
                        <div key={link.id || `hero-link-${index}-${link.url}`} className="hero-block-editor__link-item">
                            <TextControl
                                label={__('Link Text', 'vincentragosta')}
                                value={link.text || ''}
                                onChange={(value) => handleUpdateLink(index, 'text', value)}
                                className="hero-block-editor__link-text"
                            />
                            <div className="hero-block-editor__link-url-wrapper">
                                <TextControl
                                    label={__('URL', 'vincentragosta')}
                                    value={ link.url || '' }
                                    onChange={ ( value ) => handleUpdateLink( index, 'url', value ) }
                                    placeholder="https://"
                                />
                                <URLInputButton
                                    url={ link.url }
                                    onChange={ ( url, post ) => handleUpdateLinkUrl( index, url, post ) }
                                />
                            </div>
                            <ToggleControl
                                label={__('Open in new tab', 'vincentragosta')}
                                checked={!!link.opensInNewTab}
                                onChange={(isChecked) => handleUpdateLink(index, 'opensInNewTab', isChecked)}
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
                        // Use options from state now
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
                    <div className="hero-block__links">
                        {links.length === 0 && <p className="hero-block-editor__links-placeholder">{__('[Add links in sidebar]', 'vincentragosta')}</p>}
                        {links.map((link) => (
                            <span key={link.id || `${link.text}-${link.url}`} className="wp-block-button__link hero-block__link is-editor-preview">
								{link.text || '[Link Text]'}
							</span>
                        ))}
                    </div>
                </div>
                {/* SVG Preview Area */}
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