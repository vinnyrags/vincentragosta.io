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
        svgOptions: [{ label: __('Loading...', 'child-theme'), value: '' }],
        svgContent: {},
    });

    useEffect(() => {
        if (window.childThemeHeroBlockData) {
            const options = Array.isArray(window.childThemeHeroBlockData.svgOptions)
                ? window.childThemeHeroBlockData.svgOptions
                : [{ label: __('Error loading options', 'child-theme'), value: '' }];

            const content = typeof window.childThemeHeroBlockData.svgContent === 'object' && window.childThemeHeroBlockData.svgContent !== null
                ? window.childThemeHeroBlockData.svgContent
                : {};

            setBlockData({
                svgOptions: options,
                svgContent: content,
            });
        } else {
            console.error('Error: childThemeHeroBlockData not found on window. SVG selection/preview will not work.');
            setBlockData(prevData => ({
                ...prevData,
                svgOptions: [{ label: __('Error: Data unavailable', 'child-theme'), value: '' }]
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
                <PanelBody title={__('Video Background', 'child-theme')} initialOpen>
                    <TextControl
                        label={__('Video URL', 'child-theme')}
                        value={videoUrl}
                        onChange={onChangeVideoUrl}
                        help={__('Enter a video URL to use it as a background. This will hide the SVG option.', 'child-theme')}
                    />
                </PanelBody>

                {!videoUrl && (
                    <PanelBody title={__('SVG Asset', 'child-theme')} initialOpen>
                        <SelectControl
                            label={__('Select SVG', 'child-theme')}
                            value={svgAsset}
                            options={svgOptions}
                            onChange={onChangeSvgAsset}
                        />
                        {svgAsset && (
                            <p>
                                <strong>{__('Selected:', 'child-theme')}</strong> {svgAsset}
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
