import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import metadata from './block.json'; // Import metadata
import './style.scss'; // Styles for frontend + editor
import './editor.scss'; // Styles for editor only

registerBlockType(metadata.name, {
    /**
     * @see ./edit.js
     */
    edit: Edit,

    /**
     * @see ./save.js
     */
    save, // Will return null because we use PHP rendering
});