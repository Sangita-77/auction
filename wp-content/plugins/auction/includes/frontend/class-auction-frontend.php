<?php
/**
 * Frontend integration for the Auction plugin.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles WooCommerce storefront integration.
 */
class Auction_Frontend {

	/**
	 * Singleton instance.
	 *
	 * @var Auction_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Plugin path.
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Constructor.
	 *
	 * @param array $args Init args.
	 */
	private function __construct( array $args = array() ) {
		$this->plugin_path = $args['plugin_path'] ?? '';
		$this->plugin_url  = $args['plugin_url'] ?? '';

		add_action( 'init', array( $this, 'maybe_handle_registration_form' ) );

		$this->hooks();
	}

	/**
	 * Get singleton instance.
	 *
	 * @param array $args Arguments.
	 *
	 * @return Auction_Frontend
	 */
	public static function instance( array $args = array() ): Auction_Frontend {
		if ( null === self::$instance ) {
			self::$instance = new self( $args );
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_product_auction_panel' ), 25 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_loop_badge' ), 20 );

		add_action( 'pre_get_posts', array( $this, 'maybe_filter_catalog_queries' ) );

		add_action( 'wp_ajax_auction_place_bid', array( $this, 'ajax_place_bid' ) );
		add_action( 'wp_ajax_nopriv_auction_place_bid', array( $this, 'ajax_place_bid' ) );

		add_action( 'wp_ajax_auction_toggle_watchlist', array( $this, 'ajax_toggle_watchlist' ) );
		add_action( 'wp_ajax_nopriv_auction_toggle_watchlist', array( $this, 'ajax_toggle_watchlist' ) );

		add_shortcode( 'auction_watchlist', array( $this, 'render_watchlist_shortcode' ) );
		add_shortcode( 'auction_register_form', array( $this, 'render_registration_form_shortcode' ) );

