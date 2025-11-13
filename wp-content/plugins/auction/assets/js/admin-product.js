( function ( $, config ) {
	$( function () {
		var $panel = $( '#auction_product_data' );

		if ( ! $panel.length ) {
			return;
		}

		var $subtabs = $panel.find( '.auction-subtabs button' );
		var $sections = $panel.find( '.auction-subtab-section' );

		$subtabs.on( 'click', function ( event ) {
			event.preventDefault();

			var target = $( this ).attr( 'data-target' );

			$subtabs.removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );

			$sections.removeClass( 'is-active' );
			$sections.filter( '[data-section="' + target + '"]' ).addClass( 'is-active' );
		} );

		// Initialize default active section.
		$subtabs.first().trigger( 'click' );

		// Automatic bidding rules repeater.
		$panel.on( 'click', '.auction-add-rule', function ( event ) {
			event.preventDefault();

			var $tableBody = $panel.find( '.auction-rules-table tbody' );
			var index = $tableBody.find( 'tr' ).not( '.no-rules' ).length;
			var template = $panel.find( '#auction-rule-template' ).html();

			template = template
				.replace( /__index__/g, index )
				.replace( /__add_rule__/g, config?.i18n?.delete_rule || 'Remove' );

			$tableBody.find( '.no-rules' ).remove();
			$tableBody.append( template );
		} );

		$panel.on( 'click', '.auction-remove-rule', function ( event ) {
			event.preventDefault();
			$( this ).closest( 'tr' ).remove();

			var $tableBody = $panel.find( '.auction-rules-table tbody' );

			if ( ! $tableBody.find( 'tr' ).length ) {
				$tableBody.append(
					'<tr class="no-rules"><td colspan="4">' +
						( config?.i18n?.no_rules || 'No advanced rules defined yet.' ) +
						'</td></tr>'
				);
			}
		} );
	} );
} )( window.jQuery, window.AuctionProductConfig || {} );

