/* global jQuery, wp, fsnwSignatureKioskAdmin */
( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.fsnw-select-image', function ( event ) {
		event.preventDefault();

		var $button = $( this );
		var $input = $( $button.data( 'target-input' ) ? '#' + $button.data( 'target-input' ) : '#fsnw-image-id' );
		var $preview = $( $button.data( 'target-preview' ) || '.fsnw-image-preview' );

		var frame = wp.media( {
			title: fsnwSignatureKioskAdmin.selectImageTitle,
			multiple: false,
			library: { type: 'image' },
			button: { text: fsnwSignatureKioskAdmin.useImageLabel }
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var thumbnailUrl = ( attachment.sizes && attachment.sizes.thumbnail )
				? attachment.sizes.thumbnail.url
				: attachment.url;

			$input.val( attachment.id );
			$preview.html( $( '<img>', { src: thumbnailUrl, alt: '' } ) );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.fsnw-remove-image', function ( event ) {
		event.preventDefault();

		var $button = $( this );
		var $input = $( $button.data( 'target-input' ) ? '#' + $button.data( 'target-input' ) : '#fsnw-image-id' );
		var $preview = $( $button.data( 'target-preview' ) || '.fsnw-image-preview' );

		$input.val( '' );
		$preview.empty();
	} );
} )( jQuery );