		add_action( 'template_redirect', array( $this, 'restrict_ended_auction_access' ) );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'filter_ended_auction_visibility' ), 10, 2 );
	}

	/**
	 * Process registration form submission.
	 *
	 * @return void
	 */
	public function maybe_handle_registration_form(): void {
		if ( empty( $_POST['auction_register_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['auction_register_nonce'] ), 'auction_register_user' ) ) {
			wc_add_notice( __( 'Security check failed. Please try again.', 'auction' ), 'error' );
			return;
		}

		$first_name        = isset( $_POST['auction_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['auction_first_name'] ) ) : '';
		$last_name         = isset( $_POST['auction_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['auction_last_name'] ) ) : '';
		$email             = isset( $_POST['auction_email'] ) ? sanitize_email( wp_unslash( $_POST['auction_email'] ) ) : '';
		$password          = isset( $_POST['auction_password'] ) ? (string) wp_unslash( $_POST['auction_password'] ) : '';
		$confirm_password  = isset( $_POST['auction_confirm_password'] ) ? (string) wp_unslash( $_POST['auction_confirm_password'] ) : '';

		if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) || empty( $password ) || empty( $confirm_password ) ) {
			wc_add_notice( __( 'All fields are required.', 'auction' ), 'error' );
		}

		if ( ! is_email( $email ) ) {
			wc_add_notice( __( 'Please enter a valid email address.', 'auction' ), 'error' );
		}

		if ( $password !== $confirm_password ) {
			wc_add_notice( __( 'Passwords do not match.', 'auction' ), 'error' );
		}

		if ( email_exists( $email ) ) {
			wc_add_notice( __( 'An account with this email already exists.', 'auction' ), 'error' );
		}

		if ( wc_notice_count( 'error' ) > 0 ) {
			return;
		}

		$customer_id = wc_create_new_customer( $email, '', $password );

		if ( is_wp_error( $customer_id ) ) {
			wc_add_notice( $customer_id->get_error_message(), 'error' );
			return;
		}

		update_user_meta( $customer_id, 'first_name', $first_name );
		update_user_meta( $customer_id, 'last_name', $last_name );

		wp_set_current_user( $customer_id );
		wp_set_auth_cookie( $customer_id );

		wc_add_notice( __( 'Registration successful. You are now logged in.', 'auction' ), 'success' );

		$redirect = wp_get_referer() ?: wc_get_page_permalink( 'myaccount' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$enqueue = false;

		if ( is_product() ) {
			$product = wc_get_product();
			if ( $product && Auction_Product_Helper::is_auction_product( $product ) ) {
				$enqueue = true;
			}
		}

		$show_countdown_loop = Auction_Settings::is_enabled( 'show_countdown' ) || Auction_Settings::is_enabled( 'show_countdown_loop' );

		if ( ! $enqueue && ( is_shop() || is_product_category() || is_product_taxonomy() ) && $show_countdown_loop ) {
			$enqueue = true;
		}

		if ( ! $enqueue ) {
			return;
		}

		wp_enqueue_style(
			'auction-frontend',
			$this->plugin_url . 'assets/css/frontend.css',
			array(),
			Auction_Plugin::VERSION
		);

		wp_enqueue_script(
			'auction-frontend',
			$this->plugin_url . 'assets/js/frontend.js',
			array( 'jquery' ),
			Auction_Plugin::VERSION,
			true
		);

		wp_enqueue_script( 'jquery-blockui' );

		wp_localize_script(
			'auction-frontend',
			'AuctionFrontendConfig',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'auction_bid_nonce' ),
				'session_id'     => $this->get_or_set_session_id(),
				'register_form'  => wp_kses_post( $this->get_registration_form_markup( array(), false ) ),
				'currency'       => array(
					'symbol'             => get_woocommerce_currency_symbol(),
					'position'           => get_option( 'woocommerce_currency_pos', 'left' ),
					'decimals'           => wc_get_price_decimals(),
					'decimal_separator'  => wc_get_price_decimal_separator(),
					'thousand_separator' => wc_get_price_thousand_separator(),
				),
				'i18n'           => array(
					'bid_submitted'   => __( 'Bid submitted successfully!', 'auction' ),
					'bid_outbid'      => __( 'Your bid was submitted but you have already been outbid.', 'auction' ),
					'error_generic'   => __( 'An error occurred. Please try again.', 'auction' ),
					'login_required'  => __( 'Please log in to use this feature.', 'auction' ),
					'watch_added'     => __( 'Added to your watchlist.', 'auction' ),
					'watch_removed'   => __( 'Removed from your watchlist.', 'auction' ),
					'auto_bid_notice' => __( 'You set a maximum automatic bid of %s.', 'auction' ),
				),
			)
		);
	}

	/**
	 * Restrict access to ended auction product pages.
	 *
	 * @return void
	 */
	public function restrict_ended_auction_access(): void {
		if ( is_admin() || ! is_singular( 'product' ) ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		if ( ! $product || ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return;
		}

		$config = Auction_Product_Helper::get_config( $product );

		if ( 'ended' !== Auction_Product_Helper::get_auction_status( $config ) ) {
			return;
		}

		$winner_id = absint( $product->get_meta( '_auction_winner_user_id', true ) );

		if ( $winner_id && get_current_user_id() === $winner_id ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		include get_query_template( '404' );
		exit;
	}

	/**
	 * Hide ended auction products from catalog visibility.
	 *
	 * @param bool $visible   Current visibility.
	 * @param int  $product_id Product ID.
	 *
	 * @return bool
	 */
	public function filter_ended_auction_visibility( bool $visible, int $product_id ): bool {
		if ( is_admin() ) {
			return $visible;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return $visible;
		}

		$config = Auction_Product_Helper::get_config( $product );

		if ( 'ended' !== Auction_Product_Helper::get_auction_status( $config ) ) {
			return $visible;
		}

		$winner_id = absint( $product->get_meta( '_auction_winner_user_id', true ) );

		if ( $winner_id && get_current_user_id() === $winner_id ) {
			return true;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Render auction block on single product page.
	 *
	 * @return void
	 */
	public function render_single_product_auction_panel(): void {
		global $product;
		$login_page_url       = wc_get_page_permalink( 'myaccount' );
		$register_page_url    = apply_filters( 'auction_register_page_url', $this->get_registration_page_url() );
		$requires_login       = ! is_user_logged_in();
		$register_modal       = apply_filters( 'auction_enable_registration_modal', true );

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return;
		}

		$config = Auction_Product_Helper::get_config( $product );
		$state  = Auction_Product_Helper::get_runtime_state( $product );
		$status = Auction_Product_Helper::get_auction_status( $config );

		$current_bid = $state['winning_bid_id'] ? $state['current_bid'] : Auction_Product_Helper::get_start_price( $config );
		$current_bid = $current_bid > 0 ? $current_bid : 0;

		$manual_increment = Auction_Product_Helper::get_manual_increment( $config );
		$next_bid         = $state['winning_bid_id'] ? $current_bid + $manual_increment : max( $current_bid, $manual_increment );

		$leading_bid = Auction_Bid_Manager::get_leading_bid( $product->get_id() );
		$latest_bid  = $leading_bid ?: null;
		if ( $latest_bid ) {
			$latest_bid['display_name']   = $this->format_bidder_name( $latest_bid, $config );
			$latest_bid['display_amount'] = Auction_Product_Helper::to_float( $latest_bid['bid_amount'] ?? 0 );
			$latest_bid['display_time']   = ! empty( $latest_bid['created_at'] )
				? wp_date(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					strtotime( $latest_bid['created_at'] )
				)
				: '';
		}
		$bid_history = Auction_Bid_Manager::get_bid_history( $product->get_id(), 10, true );
		$bid_history = array_map(
			function ( $item ) use ( $config ) {
				return array(
					'name'   => $this->format_bidder_name( $item, $config ),
					'amount' => Auction_Product_Helper::to_float( $item['bid_amount'] ?? 0 ),
					'time'   => $item['created_at'] ?? '',
					'status' => $item['status'] ?? '',
				);
			},
			$bid_history
		);

		$user_id       = get_current_user_id();
		$watchlist     = $this->get_watchlist_ids( $user_id );
		$is_watchlisted = $user_id && in_array( $product->get_id(), $watchlist, true );

		wc_get_template(
			'frontend/single-auction-panel.php',
			array(
				'product'          => $product,
				'status'           => $status,
				'config'           => $config,
				'state'            => $state,
				'current_bid'      => $current_bid,
				'next_bid'         => $next_bid,
				'manual_increment' => $manual_increment,
				'leading_bid'      => $leading_bid,
				'latest_bid'       => $latest_bid,
				'bid_history'      => $bid_history,
				'is_watchlisted'   => $is_watchlisted,
				'watchlist_nonce'  => wp_create_nonce( 'auction_watchlist_nonce' ),
				'login_page_url'   => $login_page_url,
				'register_page_url'=> $register_page_url,
				'requires_login'   => $requires_login,
				'register_modal'   => $register_modal,
			),
			'',
			$this->plugin_path . 'templates/'
		);
	}

	/**
	 * Render badge/countdown in loop.
	 *
	 * @return void
	 */

	//  ------------------------------------------------------
	// public function render_loop_badge(): void {
	// 	global $product;

	// 	if ( ! $product instanceof WC_Product ) {
	// 		return;
	// 	}

	// 	if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
	// 		return;
	// 	}

	// 	if ( ! Auction_Settings::is_enabled( 'show_countdown_loop' ) && ! Auction_Settings::is_enabled( 'custom_badge_enable' ) ) {
	// 		return;
	// 	}

	// 	$config = Auction_Product_Helper::get_config( $product );
	// 	$status = Auction_Product_Helper::get_auction_status( $config );

	// 	$end_timestamp = $config['end_timestamp'] ?: 0;

	// 	printf(
	// 		'<div class="auction-loop-meta" data-auction-product="%1$d" data-auction-status="%2$s" %3$s>',
	// 		esc_attr( $product->get_id() ),
	// 		esc_attr( $status ),
	// 		$end_timestamp ? 'data-auction-end="' . esc_attr( $end_timestamp ) . '"' : ''
	// 	);

	// 	if ( Auction_Settings::is_enabled( 'custom_badge_enable' ) ) {
	// 		$badge_url = esc_url( Auction_Settings::get( 'custom_badge_asset', '' ) );

	// 		echo '<span class="auction-badge">';
	// 		if ( $badge_url ) {
	// 			echo '<img src="' . $badge_url . '" alt="' . esc_attr__( 'Auction', 'auction' ) . '" />';
	// 		} else {
	// 			esc_html_e( 'Auction', 'auction' );
	// 		}
	// 		echo '</span>';
	// 	}

	// 	if ( Auction_Settings::is_enabled( 'show_countdown_loop' ) && $end_timestamp ) {
	// 		echo '<span class="auction-countdown" data-countdown-target="' . esc_attr( $end_timestamp ) . '"></span>';
	// 	}

	// 	echo '</div>';
	// }

	public function render_loop_badge(): void {
		global $product;
	
		if ( ! $product instanceof WC_Product ) {
			return;
		}
	
		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return;
		}
	
		$config          = Auction_Product_Helper::get_config( $product );
		$status          = Auction_Product_Helper::get_auction_status( $config );
		$start_timestamp = $config['start_timestamp'] ?: 0;
		$end_timestamp   = $config['end_timestamp'] ?: 0;
	
		echo '<div class="auction-loop-meta" data-auction-product="' . esc_attr( $product->get_id() ) . '" data-auction-status="' . esc_attr( $status ) . '" ' . ($end_timestamp ? 'data-auction-end="' . esc_attr( $end_timestamp) . '"' : '') . '>';
	
		// Badge
		if ( Auction_Settings::is_enabled( 'custom_badge_enable' ) ) {
			$badge_url = esc_url( Auction_Settings::get( 'custom_badge_asset', '' ) );
			echo '<span class="auction-badge">';
			if ( $badge_url ) {
				echo '<img src="' . $badge_url . '" alt="' . esc_attr__( 'Auction', 'auction' ) . '" />';
			} else {
				esc_html_e( 'Auction', 'auction' );
			}
			echo '</span>';
		}
	
		// Countdown
		$start_attr = $start_timestamp ? ' data-countdown-start="' . esc_attr( $start_timestamp ) . '"' : '';

		if ( $end_timestamp && Auction_Settings::is_enabled( 'show_countdown_loop' ) ) {
			echo '<span class="auction-countdown" data-countdown-target="' . esc_attr( $end_timestamp ) . '"' . $start_attr . '></span>';
		}
	
		// Current bid
		$state = Auction_Product_Helper::get_runtime_state( $product );
		$current_bid = $state['winning_bid_id'] ? $state['current_bid'] : Auction_Product_Helper::get_start_price( $config );
		$current_bid = $current_bid > 0 ? $current_bid : 0;
		$currency = get_woocommerce_currency_symbol();
	
		echo '<div class="auction-loop-info">';
		if ( $end_timestamp ) {
			echo '<span class="auction-end">Auction ends: <strong>' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $end_timestamp ) . '</strong></span><br>';
			echo '<span class="auction-countdown" data-countdown-target="' . esc_attr( $end_timestamp ) . '"' . $start_attr . '></span>';
		}

		// if ( $end_timestamp ) {
		// 	echo '<span class="auction-countdown" data-countdown-target="' . esc_attr( $end_timestamp ) . '"></span>';
		// }
		
		echo '<span class="auction-current-bid">Current bid: <strong>' . wc_price( $current_bid ) . '</strong></span>';
		echo '</div>';
	
		echo '</div>';
	}
	
	// ------------------------------------------------------------

	/**
	 * Filter catalog to respect auction visibility settings.
	 *
	 * @param WP_Query $query Query.
	 *
	 * @return void
	 */
	public function maybe_filter_catalog_queries( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$hide_ended        = Auction_Settings::is_enabled( 'hide_ended' );
		$hide_future       = Auction_Settings::is_enabled( 'hide_future' );
		$hide_out_of_stock = Auction_Settings::is_enabled( 'hide_out_of_stock' );
		$show_shop         = Auction_Settings::is_enabled( 'show_on_shop', true );

		if ( $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) ) {
			$meta_query = (array) $query->get( 'meta_query', array() );

			$meta_query['relation'] = 'AND';

			if ( ! $show_shop && $query->is_post_type_archive( 'product' ) ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_auction_enabled',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_auction_enabled',
						'value'   => 'yes',
						'compare' => '!=',
					),
				);
			}

			$current_time = current_time( 'mysql' );

			if ( $hide_ended ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_auction_end_time',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_auction_end_time',
						'value'   => $current_time,
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				);
			}

			if ( $hide_future ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_auction_start_time',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_auction_start_time',
						'value'   => $current_time,
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
				);
			}

			if ( $hide_out_of_stock ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_stock_status',
						'value'   => 'outofstock',
						'compare' => '!=',
					),
					array(
						'key'     => '_stock_status',
						'compare' => 'NOT EXISTS',
					),
				);
			}

			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * AJAX handler for placing bids.
	 *
	 * @return void
	 */
	public function ajax_place_bid(): void {
		check_ajax_referer( 'auction_bid_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to place a bid.', 'auction' ),
				),
				401
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid product.', 'auction' ),
				)
			);
		}

		$user_id    = get_current_user_id();
		$session_id = $this->get_or_set_session_id();

		$args = array(
			'product_id'      => $product_id,
			'user_id'         => $user_id,
			'session_id'      => $session_id,
			'bid_amount'      => isset( $_POST['bid_amount'] ) ? wc_clean( wp_unslash( $_POST['bid_amount'] ) ) : 0,
			'is_auto'         => isset( $_POST['is_auto'] ) && '1' === $_POST['is_auto'],
			'max_auto_amount' => isset( $_POST['max_auto_amount'] ) ? wc_clean( wp_unslash( $_POST['max_auto_amount'] ) ) : null,
			'ip_address'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		$result = Auction_Bid_Manager::place_bid( $args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'result'      => $result['status'],
				'current_bid' => $result['current_bid'],
				'was_outbid'  => $result['was_outbid'],
			)
		);
	}

	/**
	 * AJAX handler for watchlist toggling.
	 *
	 * @return void
	 */
	public function ajax_toggle_watchlist(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to manage your watchlist.', 'auction' ),
				),
				401
			);
		}

		check_ajax_referer( 'auction_watchlist_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid product.', 'auction' ),
				)
			);
		}

		$user_id    = get_current_user_id();
		$watchlist  = $this->get_watchlist_ids( $user_id );
		$in_list    = in_array( $product_id, $watchlist, true );
		$watchlist  = $in_list ? array_diff( $watchlist, array( $product_id ) ) : array_merge( $watchlist, array( $product_id ) );
		$watchlist  = array_map( 'absint', $watchlist );
		$watchlist  = array_values( array_unique( $watchlist ) );
		update_user_meta( $user_id, 'auction_watchlist', $watchlist );

		wp_send_json_success(
			array(
				'action'      => $in_list ? 'removed' : 'added',
				'watchlisted' => ! $in_list,
			)
		);
	}

	/**
	 * Render watchlist shortcode.
	 *
	 * @return string
	 */
	public function render_watchlist_shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your auction watchlist.', 'auction' ) . '</p>';
		}

		$user_id    = get_current_user_id();
		$watchlist  = $this->get_watchlist_ids( $user_id );

		if ( empty( $watchlist ) ) {
			return '<p>' . esc_html__( 'Your watchlist is empty.', 'auction' ) . '</p>';
		}

		$args = array(
			'post_type'      => 'product',
			'post__in'       => $watchlist,
			'orderby'        => 'post__in',
			'posts_per_page' => -1,
		);

		$products = new WP_Query( $args );

		ob_start();

		if ( $products->have_posts() ) {
			echo '<ul class="auction-watchlist">';
			while ( $products->have_posts() ) {
				$products->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( ! $product ) {
					continue;
				}

				echo '<li>';
				echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a>';
				echo '</li>';
			}
			echo '</ul>';
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html__( 'No products found.', 'auction' ) . '</p>';
		}

		return ob_get_clean();
	}

	/**
	 * Render registration form shortcode.
	 *
	 * @return string
	 */
	public function render_registration_form_shortcode(): string {
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already logged in.', 'auction' ) . '</p>';
		}

		$values = array(
			'first_name' => isset( $_POST['auction_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['auction_first_name'] ) ) : '',
			'last_name'  => isset( $_POST['auction_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['auction_last_name'] ) ) : '',
			'email'      => isset( $_POST['auction_email'] ) ? sanitize_email( wp_unslash( $_POST['auction_email'] ) ) : '',
		);

		return $this->get_registration_form_markup( $values, true );
	}

	/**
	 * Retrieve or set session cookie for anonymous bidders.
	 *
	 * @return string
	 */
	private function get_or_set_session_id(): string {
		if ( ! empty( $_COOKIE['auction_session'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['auction_session'] ) );
		}

		$session_id = Auction_Bid_Manager::generate_session_id();

		setcookie( 'auction_session', $session_id, time() + WEEK_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );

		return $session_id;
	}

	/**
	 * Retrieve user watchlist.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	private function get_watchlist_ids( int $user_id ): array {
		if ( ! $user_id ) {
			return array();
		}

		$list = get_user_meta( $user_id, 'auction_watchlist', true );

		if ( ! is_array( $list ) ) {
			return array();
		}

		return array_map( 'absint', $list );
	}

	/**
	 * Determine registration page URL.
	 *
	 * @return string
	 */
	private function get_registration_page_url(): string {
		$page_id = (int) get_option( 'auction_registration_page_id', 0 );

		if ( $page_id && get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}

		// Fallback: My Account page with register anchor.
		$myaccount = wc_get_page_permalink( 'myaccount' );

		return $myaccount ? add_query_arg( 'register', '1', $myaccount ) : home_url( '/' );
	}

	/**
	 * Format bidder name for display.
	 *
	 * @param array $record Bid record.
	 * @param array $config Auction config.
	 *
	 * @return string
	 */
	private function format_bidder_name( array $record, array $config ): string {
		if ( ! empty( $config['sealed'] ) && 'yes' === $config['sealed'] ) {
			return __( 'Hidden (sealed auction)', 'auction' );
		}

		if ( ! empty( $record['user_id'] ) ) {
			$user = get_user_by( 'id', absint( $record['user_id'] ) );
			if ( $user ) {
				$display_type = Auction_Settings::get( 'bid_username_display', 'masked' );
				$name         = $user->display_name ?: $user->user_login;

				if ( 'full' === $display_type ) {
					return $name;
				}

				return mb_substr( $name, 0, 1 ) . '****' . mb_substr( $name, -1 );
			}
		}

		if ( ! empty( $record['session_id'] ) ) {
			return __( 'Guest bidder', 'auction' );
		}

		return __( 'Unknown bidder', 'auction' );
	}

	/**
	 * Build registration form HTML.
	 *
	 * @param array $values           Prefilled values.
	 * @param bool  $include_notices  Whether to render notices.
	 *
	 * @return string
	 */
	private function get_registration_form_markup( array $values = array(), bool $include_notices = true ): string {
		$values = wp_parse_args(
			$values,
			array(
				'first_name' => '',
				'last_name'  => '',
				'email'      => '',
			)
		);

		ob_start();

		if ( $include_notices ) {
			wc_print_notices();
		}
		?>
		<form method="post" class="auction-registration-form">
			<?php wp_nonce_field( 'auction_register_user', 'auction_register_nonce' ); ?>
			<p class="form-row">
				<label for="auction_first_name"><?php esc_html_e( 'First name', 'auction' ); ?> <span class="required">*</span></label>
				<input type="text" id="auction_first_name" name="auction_first_name" value="<?php echo esc_attr( $values['first_name'] ); ?>" required />
			</p>
			<p class="form-row">
				<label for="auction_last_name"><?php esc_html_e( 'Last name', 'auction' ); ?> <span class="required">*</span></label>
				<input type="text" id="auction_last_name" name="auction_last_name" value="<?php echo esc_attr( $values['last_name'] ); ?>" required />
			</p>
			<p class="form-row">
				<label for="auction_email"><?php esc_html_e( 'Email address', 'auction' ); ?> <span class="required">*</span></label>
				<input type="email" id="auction_email" name="auction_email" value="<?php echo esc_attr( $values['email'] ); ?>" required />
			</p>
			<p class="form-row">
				<label for="auction_password"><?php esc_html_e( 'Password', 'auction' ); ?> <span class="required">*</span></label>
				<input type="password" id="auction_password" name="auction_password" required />
			</p>
			<p class="form-row">
				<label for="auction_confirm_password"><?php esc_html_e( 'Confirm password', 'auction' ); ?> <span class="required">*</span></label>
				<input type="password" id="auction_confirm_password" name="auction_confirm_password" required />
			</p>

			<p class="form-row">
				<button type="submit" class="button"><?php esc_html_e( 'Register', 'auction' ); ?></button>
			</p>
		</form>
		<?php

		return ob_get_clean();
	}
}

