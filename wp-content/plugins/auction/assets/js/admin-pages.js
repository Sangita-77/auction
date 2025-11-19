( function ( $ ) {
	$( function () {
		var $navTabs = $( '.nav-tab-wrapper .nav-tab' );

		function activateTab( $tab ) {
			$navTabs.removeClass( 'nav-tab-active' );
			$tab.addClass( 'nav-tab-active' );
		}

		if ( $navTabs.length ) {
			$navTabs.on( 'click', function () {
				activateTab( $( this ) );
			} );

			if ( window.location.hash ) {
				var $targetTab = $navTabs.filter( '[href="' + window.location.hash + '"]' );
				if ( $targetTab.length ) {
					activateTab( $targetTab );
				}
			}
		}

		// Media uploader for badge field.
		var mediaFrame;
		var i18n = ( window.auctionAdminPages && window.auctionAdminPages.i18n ) ? window.auctionAdminPages.i18n : {};
		$( document ).on( 'click', '.auction-media-upload', function ( event ) {
			event.preventDefault();

			var target = $( this ).data( 'target' );
			var $input = $( '#' + target );

			if ( ! $input.length || ! wp || ! wp.media ) {
				return;
			}

			if ( mediaFrame ) {
				mediaFrame.off( 'select' );
			}

			mediaFrame = wp.media( {
				title: i18n.mediaTitle || 'Select Image',
				button: {
					text: i18n.mediaButton || 'Use image',
				},
				multiple: false,
			} );

			mediaFrame.on( 'select', function () {
				var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
				if ( attachment && attachment.url ) {
					$input.val( attachment.url ).trigger( 'change' );
				}
			} );

			mediaFrame.open();
		} );

		$( document ).on( 'click', '.auction-media-clear', function ( event ) {
			event.preventDefault();
			var target = $( this ).data( 'target' );
			var $input = $( '#' + target );

			if ( $input.length ) {
				$input.val( '' ).trigger( 'change' );
			}
		} );
	} );
} )( window.jQuery );
