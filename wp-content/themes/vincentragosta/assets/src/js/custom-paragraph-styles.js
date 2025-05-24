import { __ } from '@wordpress/i18n';
import { registerBlockStyle } from '@wordpress/blocks'; // Correct import for registerBlockStyle
import { domReady } from '@wordpress/dom-ready'; // Correct import for domReady

domReady( () => {
    // Register the "Small" style
    registerBlockStyle( 'core/paragraph', {
        name: 'small-text', // This will generate a class like .is-style-small-text
        label: __( 'Small', 'vincentragosta' ),
    } );

    // "Medium" will be the default appearance of the paragraph block
    // when no specific style (like Small or Large) is selected.
    // The UI will typically show "Default" for this state.

    // Register the "Large" style
    registerBlockStyle( 'core/paragraph', {
        name: 'large-text', // This will generate a class like .is-style-large-text
        label: __( 'Large', 'vincentragosta' ),
    } );
} );