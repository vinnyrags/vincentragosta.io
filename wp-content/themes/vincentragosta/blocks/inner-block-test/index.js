import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save'; // Even though save returns null, import and include it
import metadata from './block.json'; // Import block.json

import './style.scss'; // Styles for frontend and editor preview
import './editor.scss'; // Styles specific to the editor

registerBlockType(metadata.name, {
    /**
     * @see ./edit.js
     */
    edit: Edit,

    /**
     * @see ./save.js
     */
    save: save, // This function returns null for blocks using a render callback

    // Spread all other properties from block.json, including attributes and supports
    ...metadata,
});