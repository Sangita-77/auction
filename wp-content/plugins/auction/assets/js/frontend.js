( function ( $, config ) {
	function decodeHtmlEntity( str ) {
		var textarea = document.createElement( 'textarea' );
		textarea.innerHTML = str;
		return textarea.value;
	}

	function formatCurrency( amount ) {
		var decimals = parseInt( config.currency.decimals, 10 );
		var decimalSep = config.currency.decimal_separator || '.';
		var thousandSep = config.currency.thousand_separator || ',';

		var formatted = parseFloat( amount ).toFixed( isNaN( decimals ) ? 2 : decimals );

		var parts = formatted.split( '.' );
		parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thousandSep );

		formatted = parts.join( decimalSep );

		// Decode HTML entities in currency symbol (e.g., &#8377; becomes ₹)
		var symbol = decodeHtmlEntity( config.currency.symbol );

		switch ( config.currency.position ) {
			case 'right':
				return formatted + symbol;
			case 'left_space':
				return symbol + ' ' + formatted;
			case 'right_space':
				return formatted + ' ' + symbol;
			default:
				return symbol + formatted;
		}
	}

	function updateFormState( $form, currentBid, nextBid ) {
		$form.attr( 'data-current-bid', currentBid );
		$form.attr( 'data-next-bid', nextBid );

		var $bidInput = $form.find( 'input[name="bid_amount"]' );
		$bidInput.attr( 'min', nextBid );
		$bidInput.val( nextBid );

		$form.closest( '.auction-single-panel' ).find( '.auction-current-bid' ).text( formatCurrency( currentBid ) );
		$form.closest( '.auction-single-panel' ).find( '.auction-next-bid' ).text( formatCurrency( nextBid ) );
	}

	function submitBidRequest( $form, payload ) {
		var $feedback = $form.find( '.auction-bid-feedback' );

		$feedback.removeClass( 'is-error is-success' ).text( '' );
		$form.block( { message: null } );

		$.post( config.ajax_url, payload )
			.done( function ( response ) {
				if ( ! response || ! response.success ) {
					throw new Error( response && response.data ? response.data.message : config.i18n.error_generic );
				}

				var data = response.data;
				var currentBid = parseFloat( data.current_bid );
				var manualIncrement = parseFloat( $form.data( 'manual-increment' ) );
				var nextBid = currentBid + manualIncrement;

				updateFormState( $form, currentBid, nextBid );

				$feedback.addClass( 'is-success' ).text(
					data.was_outbid ? config.i18n.bid_outbid : config.i18n.bid_submitted
				);
			} )
			.fail( function ( jqXHR ) {
				var message = config.i18n.error_generic;

				if ( jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ) {
					message = jqXHR.responseJSON.data.message;
				}

				$feedback.addClass( 'is-error' ).text( message );
			} )
			.always( function () {
				$form.unblock();
			} );
	}

	function showBidConfirmation( $form, $modal, payload ) {
		var $amountEl = $modal.find( '.auction-bid-confirmation__amount' );
		var $autoNote = $modal.find( '.auction-bid-confirmation__auto-note' );

		$amountEl.text( formatCurrency( parseFloat( payload.bid_amount ) ) );

		if ( payload.is_auto && payload.max_auto_amount ) {
			$autoNote.text(
				config.i18n.auto_bid_notice.replace(
					'%s',
					formatCurrency( parseFloat( payload.max_auto_amount ) )
				)
			);
			$autoNote.removeAttr( 'hidden' );
		} else {
			$autoNote.attr( 'hidden', true ).text( '' );
		}

		$modal
			.addClass( 'is-visible' )
			.attr( 'aria-hidden', 'false' )
			.data( 'payload', payload );

		$modal.find( '.auction-confirm-bid' )
			.off( 'click' )
			.on( 'click', function () {
				hideBidConfirmation( $modal );
				submitBidRequest( $form, payload );
			} );

		$modal.find( '.auction-cancel-bid' )
			.off( 'click' )
			.on( 'click', function () {
				hideBidConfirmation( $modal );
			} );
	}

	function hideBidConfirmation( $modal ) {
		$modal
			.removeClass( 'is-visible' )
			.attr( 'aria-hidden', 'true' )
			.removeData( 'payload' );

		$modal.find( '.auction-bid-confirmation__auto-note' )
			.attr( 'hidden', true )
			.text( '' );
	}

	function handleBidForm( $form ) {
		var $panel = $form.closest( '.auction-single-panel' );
		var $bidPanel = $panel.find( '.auction-bid-panel' );
		var $openBidButton = $panel.find( '.auction-open-bid-panel' );
		var $modal = $panel.find( '.auction-bid-confirmation' );
		var $loginModal = $panel.find( '.auction-login-modal' );
		var $registerModal = $panel.find( '.auction-register-modal' );
		var requiresLogin = !! Number( $panel.data( 'requires-login' ) );
		var enableRegisterModal = !! Number( $panel.data( 'enable-register-modal' ) );

		// Check if auction is ended - disable bid button for closed auctions
		var $statusEl = $panel.find( '.auction-status' );
		var auctionStatus = $statusEl.length ? $statusEl.data( 'auction-status' ) : '';
		var isEnded = auctionStatus === 'ended';

		if ( $openBidButton.length ) {
			// Disable bid button if auction is ended
			if ( isEnded ) {
				$openBidButton.prop( 'disabled', true ).addClass( 'disabled' );
			} else {
				$openBidButton.on( 'click', function () {
					if ( requiresLogin ) {
						showLoginModal( $loginModal );
						return;
					}

					$bidPanel.prop( 'hidden', false );
					$openBidButton.prop( 'disabled', true );
				} );
			}
		}

		// Disable bid form submission if auction is ended
		if ( isEnded ) {
			$form.find( 'button[type="submit"]' ).prop( 'disabled', true );
		}

		$form.on( 'change', 'input[name="is_auto"]', function () {
			var checked = $( this ).is( ':checked' );
			var $autoField = $form.find( '.auction-auto-max-field' );

			if ( checked ) {
				$autoField.removeClass( 'hidden' );
				$autoField.find( 'input' ).attr( 'required', true );
			} else {
				$autoField.addClass( 'hidden' );
				$autoField.find( 'input' ).removeAttr( 'required' );
			}
		} );

		$form.on( 'submit', function ( event ) {
			event.preventDefault();

			if ( requiresLogin ) {
				showLoginModal( $loginModal );
				return;
			}

			var formData = {
				action: 'auction_place_bid',
				nonce: config.nonce,
				product_id: $form.data( 'product-id' ),
				bid_amount: $form.find( 'input[name="bid_amount"]' ).val(),
			};

			if ( $form.find( 'input[name="is_auto"]' ).is( ':checked' ) ) {
				formData.is_auto = 1;
				formData.max_auto_amount = $form.find( 'input[name="max_auto_amount"]' ).val();
			}

			showBidConfirmation( $form, $modal, formData );
		} );

		if ( $loginModal.length ) {
			$loginModal.find( '.auction-login-modal__close' ).on( 'click', function () {
				hideLoginModal( $loginModal );
			} );

			$loginModal.on( 'click', function ( event ) {
				if ( $( event.target ).is( '.auction-login-modal' ) ) {
					hideLoginModal( $loginModal );
				}
			} );

			if ( enableRegisterModal && $registerModal.length ) {
				$loginModal.find( '.auction-login-modal__register-link' ).on( 'click', function ( event ) {
					event.preventDefault();
					hideLoginModal( $loginModal );
					showRegisterModal( $registerModal );
				} );
			}

			$( document ).on( 'keyup', function ( event ) {
				if ( event.key === 'Escape' && $loginModal.hasClass( 'is-visible' ) ) {
					hideLoginModal( $loginModal );
				}
			} );
		}

		if ( enableRegisterModal && $registerModal.length ) {
			$registerModal.find( '.auction-register-modal__close' ).on( 'click', function () {
				hideRegisterModal( $registerModal );
			} );

			$registerModal.on( 'click', function ( event ) {
				if ( $( event.target ).is( '.auction-register-modal' ) ) {
					hideRegisterModal( $registerModal );
				}
			} );

			$( document ).on( 'keyup', function ( event ) {
				if ( event.key === 'Escape' && $registerModal.hasClass( 'is-visible' ) ) {
					hideRegisterModal( $registerModal );
				}
			} );
		}
	}
	function showLoginModal( $modal ) {
		$modal.addClass( 'is-visible' ).attr( 'aria-hidden', 'false' );
	}

	function hideLoginModal( $modal ) {
		$modal.removeClass( 'is-visible' ).attr( 'aria-hidden', 'true' );
	}

	function showRegisterModal( $modal ) {
		var $panel = $modal.closest( '.auction-single-panel' );
		var registerUrl = $panel.data( 'register-url' );
		var $content = $modal.find( '.auction-register-modal__content' );

		if ( ! config.register_form ) {
			if ( registerUrl ) {
				window.location.href = registerUrl;
				return;
			}
		}

		if ( ! $content.data( 'loaded' ) && config.register_form ) {
			$content.html( config.register_form );
			$content.data( 'loaded', true );
		}

		$modal.addClass( 'is-visible' ).attr( 'aria-hidden', 'false' );
	}

	function hideRegisterModal( $modal ) {
		$modal.removeClass( 'is-visible' ).attr( 'aria-hidden', 'true' );
	}


	function initCountdowns( context ) {
		var $countdowns = $( context ).find( '[data-countdown-target]' );

		if ( ! $countdowns.length ) {
			return;
		}

		function renderCountdown( $el ) {
			var target = parseInt( $el.data( 'countdown-target' ), 10 );
			var start  = parseInt( $el.data( 'countdown-start' ), 10 ) || 0;

			if ( ! target ) {
				return;
			}

			var now = Math.floor( Date.now() / 1000 );
			var diff = target - now;

			if ( start && now < start ) {
				var total = target - start;
				var elapsed = start - now;
				diff = total - elapsed;
			}

			if ( diff <= 0 ) {
				$el.text( '--:--:--' );

				// Check if we're on the auction page (where we want to show closed auctions if setting allows)
				// Note: If "Hide ended auctions" setting is enabled, PHP query filters them out, so they won't be in DOM
				var isAuctionPage = false;
				// Check URL query parameter
				if ( window.location.search.indexOf( 'auction_page=1' ) !== -1 ) {
					isAuctionPage = true;
				}
				// Also check URL path for /auctions/ rewrite rule
				if ( ! isAuctionPage && window.location.pathname.indexOf( '/auctions' ) !== -1 ) {
					isAuctionPage = true;
				}

				// Only hide product cards on archive/listing pages, not on single product pages or auction page.
				// On single product pages and auction page (when "Hide ended auctions" is disabled), show ended auctions.
				if ( ! $( 'body' ).hasClass( 'single-product' ) && ! isAuctionPage ) {
					var $productCard = $el.closest( '.product, .woocommerce-product, .wc-block-grid__product' );

					if ( $productCard.length && ! $productCard.data( 'auction-hidden' ) ) {
						$productCard
							.data( 'auction-hidden', true )
							.css( 'display', 'none' );
					}
				}

				return;
			}

			// Show countdown as "DDd HHh MMm" (no seconds).
			var days    = Math.floor( diff / 86400 ); // 60 * 60 * 24
			var hours   = Math.floor( ( diff % 86400 ) / 3600 );
			var minutes = Math.floor( ( diff % 3600 ) / 60 );

			var text =
				String( days ).padStart( 2, '0' ) + 'd ' +
				String( hours ).padStart( 2, '0' ) + 'h ' +
				String( minutes ).padStart( 2, '0' ) + 'm';

			$el.text( text );
		}

		setInterval( function () {
			$countdowns.each( function () {
				renderCountdown( $( this ) );
			} );
		}, 1000 );
	}

	function handleWatchlist() {
		$( document ).on( 'click', '.auction-watchlist-toggle', function ( event ) {
			event.preventDefault();

			var $button = $( this );

			var payload = {
				action: 'auction_toggle_watchlist',
				nonce: $button.data( 'nonce' ),
				product_id: $button.data( 'product-id' ),
			};

			$button.prop( 'disabled', true );

			$.post( config.ajax_url, payload )
				.done( function ( response ) {
					if ( ! response || ! response.success ) {
						throw new Error( response && response.data ? response.data.message : config.i18n.error_generic );
					}

					var isWatchlisted = response.data.watchlisted;
					$button
						.toggleClass( 'is-watchlisted', isWatchlisted )
						.text( isWatchlisted ? config.i18n.watch_added : config.i18n.watch_removed );
				} )
				.fail( function ( jqXHR ) {
					var message = config.i18n.error_generic;

					if ( jqXHR.status === 401 ) {
						message = config.i18n.login_required;
					} else if ( jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ) {
						message = jqXHR.responseJSON.data.message;
					}

					window.alert( message ); // eslint-disable-line no-alert
				} )
				.always( function () {
					$button.prop( 'disabled', false );
				} );
		} );
	}

	$( function () {
		$( '.auction-bid-form' ).each( function () {
			handleBidForm( $( this ) );
		} );

		initCountdowns( document.body );
		handleWatchlist();

		$(document).on('updated_checkout updated_product_list', function () {
        initCountdowns(document.body);
    });
	} );
} )( window.jQuery, window.AuctionFrontendConfig || {} );
