import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
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

const ALLOWED_BLOCKS = ['core/buttons', 'core/heading', 'core/paragraph'];

export default function Edit({ attributes, setAttributes }) {
    const { svgAsset, align, videoUrl } = attributes;

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
    const onChangeSvgAsset = (newSvg) => setAttributes({ svgAsset: newSvg });
    const onChangeAlign = (newAlign) => setAttributes({ align: newAlign === undefined ? null : newAlign });
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
                <PanelBody title={__('Video Background', 'vincentragosta')} initialOpen>
                    <TextControl
                        label={__('Video URL', 'vincentragosta')}
                        value={videoUrl}
                        onChange={onChangeVideoUrl}
                        help={__('Enter a video URL to use it as a background. This will hide the SVG option.', 'vincentragosta')}
                    />
                </PanelBody>

                {!videoUrl && (
                    <PanelBody title={__('SVG Asset', 'vincentragosta')} initialOpen>
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
                )}
            </InspectorControls>

            <div {...blockProps}>
                {videoUrl ? (
                    <video
                        className="hero__video"
                        src={videoUrl}
                        autoPlay
                        muted
                        loop
                        playsInline
                    />
                ) : (
                    currentSvgContent && (
                        <div
                            className="hero__svg"
                            dangerouslySetInnerHTML={{ __html: currentSvgContent }}
                        />
                    )
                )}
                <div className="hero__content">
                    <InnerBlocks allowedBlocks={ALLOWED_BLOCKS} />
                </div>
            </div>
        </>
    );
}
