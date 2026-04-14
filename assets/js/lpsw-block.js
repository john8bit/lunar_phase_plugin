( function ( blocks, element, i18n, components, blockEditor, serverSideRender ) {
	const { registerBlockType } = blocks;
	const { createElement: el, Fragment } = element;
	const { __ } = i18n;
	const { PanelBody, TextControl, ToggleControl } = components;
	const { InspectorControls } = blockEditor;
	const ServerSideRender = serverSideRender;

	registerBlockType( 'celestial-web-development/lunar-phase-widget', {
		title: __( 'Lunar Phase Widget', 'lunar-phase-stock-widget' ),
		description: __( 'Display the current moon phase with a phase image, moonrise, and moonset.', 'lunar-phase-stock-widget' ),
		icon: 'moon',
		category: 'widgets',
		keywords: [ __( 'moon', 'lunar-phase-stock-widget' ), __( 'astronomy', 'lunar-phase-stock-widget' ), __( 'moonrise', 'lunar-phase-stock-widget' ) ],
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
					el( PanelBody, { title: __( 'Widget Settings', 'lunar-phase-stock-widget' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Location Override', 'lunar-phase-stock-widget' ),
							help: __( 'Leave blank to use the plugin default location.', 'lunar-phase-stock-widget' ),
							value: attrs.location,
							onChange: function ( value ) { props.setAttributes( { location: value } ); },
						} ),
						el( TextControl, {
							label: __( 'Date Override', 'lunar-phase-stock-widget' ),
							help: __( 'Format: YYYY-MM-DD. Leave blank to use today.', 'lunar-phase-stock-widget' ),
							value: attrs.date,
							onChange: function ( value ) { props.setAttributes( { date: value } ); },
						} ),
						el( TextControl, {
							label: __( 'Custom Title', 'lunar-phase-stock-widget' ),
							help: __( 'Leave blank to use the default title from plugin settings.', 'lunar-phase-stock-widget' ),
							value: attrs.title,
							onChange: function ( value ) { props.setAttributes( { title: value } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show Location Label', 'lunar-phase-stock-widget' ),
							checked: attrs.showLocation,
							onChange: function ( value ) { props.setAttributes( { showLocation: value } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show Data Credit', 'lunar-phase-stock-widget' ),
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
