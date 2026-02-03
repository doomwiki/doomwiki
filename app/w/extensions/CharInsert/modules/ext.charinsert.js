( function ( $, mw ) {
	var addClickHandlers = function ( $content ) {
		$content.find( 'a.mw-charinsert-item' ).each( function () {
			var $elm = $( this ),
				start = $elm.data( 'mw-charinsert-start' ),
				end = $elm.data( 'mw-charinsert-end' );
			if ( $elm.data( 'mw-charinsert-done' ) ) {
				return;
			}
			$elm.click( function ( e ) {
				e.preventDefault();
				mw.toolbar.insertTags( start, end, '' );
			} )
				.data( 'mw-charinsert-done', true )
				.attr( 'href', '#' );
		} );
	};
	// Normally <charinsert> appears outside of content area.
	// However, we also want to catch things like live preview,
	// so we use both the onready hook and wikipage.content.
	$( function () {
		addClickHandlers( $( document ) );
	} );
	mw.hook( 'wikipage.content' ).add( addClickHandlers );
} )( jQuery, mediaWiki );
