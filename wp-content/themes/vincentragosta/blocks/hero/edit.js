import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    RichText,
    InspectorControls,
    BlockControls,
    AlignmentToolbar,
    InnerBlocks,
} from '@wordpress/block-editor';

import {
    PanelBody,
    SelectControl,
    TextControl,
} from '@wordpress/components';

import { useState, useEffect } from '@wordpress/element';

import './editor.scss';

const ALLOWED_BLOCKS = [ 'core/buttons' ];

export default function Edit({ attributes, setAttributes, clientId }) {
    const { title, subtitle, svgAsset, align, videoUrl } = attributes;

    // Add the 'hero--has-video' class conditionally
    const blockProps = useBlockProps({
        className: videoUrl ? 'hero--has-video' : '',
    });

    const [blockData, setBlockData] = useState({
        svgOptions: [{ label: __('Loading...', 'vincentragosta'), value: '' }],
        svgContent: {},
    });

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
            console.error('Error: vincentragostaHeroBlockData not found on window. SVG selection/preview will not work.');
            setBlockData(prevData => ({
                ...prevData,
                svgOptions: [{ label: __('Error: Data unavailable', 'vincentragosta'), value: '' }]
            }));
        }
    }, []);


    const svgOptions = blockData.svgOptions;
    const svgContentMap = blockData.svgContent;
    const onChangeTitle = (newTitle) => setAttributes({ title: newTitle });
    const onChangeSubtitle = (newSubtitle) => setAttributes({ subtitle: newSubtitle });
    const onChangeSvgAsset = (newSvg) => setAttributes({ svgAsset: newSvg });
    const onChangeAlign = ( newAlign ) => setAttributes( { align: newAlign === undefined ? null : newAlign } );
    const onChangeVideoUrl = (newVideoUrl) => setAttributes({ videoUrl: newVideoUrl });

    const currentSvgContent = svgAsset && typeof svgContentMap[svgAsset] === 'string' && svgContentMap[svgAsset].trim() !== ''
        ? svgContentMap[svgAsset]
        : null;

    return (
        <>
            <BlockControls>
                <AlignmentToolbar value={align} onChange={onChangeAlign} />
            </BlockControls>

            <InspectorControls>
                <PanelBody title={__('SVG Asset', 'vincentragosta')} initialOpen={true}>
                    <SelectControl
                        label={__('Select SVG', 'vincentragosta')}
                        value={svgAsset}
                        options={svgOptions}
                        onChange={onChangeSvgAsset}
                    />
                    {svgAsset && (
                        <p>
                            <strong>{__('Selected:', 'vincentragosta')}</strong> {svgAsset}
                        </p>
                    )}
                </PanelBody>
                <PanelBody title={__('Video Background', 'vincentragosta')} initialOpen={true}>
                    <TextControl
                        label={__('Video URL', 'vincentragosta')}
                        value={videoUrl}
                        onChange={onChangeVideoUrl}
                        help={__('Enter URL for background video. The video will autoplay, be muted, and loop.', 'vincentragosta')}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                { videoUrl && (
                    <video
                        className="hero__video"
                        src={videoUrl}
                        autoPlay
                        muted
                        loop
                        playsInline
                    />
                )}
                <div className="hero__content">
                    <RichText
                        tagName="h1"
                        className="hero__title"
                        value={title}
                        onChange={onChangeTitle}
                        placeholder={__('Enter Hero Title...', 'vincentragosta')}
                        allowedFormats={['core/bold', 'core/italic', 'vincentragosta/inline-block']}
                        withoutInteractiveFormatting
                    />
                    <div className="hero__links">
                        <InnerBlocks
                            allowedBlocks={ALLOWED_BLOCKS}
                        />
                    </div>
                </div>
                <div className="hero__svg">
                    {currentSvgContent ? (
                        <div
                            className="hero-editor__svg-preview is-loaded"
                            dangerouslySetInnerHTML={{ __html: currentSvgContent }}
                        />
                    ) : (
                        <div className="hero-editor__svg-placeholder">
                            {svgAsset
                                ? __('SVG preview unavailable or not found.', 'vincentragosta')
                                : __('[No SVG Selected]', 'vincentragosta')}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}