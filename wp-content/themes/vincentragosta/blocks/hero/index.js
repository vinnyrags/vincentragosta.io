// file: wp-content/themes/vincentragosta/blocks/hero/index.js
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import metadata from './block.json'; // Import metadata
import './style.scss'; // Styles for frontend + editor
import './editor.scss'; // Styles for editor only

console.log('Hero Block Debug: Loading index.js');
console.log('Hero Block Debug: Imported metadata -> ', metadata);
console.log('Hero Block Debug: Imported metadata.supports -> ', metadata.supports);


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

console.log('Hero Block Debug: Settings for registerBlockType -> ', settings);
console.log('Hero Block Debug: Settings.supports -> ', settings.supports);


registerBlockType(metadata.name, settings);

console.log('Hero Block Debug: registerBlockType called for name:', metadata.name, 'with settings:', settings);