import { registerBlockType } from '@wordpress/blocks';
import { post as icon } from '@wordpress/icons';
import Edit from './edit';
import metadata from '../block.json';

registerBlockType(metadata.name, {
	icon,
	edit: Edit,
	save: () => null,
});
