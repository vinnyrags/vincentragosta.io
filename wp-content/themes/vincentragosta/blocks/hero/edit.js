import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    RichText,
    // InspectorControls,
    BlockControls,
    AlignmentToolbar,
    InnerBlocks, // Import InnerBlocks
} from '@wordpress/block-editor';
// import {
//     PanelBody,
//     SelectControl,
// } from '@wordpress/components';
// import { useState, useEffect } from '@wordpress/element';

import './editor.scss';

// Define the allowed blocks inside the Hero block
const ALLOWED_BLOCKS = [ 'core/buttons' ];

// Removed MY_TEMPLATE and templateLock for debugging simplicity

export default function Edit({ attributes, setAttributes, clientId }) {
    const { title, subtitle, svgAsset, align } = attributes;
    // Apply block props to the main wrapper div
    const blockProps = useBlockProps();

    // State and effect for SVG data remain the same
    // const [blockData, setBlockData] = useState({
    //     svgOptions: [{ label: __('Loading...', 'vincentragosta'), value: '' }],
    //     svgContent: {},
    // });
    // useEffect(() => {
    //     if (window.vincentragostaHeroBlockData) {
    //         const options = Array.isArray(window.vincentragostaHeroBlockData.svgOptions)
    //             ? window.vincentragostaHeroBlockData.svgOptions
    //             : [{ label: __('Error loading options', 'vincentragosta'), value: '' }];
    //         const content = typeof window.vincentragostaHeroBlockData.svgContent === 'object' && window.vincentragostaHeroBlockData.svgContent !== null
    //             ? window.vincentragostaHeroBlockData.svgContent
    //             : {};
    //         setBlockData({ svgOptions: options, svgContent: content });
    //     } else {
    //         console.error('Error: vincentragostaHeroBlockData not found on window.');
    //         setBlockData(prevData => ({ ...prevData, svgOptions: [{ label: __('Error: Data unavailable', 'vincentragosta'), value: '' }] }));
    //     }
    // }, []);
    // const svgOptions = blockData.svgOptions;
    // const svgContentMap = blockData.svgContent;


    // --- Event Handlers ---
    const onChangeTitle = (newTitle) => setAttributes({ title: newTitle });
    const onChangeSubtitle = (newSubtitle) => setAttributes({ subtitle: newSubtitle });
    const onChangeSvgAsset = (newSvg) => setAttributes({ svgAsset: newSvg });
    const onChangeAlign = ( newAlign ) => setAttributes( { align: newAlign === undefined ? null : newAlign } );

    // // Get SVG content (non-sprite version)
    // const currentSvgContent = svgAsset && typeof svgContentMap[svgAsset] === 'string' && svgContentMap[svgAsset].trim() !== ''
    //     ? svgContentMap[svgAsset]
    //     : null;

    return (
        <>
            <BlockControls>
                <AlignmentToolbar value={align} onChange={onChangeAlign} />
            </BlockControls>

            {/*<InspectorControls>*/}
                {/* SVG Panel - Keep this */}
                {/*<PanelBody title={__('SVG Asset', 'vincentragosta')} initialOpen={true}>*/}
                {/*    <SelectControl*/}
                {/*        label={__('Select SVG', 'vincentragosta')}*/}
                {/*        value={svgAsset}*/}
                {/*        options={svgOptions}*/}
                {/*        onChange={onChangeSvgAsset}*/}
                {/*    />*/}
                {/*</PanelBody>*/}
                {/* No Link Panel needed here anymore */}
            {/*</InspectorControls>*/}

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
                    <div className="hero-block__links"> {/* Keep this wrapper for layout */}
                        <InnerBlocks
                            allowedBlocks={ALLOWED_BLOCKS} // Still restrict to 'core/buttons'
                            // No template prop here
                            // No templateLock prop here
                        />
                    </div>
                </div>
                {/*/!* SVG Preview Area (non-sprite version) *!/*/}
                {/*<div className="hero-block__svg">*/}
                {/*    {currentSvgContent ? (*/}
                {/*        <div*/}
                {/*            className="hero-block-editor__svg-preview is-loaded"*/}
                {/*            dangerouslySetInnerHTML={{ __html: currentSvgContent }}*/}
                {/*        />*/}
                {/*    ) : (*/}
                {/*        <div className="hero-block-editor__svg-placeholder">*/}
                {/*            {svgAsset*/}
                {/*                ? __('SVG preview unavailable.', 'vincentragosta')*/}
                {/*                : __('[No SVG Selected]', 'vincentragosta')}*/}
                {/*        </div>*/}
                {/*    )}*/}
                {/*</div>*/}
            </div>
        </>
    );
}