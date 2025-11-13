( function ( $ ) {
	$( function () {
		var $navTabs = $( '.nav-tab-wrapper .nav-tab' );

		if ( ! $navTabs.length ) {
			return;
		}

		function activateTab( $tab ) {
			$navTabs.removeClass( 'nav-tab-active' );
			$tab.addClass( 'nav-tab-active' );
		}

		$navTabs.on( 'click', function ( event ) {
			activateTab( $( this ) );
		} );

		// Activate tab based on hash on load.
		if ( window.location.hash ) {
			var $targetTab = $navTabs.filter( '[href="' + window.location.hash + '"]' );
			if ( $targetTab.length ) {
				activateTab( $targetTab );
			}
		}
	} );
} )( window.jQuery );

