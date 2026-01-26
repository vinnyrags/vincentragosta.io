import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import metadata from './block.json'; // Import metadata
import './style.scss';
import './editor.scss';

const settings = {
    /**
     * @see ./edit.js
     */
    edit: Edit,

    /**
     * @see ./save.js
     */
    save, // Will return null because we use PHP rendering

    // Spread all properties from metadata, including 'supports'
    ...metadata,

    // Explicitly ensure supports.innerBlocks is true here as a fallback check,
    // though spreading metadata should handle it if metadata is correct.
    // supports: {
    //     ...metadata.supports, // Keep other supports from JSON
    //     innerBlocks: true,
    // }
};

registerBlockType(metadata.name, settings);