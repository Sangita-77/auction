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
		add_action( 'init', array( $this, 'add_auction_page_rewrite_rule' ) );
		
		// Try to create menu item on every page load until it succeeds
		add_action( 'wp_loaded', array( $this, 'maybe_create_menu_item' ), 999 );
		add_action( 'init', array( $this, 'maybe_create_menu_item' ), 999 );

		$this->hooks();
	}

	/**
	 * Enforce auction filters on WooCommerce product queries.
	 *
	 * @param WP_Query $query Query instance.
	 *
	 * @return void
	 */
	public function enforce_auction_product_filters( $query ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		if ( is_admin() ) {
			return;
		}

		if ( ! $query instanceof WP_Query ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( $post_type && 'product' !== $post_type ) {
			return;
		}

		// Respect product_display_mode setting - don't enforce auction filter when 'all' is selected
		$display_mode = Auction_Settings::get( 'product_display_mode', 'all' );
		
		// When display mode is set, don't add any auction filter here
		// The maybe_filter_catalog_queries method handles all filtering based on display mode
		// This prevents conflicting filters that would restrict products incorrectly
		return;
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

		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

		// Add auctions page to navigation menu
		add_action( 'admin_head-nav-menus.php', array( $this, 'add_nav_menu_meta_box' ) );
		add_filter( 'wp_setup_nav_menu_item', array( $this, 'setup_nav_menu_item' ) );
		add_filter( 'wp_nav_menu_objects', array( $this, 'nav_menu_item_classes' ) );

		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_product_auction_panel' ), 25 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_loop_badge' ), 20 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'maybe_output_first_section_header' ), 25 );
		add_action( 'woocommerce_shop_loop', array( $this, 'maybe_output_section_header' ), 5 );

		add_action( 'pre_get_posts', array( $this, 'maybe_filter_catalog_queries' ) );
		add_action( 'woocommerce_product_query', array( $this, 'enforce_auction_product_filters' ) );
		add_filter( 'posts_where', array( $this, 'filter_buy_shop_products_where' ), 10, 2 );
		add_filter( 'the_posts', array( $this, 'organize_products_by_sections' ), 10, 2 );

		add_filter( 'woocommerce_is_purchasable', array( $this, 'maybe_disable_direct_purchase' ), 10, 2 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'filter_add_to_cart_text' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'filter_add_to_cart_text' ), 10, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'filter_loop_add_to_cart_link' ), 10, 2 );

		// Ensure that when an auction has a Buy Now price configured, that price is used
		// instead of the normal/discounted WooCommerce price.
		add_filter( 'woocommerce_product_get_price', array( $this, 'maybe_use_auction_buy_now_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'maybe_use_auction_buy_now_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'maybe_use_auction_buy_now_price' ), 99, 2 );
		add_filter( 'woocommerce_is_sold_individually', array( $this, 'disable_quantity_option' ), 10, 2 );
		add_filter( 'woocommerce_related_products', array( $this, 'filter_related_product_ids' ), 10, 3 );
		
		// Hide main price on auction page
		add_filter( 'woocommerce_get_price_html', array( $this, 'hide_price_on_auction_page' ), 10, 2 );

		add_action( 'wp_ajax_auction_place_bid', array( $this, 'ajax_place_bid' ) );
		add_action( 'wp_ajax_nopriv_auction_place_bid', array( $this, 'ajax_place_bid' ) );

		add_action( 'wp_ajax_auction_toggle_watchlist', array( $this, 'ajax_toggle_watchlist' ) );
		add_action( 'wp_ajax_nopriv_auction_toggle_watchlist', array( $this, 'ajax_toggle_watchlist' ) );

		add_shortcode( 'auction_watchlist', array( $this, 'render_watchlist_shortcode' ) );
		add_shortcode( 'auction_register_form', array( $this, 'render_registration_form_shortcode' ) );

		add_action( 'template_redirect', array( $this, 'restrict_ended_auction_access' ) );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'filter_ended_auction_visibility' ), 10, 2 );

		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_related_auctions' ), 20 );
	}

	/**
	 * Add rewrite rule for auction page.
	 *
	 * @return void
	 */
	public function add_auction_page_rewrite_rule(): void {
		$shop_page_id = wc_get_page_id( 'shop' );
		if ( ! $shop_page_id ) {
			return;
		}

		$shop_page = get_post( $shop_page_id );
		if ( ! $shop_page ) {
			return;
		}

		$shop_slug = $shop_page->post_name;
		if ( ! $shop_slug ) {
			$shop_slug = 'shop';
		}

		// Add rewrite rule for /shop/auctions/ or /auctions/
		add_rewrite_rule(
			'^auctions/?$',
			'index.php?post_type=product&auction_page=1',
			'top'
		);

		// Also add /shop/auctions/ if shop page exists
		add_rewrite_rule(
			'^' . $shop_slug . '/auctions/?$',
			'index.php?post_type=product&auction_page=1',
			'top'
		);
	}

	/**
	 * Maybe create menu item on init.
	 *
	 * @return void
	 */
	public function maybe_create_menu_item(): void {
		// Check if already created
		$created = get_option( 'auction_menu_item_created', false );
		if ( $created ) {
			return;
		}

		// Always try if not created yet (removed flag check to make it more aggressive)
		// Try to create
		require_once __DIR__ . '/../class-auction-install.php';
		$result = Auction_Install::create_auction_menu_item();
		
		// If successful, clear flags
		if ( ! is_wp_error( $result ) ) {
			delete_option( 'auction_should_create_menu' );
			delete_option( 'auction_force_create_menu' );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Auction: Menu creation failed: ' . $result->get_error_message() );
		}
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'auction_page';
		return $vars;
	}

	/**
	 * Add auctions page meta box to navigation menu admin.
	 *
	 * @return void
	 */
	public function add_nav_menu_meta_box(): void {
		add_meta_box(
			'auction-nav-menu',
			__( 'Auctions', 'auction' ),
			array( $this, 'nav_menu_meta_box' ),
			'nav-menus',
			'side',
			'low'
		);
	}

	/**
	 * Output the auctions page meta box in navigation menu admin.
	 *
	 * @return void
	 */
	public function nav_menu_meta_box(): void {
		global $_nav_menu_placeholder, $nav_menu_selected_id;

		$_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;

		$auction_url = $this->get_auction_page_url();
		?>
		<div id="auction-menu-items" class="posttypediv">
			<div id="tabs-panel-auction" class="tabs-panel tabs-panel-active">
				<ul id="auction-checklist" class="categorychecklist form-no-clear">
					<li>
						<label class="menu-item-title">
							<input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-object-id]" value="-1" />
							<?php esc_html_e( 'Auctions', 'auction' ); ?>
						</label>
						<input type="hidden" class="menu-item-type" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-type]" value="custom" />
						<input type="hidden" class="menu-item-title" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-title]" value="<?php echo esc_attr__( 'Auctions', 'auction' ); ?>" />
						<input type="hidden" class="menu-item-url" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-url]" value="<?php echo esc_url( $auction_url ); ?>" />
						<input type="hidden" class="menu-item-classes" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-classes]" value="auctions-menu-item" />
					</li>
				</ul>
			</div>
			<p class="button-controls" data-items-type="auction-menu-items">
				<span class="list-controls">
					<label>
						<input type="checkbox" class="select-all" />
						<?php esc_html_e( 'Select All', 'auction' ); ?>
					</label>
				</span>
				<span class="add-to-menu">
					<button type="submit" class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'auction' ); ?>" name="add-auction-menu-item" id="submit-auction-menu-items"><?php esc_html_e( 'Add to Menu', 'auction' ); ?></button>
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Get the auction page URL.
	 *
	 * @return string
	 */
	private function get_auction_page_url(): string {
		$shop_page_id = wc_get_page_id( 'shop' );
		if ( $shop_page_id ) {
			$shop_url = get_permalink( $shop_page_id );
			return add_query_arg( 'auction_page', '1', $shop_url );
		}

		// Fallback to /auctions/ if rewrite rules are set up
		return home_url( '/auctions/' );
	}

	/**
	 * Setup navigation menu item properties.
	 *
	 * @param object $menu_item Menu item object.
	 * @return object
	 */
	public function setup_nav_menu_item( $menu_item ) {
		if ( isset( $menu_item->url ) && strpos( $menu_item->url, 'auction_page=1' ) !== false ) {
			$menu_item->type_label = __( 'Auctions Page', 'auction' );
		} elseif ( isset( $menu_item->url ) && strpos( $menu_item->url, '/auctions' ) !== false ) {
			$menu_item->type_label = __( 'Auctions Page', 'auction' );
		}

		return $menu_item;
	}

	/**
	 * Add active classes to auction menu items.
	 *
	 * @param array $menu_items Menu items.
	 * @return array
	 */
	public function nav_menu_item_classes( array $menu_items ): array {
		// Check if we're on auction page
		$is_auction_page = false;
		if ( isset( $_GET['auction_page'] ) && '1' === $_GET['auction_page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$is_auction_page = true;
		}

		// Check query var from rewrite rule
		global $wp_query;
		if ( ! $is_auction_page && $wp_query && $wp_query->get( 'auction_page' ) === '1' ) {
			$is_auction_page = true;
		}

		if ( ! $is_auction_page ) {
			return $menu_items;
		}

		// Add active classes to auction menu items
		foreach ( $menu_items as $key => $menu_item ) {
			$classes = (array) $menu_item->classes;

			// Check if this menu item links to auctions
			if ( isset( $menu_item->url ) && ( strpos( $menu_item->url, 'auction_page=1' ) !== false || strpos( $menu_item->url, '/auctions' ) !== false ) ) {
				$menu_items[ $key ]->current = true;
				$classes[]                   = 'current-menu-item';
				$classes[]                   = 'current_page_item';
			}

			$menu_items[ $key ]->classes = array_unique( $classes );
		}

		return $menu_items;
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
				'register_form'  => $this->get_registration_form_markup( array(), false ),
				'currency'       => array(
					'symbol'             => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
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

		// Always allow viewing ended auctions (do not block with 404)
		return;
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

		// Always allow ended auctions to remain visible in frontend lists
		return true;
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
	
		// Get bid information
		$state = Auction_Product_Helper::get_runtime_state( $product );
		$current_bid = $state['winning_bid_id'] ? $state['current_bid'] : Auction_Product_Helper::get_start_price( $config );
		$current_bid = $current_bid > 0 ? $current_bid : 0;
		
		// Get total bid count
		$all_bids = Auction_Bid_Manager::get_bid_history( $product->get_id(), 1000, true );
		$total_bids = count( $all_bids );
		
		// Get high bidder name
		$leading_bid = Auction_Bid_Manager::get_leading_bid( $product->get_id() );
		$high_bidder_name = __( 'No bids yet', 'auction' );
		if ( $leading_bid ) {
			if ( 'yes' === ( $config['sealed'] ?? 'no' ) ) {
				$high_bidder_name = __( 'Hidden (sealed auction)', 'auction' );
			} elseif ( ! empty( $leading_bid['user_id'] ) ) {
				$user = get_user_by( 'id', absint( $leading_bid['user_id'] ) );
				if ( $user ) {
					$display_type = Auction_Settings::get( 'bid_username_display', 'masked' );
					$name = $user->display_name ?: $user->user_login;
					if ( 'full' === $display_type ) {
						$high_bidder_name = $name;
					} else {
						$high_bidder_name = mb_substr( $name, 0, 1 ) . '****' . mb_substr( $name, -1 );
					}
				}
			} elseif ( ! empty( $leading_bid['session_id'] ) ) {
				$high_bidder_name = __( 'Guest bidder', 'auction' );
			}
		}
		
		// Get min bid (start price)
		$min_bid = Auction_Product_Helper::get_start_price( $config );
		
		// Get bid increment
		$bid_increment = Auction_Product_Helper::get_manual_increment( $config );
		
		// Get product URL
		$product_url = get_permalink( $product->get_id() );
	
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
	
		echo '<div class="auction-loop-info">';
		
		// Auction end time
		if ( $end_timestamp ) {
			$start_attr = $start_timestamp ? ' data-countdown-start="' . esc_attr( $start_timestamp ) . '"' : '';
			echo '<div class="auction-info-row">';
			echo '<strong>' . esc_html__( 'Auction Ends:', 'auction' ) . '</strong> ';
			echo '<span class="auction-end-time">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $end_timestamp ) ) . '</span>';
			if ( Auction_Settings::is_enabled( 'show_countdown_loop' ) ) {
				echo ' <span class="auction-countdown" data-countdown-target="' . esc_attr( $end_timestamp ) . '"' . $start_attr . '></span>';
			}
			echo '</div>';
		}
		
		// Current Bid with total bid count
		echo '<div class="auction-info-row">';
		echo '<strong>' . esc_html__( 'Current Bid:', 'auction' ) . '</strong> ';
		echo '<span class="auction-current-bid">' . wp_kses_post( wc_price( $current_bid ) ) . '</span>';
		echo ' <span class="auction-bid-count">(' . esc_html__( 'bids:', 'auction' ) . ' ' . esc_html( $total_bids ) . ')</span>';
		echo '</div>';
		
		// High Bidder
		echo '<div class="auction-info-row">';
		echo '<strong>' . esc_html__( 'High Bidder:', 'auction' ) . '</strong> ';
		echo '<span class="auction-high-bidder">' . esc_html( $high_bidder_name ) . '</span>';
		echo '</div>';
		
		// Min Bid
		echo '<div class="auction-info-row">';
		echo '<strong>' . esc_html__( 'Min Bid:', 'auction' ) . '</strong> ';
		echo '<span class="auction-min-bid">' . wp_kses_post( wc_price( $min_bid ) ) . '</span>';
		echo '</div>';
		
		// Bid Increment
		echo '<div class="auction-info-row">';
		echo '<strong>' . esc_html__( 'Bid Increment:', 'auction' ) . '</strong> ';
		echo '<span class="auction-bid-increment">' . wp_kses_post( wc_price( $bid_increment ) ) . '</span>';
		echo '</div>';
		
		// Lot Details button
		echo '<div class="auction-info-row auction-lot-details">';
		echo '<a href="' . esc_url( $product_url ) . '" class="button auction-lot-details-btn">' . esc_html__( 'Lot Details', 'auction' ) . '</a>';
		echo '</div>';
		
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
		$display_mode      = Auction_Settings::get( 'product_display_mode', 'all' );

		if ( $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) ) {
			$meta_query = (array) $query->get( 'meta_query', array() );

			// Check if we're on the auction page (via query parameter or query var)
			$is_auction_page = false;
			
			// Check query var first (set via URL parameter ?auction_page=1)
			$auction_page_var = $query->get( 'auction_page' );
			if ( $auction_page_var && '1' === $auction_page_var ) {
				$is_auction_page = true;
			}
			
			// Also check $_GET for compatibility
			if ( ! $is_auction_page && isset( $_GET['auction_page'] ) && '1' === $_GET['auction_page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$is_auction_page = true;
			}
			
			// Also check if we're on a page with slug 'auctions'
			if ( ! $is_auction_page && $query->is_page() ) {
				$queried_object = get_queried_object();
				if ( $queried_object && isset( $queried_object->post_name ) && 'auctions' === $queried_object->post_name ) {
					$is_auction_page = true;
				}
			}

			// Determine which filtering to use: new shop/auction page system or legacy display_mode
			if ( $is_auction_page ) {
				// Auction page: Show ALL auction products (regardless of buy now setting)
				// This ensures products with both buy and bid options appear on auction page
				$meta_query[] = array(
					'key'   => '_auction_enabled',
					'value' => 'yes',
				);
			} else {
				// Legacy system: Use display_mode setting
				if ( 'all' !== $display_mode ) {
					switch ( $display_mode ) {
						case 'buy_only':
							// Only show products without auction enabled
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
							break;

						case 'auction_only':
							// Only show auction products
							$meta_query[] = array(
								'key'   => '_auction_enabled',
								'value' => 'yes',
							);
							break;

						case 'auction_with_buy_now':
							// Only show auction products with buy now enabled
							$meta_query[] = array(
								'relation' => 'AND',
								array(
									'key'   => '_auction_enabled',
									'value' => 'yes',
								),
								array(
									'key'   => '_auction_buy_now_enabled',
									'value' => 'yes',
								),
							);
							break;

						case 'auction_without_buy_now':
							// Only show auction products without buy now enabled
							$meta_query[] = array(
								'relation' => 'AND',
								array(
									'key'   => '_auction_enabled',
									'value' => 'yes',
								),
								array(
									'relation' => 'OR',
									array(
										'key'     => '_auction_buy_now_enabled',
										'compare' => 'NOT EXISTS',
									),
									array(
										'key'     => '_auction_buy_now_enabled',
										'value'   => 'yes',
										'compare' => '!=',
									),
								),
							);
							break;
					}
				} elseif ( ! $show_shop && $query->is_post_type_archive( 'product' ) ) {
					// Legacy behavior: respect show_on_shop setting
					// Hide auction products when show_on_shop is disabled
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
				// When show_on_shop is enabled and display_mode is 'all', show all products (no filter needed)
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
	 * Filter buy shop products using WHERE clause (more efficient than complex meta_query).
	 *
	 * @param string   $where The WHERE clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 * @return string
	 */
	public function filter_buy_shop_products_where( string $where, WP_Query $query ): string {
		if ( is_admin() || ! $query->is_main_query() ) {
			return $where;
		}

		// Only apply on shop page, not category pages
		if ( ! ( $query->is_post_type_archive( 'product' ) && ! $query->is_tax() ) ) {
			return $where;
		}

		// Check if we're on auction page - if so, don't filter
		$is_auction_page = false;
		
		// Check query var (from rewrite rule)
		$auction_page_var = $query->get( 'auction_page' );
		if ( $auction_page_var && '1' === $auction_page_var ) {
			$is_auction_page = true;
		}
		
		// Check $_GET for compatibility
		if ( ! $is_auction_page && isset( $_GET['auction_page'] ) && '1' === $_GET['auction_page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$is_auction_page = true;
		}

		// Only filter on regular shop page when display_mode is 'all'
		$display_mode = Auction_Settings::get( 'product_display_mode', 'all' );
		if ( $is_auction_page || 'all' !== $display_mode ) {
			return $where;
		}

		global $wpdb;

		// Filter: Show products WITHOUT auction OR products WITH auction + buy now enabled
		// This SQL ensures products with both buy and bid options appear on buy shop page
		$where .= " AND (
			NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm1 
				WHERE pm1.post_id = {$wpdb->posts}.ID 
				AND pm1.meta_key = '_auction_enabled' 
				AND pm1.meta_value = 'yes'
			)
			OR EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm2 
				INNER JOIN {$wpdb->postmeta} pm3 ON pm2.post_id = pm3.post_id
				WHERE pm2.post_id = {$wpdb->posts}.ID 
				AND pm2.meta_key = '_auction_enabled' 
				AND pm2.meta_value = 'yes'
				AND pm3.meta_key = '_auction_buy_now_enabled' 
				AND pm3.meta_value = 'yes'
			)
		)";

		return $where;
	}

	/**
	 * Ensure meta query requires auction-enabled flag.
	 *
	 * @param array $meta_query Meta query array.
	 *
	 * @return array
	 */
	private function ensure_auction_enabled_meta_query( array $meta_query ): array {
		if ( ! isset( $meta_query['relation'] ) ) {
			$meta_query['relation'] = 'AND';
		}

		$has_clause = false;

		foreach ( $meta_query as $key => $clause ) {
			if ( 'relation' === $key || ! is_array( $clause ) ) {
				continue;
			}

			if (
				isset( $clause['key'], $clause['value'] )
				&& '_auction_enabled' === $clause['key']
				&& 'yes' === $clause['value']
				&& ( $clause['compare'] ?? '=' ) === '='
			) {
				$has_clause = true;
				break;
			}
		}

		if ( ! $has_clause ) {
			$meta_query[] = array(
				'key'   => '_auction_enabled',
				'value' => 'yes',
			);
		}

		return $meta_query;
	}

	/**
	 * Prevent direct purchase when buy now is disabled.
	 *
	 * @param bool       $purchasable Whether product is purchasable.
	 * @param WC_Product $product     Product instance.
	 *
	 * @return bool
	 */
	public function maybe_disable_direct_purchase( bool $purchasable, WC_Product $product ): bool {
		if ( ! $product instanceof WC_Product ) {
			return $purchasable;
		}

		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return $purchasable;
		}

		$config = Auction_Product_Helper::get_config( $product );

		// If Buy Now is not enabled, block direct purchase.
		if ( ! $config['buy_now_enabled'] ) {
			return false;
		}

		// Otherwise allow WooCommerce to treat it as purchasable (so Buy Now works).
		return $purchasable;
	}

	/**
	 * Rename add to cart text for auction products.
	 *
	 * @param string     $text    Default text.
	 * @param WC_Product $product Product instance.
	 *
	 * @return string
	 */
	public function filter_add_to_cart_text( string $text, WC_Product $product ): string {
		if ( ! $product instanceof WC_Product ) {
			return $text;
		}

		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return $text;
		}

		$config = Auction_Product_Helper::get_config( $product );

		return $config['buy_now_enabled']
			? __( 'Buy Now', 'auction' )
			: '';
	}

	/**
	 * Remove loop add to cart link when buy now is disabled.
	 *
	 * @param string     $html    Button HTML.
	 * @param WC_Product $product Product instance.
	 *
	 * @return string
	 */
	public function filter_loop_add_to_cart_link( string $html, WC_Product $product ): string {
		if ( ! $product instanceof WC_Product ) {
			return $html;
		}

		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return $html;
		}

		$config = Auction_Product_Helper::get_config( $product );

		// When Buy Now is disabled, remove the loop add-to-cart link.
		if ( ! $config['buy_now_enabled'] ) {
			return '';
		}

		// When Buy Now is enabled, keep the default WooCommerce link (it will use our Buy Now text/price).
		return $html;
	}

	/**
	 * Use the auction's Buy Now price as the product price when Buy Now is enabled.
	 *
	 * This ensures that if the admin has configured an _auction_buy_now_price,
	 * that value is what customers see and pay when clicking the Buy Now button,
	 * regardless of any regular/sale price set on the product.
	 *
	 * @param float      $price   The current price.
	 * @param WC_Product $product The product object.
	 *
	 * @return float
	 */
	public function maybe_use_auction_buy_now_price( $price, WC_Product $product ) {
		if ( ! $product instanceof WC_Product ) {
			return $price;
		}

		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return $price;
		}

		$config = Auction_Product_Helper::get_config( $product );

		// Only override when Buy Now is explicitly enabled and a positive price is set.
		if ( ! empty( $config['buy_now_enabled'] ) ) {
			$buy_now_price = Auction_Product_Helper::to_float( $config['buy_now_price'] ?? 0 );

			if ( $buy_now_price > 0 ) {
				return $buy_now_price;
			}
		}

		return $price;
	}

	/**
	 * Disable quantity selection on product page for auction products.
	 *
	 * @param bool       $sold_individually Existing flag.
	 * @param WC_Product $product           Product instance.
	 *
	 * @return bool
	 */
	public function disable_quantity_option( bool $sold_individually, WC_Product $product ): bool {
		if ( Auction_Product_Helper::is_auction_product( $product ) ) {
			return true;
		}

		return $sold_individually;
	}

	/**
	 * Hide main price on auction page for auction products.
	 *
	 * @param string     $price_html Price HTML.
	 * @param WC_Product $product    Product instance.
	 *
	 * @return string
	 */
	public function hide_price_on_auction_page( string $price_html, WC_Product $product ): string {
		// Only hide on shop/archive pages (not single product pages)
		if ( is_product() ) {
			return $price_html;
		}

		// Check if we're on the auction page
		$is_auction_page = false;
		if ( isset( $_GET['auction_page'] ) && '1' === $_GET['auction_page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$is_auction_page = true;
		}

		// Check query var from rewrite rule
		global $wp_query;
		if ( ! $is_auction_page && $wp_query && $wp_query->get( 'auction_page' ) === '1' ) {
			$is_auction_page = true;
		}

		// Only hide price for auction products on auction page
		if ( $is_auction_page && Auction_Product_Helper::is_auction_product( $product ) ) {
			return '';
		}

		return $price_html;
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

	/**
	 * Show related auction products only.
	 *
	 * @return void
	 */
	public function render_related_auctions(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return;
		}

		$related_ids = array_diff( wc_get_related_products( $product->get_id(), 12 ), array( $product->get_id() ) );

		$related_ids = array_filter(
			$related_ids,
			static function ( $related_id ) {
				$related_product = wc_get_product( $related_id );

				return $related_product && Auction_Product_Helper::is_auction_product( $related_product );
			}
		);

		$query = $this->build_related_auction_query(
			array(
				'post__in' => $related_ids,
			),
			$product
		);

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return;
		}

		echo '<section class="related related-auctions">';
		echo '<h2>' . esc_html__( 'Related Auctions', 'auction' ) . '</h2>';
		woocommerce_product_loop_start();

		while ( $query->have_posts() ) {
			$query->the_post();
			wc_get_template_part( 'content', 'product' );
		}

		woocommerce_product_loop_end();
		echo '</section>';

		wp_reset_postdata();
	}

	/**
	 * Ensure WooCommerce related product IDs include auctions only.
	 *
	 * @param array $related_ids Array of IDs.
	 * @param int   $product_id  Current product ID.
	 * @param array $args        Query arguments.
	 *
	 * @return array
	 */
	public function filter_related_product_ids( array $related_ids, int $product_id, array $args ): array {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return $related_ids;
		}

		return array_values(
			array_filter(
				$related_ids,
				static function ( $related_id ) {
					$related_product = wc_get_product( $related_id );

					return $related_product && Auction_Product_Helper::is_auction_product( $related_product );
				}
			)
		);
	}

	/**
	 * Build query for related auction items.
	 *
	 * @param array      $args    Base args.
	 * @param WC_Product $product Current product.
	 *
	 * @return WP_Query
	 */
	private function build_related_auction_query( array $args, WC_Product $product ): WP_Query {
		$defaults = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'meta_query'     => array(
				array(
					'key'   => '_auction_enabled',
					'value' => 'yes',
				),
			),
		);

		if ( empty( $args['post__in'] ) ) {
			$defaults['post__not_in'] = array( $product->get_id() );
			$defaults['orderby']      = 'rand';

			$categories = wp_list_pluck( get_the_terms( $product->get_id(), 'product_cat' ) ?: array(), 'term_id' );

			if ( $categories ) {
				$defaults['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $categories,
					),
				);
			}
		} else {
			$defaults['post__in'] = $args['post__in'];
			$defaults['orderby']  = 'post__in';
		}

		$query_args = wp_parse_args( $args, $defaults );

		if ( isset( $query_args['post__in'] ) && empty( $query_args['post__in'] ) ) {
			unset( $query_args['post__in'] );
			$query_args['post__not_in'] = array( $product->get_id() );
			$query_args['orderby']      = 'rand';

			$categories = wp_list_pluck( get_the_terms( $product->get_id(), 'product_cat' ) ?: array(), 'term_id' );

			if ( $categories ) {
				$query_args['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $categories,
					),
				);
			}
		}

		return new WP_Query( $query_args );
	}

	/**
	 * Organize products into sections when display mode is 'all'.
	 *
	 * @param array    $posts Array of post objects.
	 * @param WP_Query $query Query instance.
	 *
	 * @return array
	 */
	public function organize_products_by_sections( array $posts, WP_Query $query ): array {
		// Only apply on shop pages
		if ( is_admin() || ! $query->is_main_query() ) {
			return $posts;
		}

		if ( ! ( $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) ) ) {
			return $posts;
		}

		$display_mode = Auction_Settings::get( 'product_display_mode', 'all' );

		// Only organize when display mode is 'all'
		if ( 'all' !== $display_mode ) {
			return $posts;
		}

		// Check if user has applied custom sorting (not default menu_order)
		$orderby = $query->get( 'orderby' );
		$orderby = $orderby ?: ( isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		// If custom sorting is applied (not default), preserve the sort order and don't reorganize
		// This ensures auction products appear in correct order when sorted by date, price, etc.
		if ( $orderby && ! in_array( $orderby, array( 'menu_order', 'title', '' ), true ) ) {
			// Custom sort is active - don't reorganize, just preserve original order
			return $posts;
		}

		// Organize products into sections (only when using default sorting)
		$products_without_auction = array();
		$products_auction_only     = array();
		$products_auction_buy_now = array();

		foreach ( $posts as $post ) {
			$product = wc_get_product( $post->ID );

			if ( ! $product ) {
				continue;
			}

			$is_auction = Auction_Product_Helper::is_auction_product( $product );

			if ( ! $is_auction ) {
				// Product without auction
				$products_without_auction[] = $post;
			} else {
				// Check if it has buy now enabled
				$config = Auction_Product_Helper::get_config( $product );
				if ( ! empty( $config['buy_now_enabled'] ) && 'yes' === $config['buy_now_enabled'] ) {
					// Auction product with buy now
					$products_auction_buy_now[] = $post;
				} else {
					// Auction product without buy now
					$products_auction_only[] = $post;
				}
			}
		}

		// Combine products in order: without auction, auction only, auction with buy now
		$organized_posts = array_merge(
			$products_without_auction,
			$products_auction_only,
			$products_auction_buy_now
		);

		// Store section information for display
		$section_info = array(
			'without_auction_start' => 0,
			'without_auction_count'  => count( $products_without_auction ),
			'auction_only_start'     => count( $products_without_auction ),
			'auction_only_count'     => count( $products_auction_only ),
			'auction_buy_now_start'  => count( $products_without_auction ) + count( $products_auction_only ),
			'auction_buy_now_count'  => count( $products_auction_buy_now ),
		);

		// Store in query for later use
		$query->set( 'auction_section_info', $section_info );

		return $organized_posts;
	}

	/**
	 * Output first section header before shop loop.
	 *
	 * @return void
	 */
	public function maybe_output_first_section_header(): void {
		global $wp_query;

		if ( ! $wp_query || ! $wp_query->is_main_query() ) {
			return;
		}

		$display_mode = Auction_Settings::get( 'product_display_mode', 'all' );

		if ( 'all' !== $display_mode ) {
			return;
		}

		// Try to get section_info from query
		$section_info = $wp_query->get( 'auction_section_info' );

		// If not available yet, try to get it from the organized posts
		if ( ! $section_info && $wp_query->posts ) {
			// Reorganize to get section info
			$section_info = $this->calculate_section_info( $wp_query->posts );
		}

		if ( ! $section_info ) {
			return;
		}

		// Output header for first section (products without auction) if it exists
		// if ( isset( $section_info['without_auction_count'] ) && $section_info['without_auction_count'] > 0 ) {
		// 	echo '<h2 class="auction-section-title" style="margin: 30px 0 20px; padding: 15px 0; font-size: 24px; font-weight: bold; border-bottom: 2px solid #e0e0e0; clear: both;">' . esc_html__( 'All Products', 'auction' ) . '</h2>';
		// }
	}

	/**
	 * Calculate section info from posts array.
	 *
	 * @param array $posts Array of post objects.
	 * @return array|null
	 */
	private function calculate_section_info( array $posts ): ?array {
		$products_without_auction = array();
		$products_auction_only     = array();
		$products_auction_buy_now = array();

		foreach ( $posts as $post ) {
			$post_id = is_object( $post ) ? $post->ID : ( is_array( $post ) ? ( $post['ID'] ?? $post['id'] ?? 0 ) : $post );
			$product = wc_get_product( $post_id );

			if ( ! $product ) {
				continue;
			}

			$is_auction = Auction_Product_Helper::is_auction_product( $product );

			if ( ! $is_auction ) {
				$products_without_auction[] = $post;
			} else {
				$config = Auction_Product_Helper::get_config( $product );
				if ( ! empty( $config['buy_now_enabled'] ) && 'yes' === $config['buy_now_enabled'] ) {
					$products_auction_buy_now[] = $post;
				} else {
					$products_auction_only[] = $post;
				}
			}
		}

		return array(
			'without_auction_start' => 0,
			'without_auction_count'  => count( $products_without_auction ),
			'auction_only_start'     => count( $products_without_auction ),
			'auction_only_count'     => count( $products_auction_only ),
			'auction_buy_now_start'  => count( $products_without_auction ) + count( $products_auction_only ),
			'auction_buy_now_count'  => count( $products_auction_buy_now ),
		);
	}

	/**
	 * Output section header before product if needed.
	 * This runs in woocommerce_shop_loop hook, before the product template is rendered.
	 *
	 * @return void
	 */
	public function maybe_output_section_header(): void {
		global $wp_query, $post;

		if ( ! $wp_query || ! $wp_query->is_main_query() ) {
			return;
		}

		$display_mode = Auction_Settings::get( 'product_display_mode', 'all' );

		if ( 'all' !== $display_mode ) {
			return;
		}

		if ( ! $post ) {
			return;
		}

		// Get the current product
		$product = wc_get_product( $post->ID ?? $post );

		if ( ! $product ) {
			return;
		}

		// Determine product type directly
		$is_auction = Auction_Product_Helper::is_auction_product( $product );
		$has_buy_now = false;

		if ( $is_auction ) {
			// Check buy now enabled directly from meta
			$buy_now_meta = $product->get_meta( '_auction_buy_now_enabled', true );
			$has_buy_now = 'yes' === $buy_now_meta;
		}

		// Determine current product category
		$current_category = 'regular';
		if ( $is_auction ) {
			$current_category = $has_buy_now ? 'auction_buy_now' : 'auction_only';
		}

		// Use a static array to track previous product category and which headers we've shown
		static $section_data = array();

		// Reset on new query
		$query_hash = $wp_query->query_vars_hash ?? md5( serialize( $wp_query->query_vars ) );
		if ( ! isset( $section_data[ $query_hash ] ) ) {
			$section_data[ $query_hash ] = array(
				'previous_category' => null,
				'headers_shown'      => array(
					'without_auction' => false,
					'auction_only'    => false,
					'auction_buy_now' => false,
				),
			);
		}

		$data = &$section_data[ $query_hash ];

		// Check if we need to show a header
		$should_show_header = false;
		$header_type = '';

		// If this is the first product and it's not regular, show header
		// If category changed from regular to auction, show header
		// If category changed from auction_only to auction_buy_now, show header
		if ( null === $data['previous_category'] ) {
			// First product in loop - if it's auction, show header (regular header is shown in maybe_output_first_section_header)
			if ( 'auction_only' === $current_category && ! $data['headers_shown']['auction_only'] ) {
				$should_show_header = true;
				$header_type = 'auction_only';
			} elseif ( 'auction_buy_now' === $current_category && ! $data['headers_shown']['auction_buy_now'] ) {
				$should_show_header = true;
				$header_type = 'auction_buy_now';
			}
		} elseif ( $data['previous_category'] !== $current_category ) {
			// Category changed - show header for auction categories only
			if ( 'auction_only' === $current_category && ! $data['headers_shown']['auction_only'] ) {
				$should_show_header = true;
				$header_type = 'auction_only';
			} elseif ( 'auction_buy_now' === $current_category && ! $data['headers_shown']['auction_buy_now'] ) {
				$should_show_header = true;
				$header_type = 'auction_buy_now';
			}
		}

		// Output the header if needed - close current list, show header, open new list
		if ( $should_show_header ) {
			echo '</ul>';
			if ( 'auction_only' === $header_type ) {
				echo '<h2 class="auction-section-title" style="margin: 30px 0 20px; padding: 15px 0; font-size: 24px; font-weight: bold; border-bottom: 2px solid #e0e0e0; clear: both;">' . esc_html__( 'Auction Products', 'auction' ) . '</h2>';
				$data['headers_shown']['auction_only'] = true;
			} elseif ( 'auction_buy_now' === $header_type ) {
				echo '<h2 class="auction-section-title" style="margin: 30px 0 20px; padding: 15px 0; font-size: 24px; font-weight: bold; border-bottom: 2px solid #e0e0e0; clear: both;">' . esc_html__( 'Auction Products with Buy Now', 'auction' ) . '</h2>';
				$data['headers_shown']['auction_buy_now'] = true;
			}
			echo '<ul class="products columns-' . esc_attr( wc_get_loop_prop( 'columns' ) ) . '">';
		}

		// Update previous category for next iteration
		$data['previous_category'] = $current_category;
	}
}

