( function ( blocks, element ) {
	var el = element.createElement;

	blocks.registerBlockType( 'docrenders/pdf-button', {
		edit: function () {
			return el(
				'div',
				{ className: 'docrenders-pdf-wrap' },
				el(
					'button',
					{
						className: 'docrenders-pdf-btn',
						disabled: true,
						style: {
							display: 'inline-flex',
							alignItems: 'center',
							padding: '.55em 1.2em',
							fontSize: '1rem',
							fontFamily: 'inherit',
							lineHeight: '1.4',
							border: 'none',
							borderRadius: '4px',
							background: '#2271b1',
							color: '#fff',
							opacity: '0.8',
							cursor: 'default',
						},
					},
					'Download PDF'
				),
				el(
					'p',
					{
						style: {
							fontSize: '12px',
							color: '#757575',
							margin: '6px 0 0',
						},
					},
					'DocRenders — PDF renders on the front end.'
				)
			);
		},

		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element );
