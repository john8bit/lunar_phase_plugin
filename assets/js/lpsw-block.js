( function ( blocks, element, i18n, components, blockEditor, serverSideRender ) {
	const { registerBlockType } = blocks;
	const { createElement: el, Fragment } = element;
	const { __ } = i18n;
	const { PanelBody, TextControl, ToggleControl } = components;
	const { InspectorControls } = blockEditor;
	const ServerSideRender = serverSideRender;

	registerBlockType( 'celestial-web-development/lunar-phase-widget', {
		title: __( 'Celestial Lunar Phase', 'celestial-lunar-phase' ),
		description: __( 'Display the current moon phase with a phase image, moonrise, and moonset.', 'celestial-lunar-phase' ),
		icon: 'moon',
		category: 'widgets',
		keywords: [ __( 'moon', 'celestial-lunar-phase' ), __( 'astronomy', 'celestial-lunar-phase' ), __( 'moonrise', 'celestial-lunar-phase' ) ],
		attributes: {
			location: { type: 'string', default: '' },
			date: { type: 'string', default: '' },
			title: { type: 'string', default: '' },
			showLocation: { type: 'boolean', default: true },
			showCredit: { type: 'boolean', default: true },
		},
		edit: function ( props ) {
			const attrs = props.attributes;
			return el( Fragment, {},
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Widget Settings', 'celestial-lunar-phase' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Location Override', 'celestial-lunar-phase' ),
							help: __( 'Leave blank to use the plugin default location.', 'celestial-lunar-phase' ),
							value: attrs.location,
							onChange: function ( value ) { props.setAttributes( { location: value } ); },
						} ),
						el( TextControl, {
							label: __( 'Date Override', 'celestial-lunar-phase' ),
							help: __( 'Format: YYYY-MM-DD. Leave blank to use today.', 'celestial-lunar-phase' ),
							value: attrs.date,
							onChange: function ( value ) { props.setAttributes( { date: value } ); },
						} ),
						el( TextControl, {
							label: __( 'Custom Title', 'celestial-lunar-phase' ),
							help: __( 'Leave blank to use the default title from plugin settings.', 'celestial-lunar-phase' ),
							value: attrs.title,
							onChange: function ( value ) { props.setAttributes( { title: value } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show Location Label', 'celestial-lunar-phase' ),
							checked: attrs.showLocation,
							onChange: function ( value ) { props.setAttributes( { showLocation: value } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show Data Credit', 'celestial-lunar-phase' ),
							checked: attrs.showCredit,
							onChange: function ( value ) { props.setAttributes( { showCredit: value } ); },
						} )
					)
				),
				el( 'div', { className: 'lpsw-editor-preview' },
					el( ServerSideRender, {
						block: 'celestial-web-development/lunar-phase-widget',
						attributes: attrs,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender );
