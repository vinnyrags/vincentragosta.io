import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
	const { displayMode, postsPerPage, category } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Blog Settings', 'ix')}>
					<SelectControl
						label={__('Display Mode', 'ix')}
						value={displayMode}
						options={[
							{ label: __('Latest', 'ix'), value: 'latest' },
							{ label: __('By Category', 'ix'), value: 'category' },
							{ label: __('All', 'ix'), value: 'all' },
						]}
						onChange={(value) => setAttributes({ displayMode: value })}
					/>
					<RangeControl
						label={__('Posts Per Page', 'ix')}
						value={postsPerPage}
						onChange={(value) => setAttributes({ postsPerPage: value })}
						min={1}
						max={24}
						help={!postsPerPage ? __('Using WordPress Reading setting.', 'ix') : ''}
						resetFallbackValue={undefined}
					/>
					{displayMode === 'category' && (
						<TextControl
							label={__('Category Slug', 'ix')}
							value={category}
							onChange={(value) => setAttributes({ category: value })}
							help={__('Enter the category slug to filter by.', 'ix')}
						/>
					)}
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="ix/blog"
				attributes={attributes}
			/>
		</>
	);
}
