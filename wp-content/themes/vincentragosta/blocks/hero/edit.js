import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    RichText,
    InspectorControls, // <-- Uncomment this
    BlockControls,
    AlignmentToolbar,
    InnerBlocks,
} from '@wordpress/block-editor';

import {
    PanelBody, // <-- Uncomment this
    SelectControl, // <-- Uncomment this
} from '@wordpress/components'; // <-- Make sure this import is present

import { useState, useEffect } from '@wordpress/element'; // <-- Uncomment this

import './editor.scss';

const ALLOWED_BLOCKS = [ 'core/buttons' ];

// Note: MY_TEMPLATE and templateLock were removed for debugging simplicity

export default function Edit({ attributes, setAttributes, clientId }) {
    const { title, subtitle, svgAsset, align } = attributes;

    const blockProps = useBlockProps();

    // --- SVG Functionality State and Effects --- <-- Uncomment these sections
    const [blockData, setBlockData] = useState({
        svgOptions: [{ label: __('Loading...', 'vincentragosta'), value: '' }],
        svgContent: {},
    });

    useEffect(() => {
        // Check if the localized data object exists on the window
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
            // Log an error if localized data is missing (check functions.php and build process)
            console.error('Error: vincentragostaHeroBlockData not found on window. SVG selection/preview will not work.');
            setBlockData(prevData => ({
                ...prevData,
                svgOptions: [{ label: __('Error: Data unavailable', 'vincentragosta'), value: '' }]
            }));
        }
    }, []); // Empty dependency array means this effect runs once on mount


    const svgOptions = blockData.svgOptions;
    const svgContentMap = blockData.svgContent;
    // --- End SVG Functionality State and Effects ---

    // --- Event Handlers ---
    const onChangeTitle = (newTitle) => setAttributes({ title: newTitle });
    const onChangeSubtitle = (newSubtitle) => setAttributes({ subtitle: newSubtitle });
    const onChangeSvgAsset = (newSvg) => setAttributes({ svgAsset: newSvg }); // <-- Uncomment this handler
    const onChangeAlign = ( newAlign ) => setAttributes( { align: newAlign === undefined ? null : newAlign } );

    // --- Get Current SVG Content for Editor Preview --- <-- Uncomment this
    const currentSvgContent = svgAsset && typeof svgContentMap[svgAsset] === 'string' && svgContentMap[svgAsset].trim() !== ''
        ? svgContentMap[svgAsset]
        : null;
    // --- End Get Current SVG Content ---


    return (
        <>
            <BlockControls>
                <AlignmentToolbar value={align} onChange={onChangeAlign} />
            </BlockControls>

            {/* --- Inspector Controls for SVG --- */} {/* <-- Uncomment this whole block */}
            <InspectorControls>
                <PanelBody title={__('SVG Asset', 'vincentragosta')} initialOpen={true}>
                    <SelectControl
                        label={__('Select SVG', 'vincentragosta')}
                        value={svgAsset}
                        options={svgOptions}
                        onChange={onChangeSvgAsset} // Use the uncommented handler
                    />
                    {/* Optionally display file path if selected */}
                    {svgAsset && (
                        <p>
                            <strong>{__('Selected:', 'vincentragosta')}</strong> {svgAsset}
                        </p>
                    )}
                </PanelBody>
                {/* Add other panels here if needed */}
            </InspectorControls>
            {/* --- End Inspector Controls --- */}

            {/* Block Content Area */}
            {/* Apply blockProps here */}
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
                        placeholder={__('Enter subtitle...','vincentragosta')}
                        allowedFormats={['core/bold', 'core/italic', 'core/link']}
                    />
                    {/* InnerBlocks Area for Buttons */}
                    <div className="hero-block__links">
                        <InnerBlocks
                            allowedBlocks={ALLOWED_BLOCKS}
                            // No template prop here
                            // No templateLock prop here
                        />
                    </div>
                </div>

                {/* --- SVG Preview Area (non-sprite version) --- */} {/* <-- Uncomment this whole block */}
                <div className="hero-block__svg">
                    {/* Display the fetched SVG content or a placeholder */}
                    {currentSvgContent ? (
                        <div
                            className="hero-block-editor__svg-preview is-loaded"
                            // Use dangerouslySetInnerHTML to render the SVG markup
                            dangerouslySetInnerHTML={{ __html: currentSvgContent }}
                        />
                    ) : (
                        <div className="hero-block-editor__svg-placeholder">
                            {svgAsset
                                ? __('SVG preview unavailable or not found.', 'vincentragosta') // More specific message
                                : __('[No SVG Selected]', 'vincentragosta')}
                        </div>
                    )}
                </div>
                {/* --- End SVG Preview Area --- */}

            </div>
        </>
    );
}