/**
 * Disables Gutenberg blocks via JavaScript.
 *
 * Block lists are passed from PHP via wp_localize_script,
 * allowing child themes to filter them server-side.
 *
 * Filters available in PHP:
 * - 'theme/disabled_block_types' - Filter block types to disable
 * - 'theme/disabled_embed_variations' - Filter embed variations to disable
 */
(function () {
	const { unregisterBlockType, unregisterBlockVariation } = wp.blocks;
	const { domReady } = wp;

	// Get filtered lists from PHP (set via wp_localize_script)
	const config = window.themeDisabledBlocks || {
		blockTypes: [],
		embedVariations: [],
		blockVariations: {},
	};

	domReady(function () {
		// Unregister block types
		config.blockTypes.forEach(function (blockName) {
			if (wp.blocks.getBlockType(blockName)) {
				unregisterBlockType(blockName);
			}
		});

		// Unregister embed variations
		config.embedVariations.forEach(function (variation) {
			unregisterBlockVariation('core/embed', variation);
		});

		// Unregister block variations (keyed by parent block name)
		Object.entries(config.blockVariations || {}).forEach(function ([blockName, variations]) {
			variations.forEach(function (variation) {
				unregisterBlockVariation(blockName, variation);
			});
		});
	});
})();
