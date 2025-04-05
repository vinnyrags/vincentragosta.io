import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    RichText,
    InspectorControls,
    URLInputButton, // Use URLInput for better UX
    BlockControls,
    AlignmentToolbar,
} from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    SelectControl,
    Button,
    ToggleControl,
    ToolbarGroup,
    ToolbarButton,
    Icon, // For icons in buttons
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { plus, trash } from '@wordpress/icons'; // Import icons

/**
 * Editor Styles.
 */
import './editor.scss';

export default function Edit({ attributes, setAttributes, clientId }) {
    const { title, subtitle, links = [], svgAsset, align } = attributes;

    // Get block props, including alignment class automatically if supported
    const blockProps = useBlockProps({
        className: `hero-block-editor-wrapper`, // Add a wrapper class if needed
    });

    // State for fetched SVG options
    const [svgOptions, setSvgOptions] = useState([
        { label: __('Loading SVGs...', 'vincentragosta'), value: '' },
    ]);

    // --- Fetch SVG Options (Example) ---
    useEffect(() => {
        // In a real theme, fetch this via REST API or use wp_localize_script / wp_add_inline_script
        // For now, using placeholder data similar to what PHP would provide
        const fetchedOptions = [
            { label: __('Select SVG', 'vincentragosta'), value: '' },
            { label: 'Squiggle', value: 'squiggle.svg' },
            { label: 'Squiggle 1', value: 'squiggle-1.svg' },
            { label: 'Squiggle 2', value: 'squiggle-2.svg' },
            { label: 'Squiggle 3', value: 'squiggle-3.svg' },
            { label: 'Squiggle 4', value: 'squiggle-4.svg' },
        ];
        setSvgOptions(fetchedOptions);
    }, []); // Empty dependency array means run once on mount

    // --- Handlers ---
    const onChangeTitle = (newTitle) => setAttributes({ title: newTitle });
    const onChangeSubtitle = (newSubtitle) => setAttributes({ subtitle: newSubtitle });
    const onChangeSvgAsset = (newSvg) => setAttributes({ svgAsset: newSvg });
    const onChangeAlign = ( newAlign ) => setAttributes( { align: newAlign } ); // Let core handle undefined

    // --- Link Management ---
    const handleAddLink = () => {
        const newLinks = [
            ...links,
            {
                id: `link-${clientId}-${Date.now()}`, // Unique enough key for editor
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
                const newText = link.text || (post && post.title) || ''; // Auto-fill text from selected post if empty
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

    // --- SVG Preview (Basic Placeholder) ---
    // A more advanced version could fetch and render the actual SVG via REST
    const SvgPreview = ({ asset }) => {
        if (!asset) return <div className="hero-block-editor__svg-placeholder">[No SVG Selected]</div>;
        const labelObj = svgOptions.find(opt => opt.value === asset);
        return <div className="hero-block-editor__svg-preview">Selected: {labelObj ? labelObj.label : asset}</div>;
    };

    return (
        <>
            {/* -- Block Controls (Toolbar) -- */}
            <BlockControls>
                <AlignmentToolbar
                    value={ align }
                    onChange={ onChangeAlign }
                />
                {/* Add other toolbar controls if needed */}
            </BlockControls>

            {/* -- Inspector Controls (Sidebar) -- */}
            <InspectorControls>
                <PanelBody title={__('Links', 'vincentragosta')} initialOpen={true}>
                    {links.map((link, index) => (
                        <div key={link.id} className="hero-block-editor__link-item">
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
                                isSmall // Make button less prominent
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

                <PanelBody title={__('SVG Asset', 'vincentragosta')} initialOpen={true}>
                    <SelectControl
                        label={__('Select SVG', 'vincentragosta')}
                        value={svgAsset}
                        options={svgOptions}
                        onChange={onChangeSvgAsset}
                    />
                </PanelBody>
                {/* Add Panels for color, spacing etc. if needed */}
            </InspectorControls>

            {/* -- Block Content Area -- */}
            <div {...blockProps}>
                {/* Apply layout classes for editor preview */}
                <div className="hero-block__content">
                    <RichText
                        tagName="h1"
                        className="hero-block__title" // Use same class as frontend
                        value={title}
                        onChange={onChangeTitle}
                        placeholder={__('Enter Hero Title...', 'vincentragosta')}
                        allowedFormats={['core/bold', 'core/italic']} // Example formats
                        withoutInteractiveFormatting // Optional: simpler toolbar
                    />
                    <RichText
                        tagName="p"
                        className="hero-block__subtitle" // Use same class as frontend
                        value={subtitle}
                        onChange={onChangeSubtitle}
                        placeholder={__('Enter subtitle...', 'vincentragosta')}
                        allowedFormats={['core/bold', 'core/italic', 'core/link']}
                    />
                    <div className="hero-block__links">
                        {/* Show simplified representation or placeholder for links in editor */}
                        {links.length === 0 && <p className="hero-block-editor__links-placeholder">{__('[Add links in sidebar]', 'vincentragosta')}</p>}
                        {links.map((link, index) => (
                            <span key={link.id} className="wp-block-button__link hero-block__link is-editor-preview">
                  {link.text || '[Link Text]'}
               </span>
                        ))}
                    </div>
                </div>
                <div className="hero-block__svg">
                    {/* Render simple preview */}
                    <SvgPreview asset={svgAsset} />
                </div>
            </div>
        </>
    );
}