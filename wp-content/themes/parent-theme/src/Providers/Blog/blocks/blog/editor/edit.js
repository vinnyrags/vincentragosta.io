import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
	const { displayMode, postsPerPage, category } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Blog Settings', 'parent-theme')}>
					<SelectControl
						label={__('Display Mode', 'parent-theme')}
						value={displayMode}
						options={[
							{ label: __('Latest', 'parent-theme'), value: 'latest' },
							{ label: __('By Category', 'parent-theme'), value: 'category' },
							{ label: __('All', 'parent-theme'), value: 'all' },
						]}
						onChange={(value) => setAttributes({ displayMode: value })}
					/>
					<RangeControl
						label={__('Posts Per Page', 'parent-theme')}
						value={postsPerPage}
						onChange={(value) => setAttributes({ postsPerPage: value })}
						min={1}
						max={24}
						help={!postsPerPage ? __('Using WordPress Reading setting.', 'parent-theme') : ''}
						resetFallbackValue={undefined}
					/>
					{displayMode === 'category' && (
						<TextControl
							label={__('Category Slug', 'parent-theme')}
							value={category}
							onChange={(value) => setAttributes({ category: value })}
							help={__('Enter the category slug to filter by.', 'parent-theme')}
						/>
					)}
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="parent-theme/blog"
				attributes={attributes}
			/>
		</>
	);
}
