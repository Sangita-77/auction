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
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_bid_history_table' ), 10 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_loop_badge' ), 20 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'maybe_output_first_section_header' ), 25 );
		add_action( 'woocommerce_shop_loop', array( $this, 'maybe_output_section_header' ), 5 );

		// Auction listing page layout hooks
		add_action( 'woocommerce_before_shop_loop', array( $this, 'render_auction_listing_header' ), 5 );
		add_action( 'woocommerce_after_shop_loop', array( $this, 'render_auction_listing_footer' ), 25 );
		add_filter( 'woocommerce_product_loop_start', array( $this, 'wrap_auction_products_start' ), 10, 1 );
		add_filter( 'woocommerce_product_loop_end', array( $this, 'wrap_auction_products_end' ), 10, 1 );

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
		add_shortcode( 'auction_listing', array( $this, 'render_auction_listing_shortcode' ) );

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
	 * Render bid history table below buy button.
	 *
	 * @return void
	 */
	public function render_bid_history_table(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
			return;
		}

		$config = Auction_Product_Helper::get_config( $product );
		$bid_history = Auction_Bid_Manager::get_bid_history( $product->get_id(), 100, true );
		
		// Format bid history
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

		?>
		<div class="auction-bid-history" style="margin-top: 30px; clear: both;">
			<h3 style="margin-bottom: 15px;"><?php esc_html_e( 'Bid History', 'auction' ); ?></h3>
			<?php if ( $config['sealed'] && 'active' === Auction_Product_Helper::get_auction_status( $config ) ) : ?>
				<p><?php esc_html_e( 'This is a sealed auction. Bid details will remain hidden until the auction ends.', 'auction' ); ?></p>
			<?php else : ?>
				<?php if ( ! empty( $bid_history ) ) : ?>
					<table class="auction-bid-history__table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
						<thead>
							<tr style="background-color: #f5f5f5;">
								<th style="padding: 12px; text-align: left; border: 1px solid #ddd;"><?php esc_html_e( 'Bidder', 'auction' ); ?></th>
								<th style="padding: 12px; text-align: left; border: 1px solid #ddd;"><?php esc_html_e( 'Bid Amount', 'auction' ); ?></th>
								<th style="padding: 12px; text-align: left; border: 1px solid #ddd;"><?php esc_html_e( 'Bid Time', 'auction' ); ?></th>
								<th style="padding: 12px; text-align: left; border: 1px solid #ddd;"><?php esc_html_e( 'Status', 'auction' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $bid_history as $entry ) : ?>
								<tr style="border-bottom: 1px solid #ddd;">
									<td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html( $entry['name'] ); ?></td>
									<td style="padding: 10px; border: 1px solid #ddd;"><?php echo wp_kses_post( wc_price( $entry['amount'] ) ); ?></td>
									<td style="padding: 10px; border: 1px solid #ddd;">
										<?php
										if ( ! empty( $entry['time'] ) ) {
											echo esc_html(
												wp_date(
													get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
													strtotime( $entry['time'] )
												)
											);
										} else {
											esc_html_e( 'N/A', 'auction' );
										}
										?>
									</td>
									<td style="padding: 10px; border: 1px solid #ddd;">
										<?php
										$status_label = '';
										switch ( $entry['status'] ?? '' ) {
											case 'active':
												$status_label = __( 'Active', 'auction' );
												break;
											case 'outbid':
												$status_label = __( 'Outbid', 'auction' );
												break;
											default:
												$status_label = __( 'N/A', 'auction' );
										}
										echo esc_html( $status_label );
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No bids have been placed yet. Be the first to bid!', 'auction' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
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
	
		// Status display (Active/Closed)
		$status_label = '';
		$status_class = '';
		switch ( $status ) {
			case 'active':
				$status_label = __( 'Active', 'auction' );
				$status_class = 'auction-status-active';
				break;
			case 'ended':
				$status_label = __( 'Closed', 'auction' );
				$status_class = 'auction-status-closed';
				break;
			case 'scheduled':
				$status_label = __( 'Scheduled', 'auction' );
				$status_class = 'auction-status-scheduled';
				break;
		}
		
		if ( $status_label ) {
			echo '<div class="auction-status-badge ' . esc_attr( $status_class ) . '">';
			// echo '<span class="auction-status-label">' . esc_html( $status_label ) . '</span>';
			echo '</div>';
		}
	
		echo '<div class="auction-loop-info">';
		
		// High Bidder
		echo '<div class="auction-info-row">';
		echo '<strong>' . esc_html__( 'High Bidder:', 'auction' ) . '</strong> ';
		echo '<span class="auction-high-bidder">' . esc_html( $high_bidder_name ) . '</span>';
		echo '</div>';
		
		// Current Bid with total bid count
		echo '<div class="auction-info-row">';
		echo '<strong>' . esc_html__( 'Current Bid:', 'auction' ) . '(' . esc_html__( 'bids:', 'auction' ) . ' ' . esc_html( $total_bids ) . ')</strong> ';
		echo '<span class="auction-current-bid">' . wp_kses_post( wc_price( $current_bid ) ) . '</span>';
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
		
		// Auction end time
		if ( $end_timestamp ) {
			$start_attr = $start_timestamp ? ' data-countdown-start="' . esc_attr( $start_timestamp ) . '"' : '';
			echo '<div class="auction-info-row d-none">';
			echo '<strong>' . esc_html__( 'Auction Ends:', 'auction' ) . '</strong> ';
			echo '<span class="auction-end-time">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $end_timestamp ) ) . '</span>';
			if ( Auction_Settings::is_enabled( 'show_countdown_loop' ) ) {
				echo ' <span class="auction-countdown" data-countdown-target="' . esc_attr( $end_timestamp ) . '"' . $start_attr . '></span>';
			}
			echo '</div>';
		}
		
		// Time Remaining / Status
		echo '<div class="auction-info-row">';
		echo '<strong>' . esc_html__( 'Time Remaining:', 'auction' ) . '</strong> ';
		$status_label = '';
		$status_class = '';
		switch ( $status ) {
			case 'active':
				$status_label = __( 'Active', 'auction' );
				$status_class = 'auction-status-active';
				break;
			case 'ended':
				$status_label = __( 'Closed', 'auction' );
				$status_class = 'auction-status-closed';
				break;
			case 'scheduled':
				$status_label = __( 'Scheduled', 'auction' );
				$status_class = 'auction-status-scheduled';
				break;
		}
		echo '<span class="auction-time-remaining ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
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

			// Check if we're on the shop page (not auction page, not category/taxonomy pages)
			$is_shop_page = $query->is_post_type_archive( 'product' ) && ! $query->is_tax() && ! $is_auction_page;

			// Hide ended auctions if the setting is enabled, but show them on shop page and auction page
			if ( $hide_ended && ! $is_shop_page && ! $is_auction_page ) {
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
		$current_time = current_time( 'mysql' );

		// Filter: Show products WITHOUT auction OR products WITH auction + buy now enabled OR closed auctions
		// This SQL ensures products with both buy and bid options appear on buy shop page
		// Also includes closed auctions so they are visible on shop page
		$closed_auction_sql = $wpdb->prepare(
			"EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm4 
				WHERE pm4.post_id = {$wpdb->posts}.ID 
				AND pm4.meta_key = '_auction_enabled' 
				AND pm4.meta_value = 'yes'
				AND EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm5 
					WHERE pm5.post_id = {$wpdb->posts}.ID 
					AND pm5.meta_key = '_auction_end_time' 
					AND pm5.meta_value < %s
				)
			)",
			$current_time
		);

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
			OR " . $closed_auction_sql . "
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
	 * Prevent direct purchase when buy now is disabled or auction is ended.
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

		// Check if auction is ended - disable Buy Now for closed auctions
		$auction_status = Auction_Product_Helper::get_auction_status( $config );
		if ( 'ended' === $auction_status ) {
			return false;
		}

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

		// Check if auction is ended - hide Buy Now text for closed auctions
		$auction_status = Auction_Product_Helper::get_auction_status( $config );
		if ( 'ended' === $auction_status ) {
			return '';
		}

		return $config['buy_now_enabled']
			? __( 'Buy Now', 'auction' )
			: '';
	}

	/**
	 * Remove loop add to cart link when buy now is disabled or auction is ended.
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

		// Check if auction is ended - remove Buy Now button for closed auctions
		$auction_status = Auction_Product_Helper::get_auction_status( $config );
		if ( 'ended' === $auction_status ) {
			return '';
		}

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

		$product = wc_get_product( $product_id );
		if ( ! $product || ! Auction_Product_Helper::is_auction_product( $product ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid auction product.', 'auction' ),
				)
			);
		}

		// Check if auction is ended - prevent bids on closed auctions
		$config = Auction_Product_Helper::get_config( $product );
		$auction_status = Auction_Product_Helper::get_auction_status( $config );
		if ( 'ended' === $auction_status ) {
			wp_send_json_error(
				array(
					'message' => __( 'This auction has ended. Bidding is no longer available.', 'auction' ),
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
	 * Render main auction listing layout (tabs, filters, search, sorting).
	 *
	 * Usage in a page: [auction_listing]
	 *
	 * @return string
	 */
	public function render_auction_listing_shortcode(): string {
		if ( ! class_exists( 'WC_Product' ) ) {
			return '';
		}

		// Load all auction products.
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_auction_enabled',
						'value' => 'yes',
					),
				),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( ! $query->have_posts() ) {
			return '<p>' . esc_html__( 'No auctions are available at the moment.', 'auction' ) . '</p>';
		}

		$now            = current_time( 'timestamp' );
		$today_date_key = gmdate( 'Y-m-d', $now );
		$todays_count   = 0;
		$scheduled_map  = array(); // date_key => count.
		$products_data  = array();
		$categories_map = array(); // term_id => array( 'term' => term, 'count' => n ).

		while ( $query->have_posts() ) {
			$query->the_post();
			$product = wc_get_product( get_the_ID() );

			if ( ! $product ) {
				continue;
			}

			$config = Auction_Product_Helper::get_config( $product );
			$status = Auction_Product_Helper::get_auction_status( $config );
			$state  = Auction_Product_Helper::get_runtime_state( $product );

			$current_bid = $state['winning_bid_id'] ? $state['current_bid'] : Auction_Product_Helper::get_start_price( $config );
			$current_bid = $current_bid > 0 ? $current_bid : 0;

			// Bid count.
			$all_bids  = Auction_Bid_Manager::get_bid_history( $product->get_id(), 1000, true );
			$bid_count = count( $all_bids );

			$start_ts   = $config['start_timestamp'] ?: 0;
			$end_ts     = $config['end_timestamp'] ?: 0;
			$lot_number = $product->get_id();

			// Today live auction count: active starting today.
			if ( 'active' === $status && $start_ts ) {
				$start_day = gmdate( 'Y-m-d', $start_ts );
				if ( $start_day === $today_date_key ) {
					$todays_count++;
				}
			}

			// Next auction date map (scheduled).
			if ( 'scheduled' === $status && $start_ts ) {
				$date_key = gmdate( 'Y-m-d', $start_ts );
				if ( ! isset( $scheduled_map[ $date_key ] ) ) {
					$scheduled_map[ $date_key ] = 0;
				}
				$scheduled_map[ $date_key ]++;
			}

			// Categories for this product.
			$terms     = get_the_terms( $product->get_id(), 'product_cat' );
			$term_slugs = array();
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_slugs[] = $term->slug;
					if ( ! isset( $categories_map[ $term->term_id ] ) ) {
						$categories_map[ $term->term_id ] = array(
							'term'  => $term,
							'count' => 0,
						);
					}
					$categories_map[ $term->term_id ]['count']++;
				}
			}

			$products_data[] = array(
				'id'          => $product->get_id(),
				'title'       => $product->get_name(),
				'permalink'   => get_permalink( $product->get_id() ),
				'image_html'  => $product->get_image(),
				'status'      => $status,
				'current_bid' => $current_bid,
				'bid_count'   => $bid_count,
				'start_ts'    => $start_ts,
				'end_ts'      => $end_ts,
				'lot_number'  => $lot_number,
				'categories'  => $term_slugs,
			);
		}

		wp_reset_postdata();

		// Determine next auction date info.
		$next_date_label = '';
		$next_date_count = 0;
		if ( ! empty( $scheduled_map ) ) {
			ksort( $scheduled_map );
			foreach ( $scheduled_map as $date_key => $count ) {
				if ( $date_key >= $today_date_key ) {
					$next_date_label = wp_date( get_option( 'date_format' ), strtotime( $date_key ) );
					$next_date_count = $count;
					break;
				}
			}
		}

		ob_start();

		?>
		<div class="auction-listing-wrapper">
			<div class="auction-listing-header">
				<div class="auction-today">
					<strong><?php echo esc_html( wp_date( get_option( 'date_format' ), $now ) ); ?></strong>
					<span>
					<?php
					printf(
						/* translators: %d auction count */
						esc_html__( ' Live Auctions Today: %d', 'auction' ),
						(int) $todays_count
					);
					?>
					</span>
				</div>
				<?php if ( $next_date_label ) : ?>
					<div class="auction-next">
						<strong>
							<?php
							printf(
								/* translators: %s date */
								esc_html__( 'Next Auction Date: %s', 'auction' ),
								esc_html( $next_date_label )
							);
							?>
						</strong>
						<span>
							<?php
							printf(
								/* translators: %d auction count */
								esc_html__( ' Live Auctions: %d', 'auction' ),
								(int) $next_date_count
							);
							?>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<div class="auction-tabs">
				<button class="auction-tab-button is-active" data-tab="bid-gallery"><?php esc_html_e( 'Bid Gallery', 'auction' ); ?></button>
				<button class="auction-tab-button" data-tab="dates-times"><?php esc_html_e( 'Dates & Times', 'auction' ); ?></button>
				<button class="auction-tab-button" data-tab="terms"><?php esc_html_e( 'Terms & Conditions', 'auction' ); ?></button>
				<button class="auction-tab-button" data-tab="categories"><?php esc_html_e( 'Categories', 'auction' ); ?></button>
			</div>

			<div class="auction-tab-panels">
				<div id="auction-tab-bid-gallery" class="auction-tab-panel is-active">
					<div class="auction-controls">
						<div class="auction-search">
							<input type="search" id="auction-search-input" placeholder="<?php esc_attr_e( 'Search by keyword...', 'auction' ); ?>" />
						</div>
						<div class="auction-category-filter">
							<select id="auction-category-select">
								<option value=""><?php esc_html_e( 'All Categories', 'auction' ); ?></option>
								<?php
								if ( ! empty( $categories_map ) ) :
									// Filter to only show categories with auction products
									$filtered_categories = array_filter( $categories_map, function( $data ) {
										return isset( $data['count'] ) && $data['count'] > 0;
									});
									usort(
										$filtered_categories,
										static function ( $a, $b ) {
											return strcasecmp( $a['term']->name, $b['term']->name );
										}
									);
									foreach ( $filtered_categories as $data ) :
										$term = $data['term'];
										?>
										<option value="<?php echo esc_attr( $term->slug ); ?>">
											<?php echo esc_html( $term->name . ' (' . $data['count'] . ')' ); ?>
										</option>
										<?php
									endforeach;
								endif;
								?>
							</select>
						</div>
						<div class="auction-layout-toggle">
							<button type="button" class="layout-toggle-button is-active" data-layout="grid">⧉</button>
							<button type="button" class="layout-toggle-button" data-layout="list">☰</button>
						</div>
						<div class="auction-reset">
							<button type="button" id="auction-reset-filters" class="button">
								<?php esc_html_e( 'Reset', 'auction' ); ?>
							</button>
						</div>
					</div>

					<div class="auction-sorting">
						<span><?php esc_html_e( 'Sort by:', 'auction' ); ?></span>
						<button type="button" class="sort-button" data-sort="bid_count"><?php esc_html_e( 'Bid Count', 'auction' ); ?></button>
						<button type="button" class="sort-button" data-sort="end_ts"><?php esc_html_e( 'End Date', 'auction' ); ?></button>
						<button type="button" class="sort-button" data-sort="title"><?php esc_html_e( 'Title', 'auction' ); ?></button>
						<button type="button" class="sort-button" data-sort="lot_number"><?php esc_html_e( 'Lot Number', 'auction' ); ?></button>
						<button type="button" class="sort-button" data-sort="current_bid"><?php esc_html_e( 'Current Bid', 'auction' ); ?></button>
						<button type="button" class="sort-button clear-sorting"><?php esc_html_e( 'Clear Sorting', 'auction' ); ?></button>
					</div>

					<div id="auction-products" class="auction-products is-grid">
						<?php foreach ( $products_data as $item ) : ?>
							<?php
							$cat_attr = '';
							if ( ! empty( $item['categories'] ) ) {
								$cat_attr = implode( ' ', array_map( 'sanitize_html_class', $item['categories'] ) );
							}
							?>
							<div class="auction-product-card"
								data-title="<?php echo esc_attr( mb_strtolower( $item['title'] ) ); ?>"
								data-lot-number="<?php echo esc_attr( (int) $item['lot_number'] ); ?>"
								data-current-bid="<?php echo esc_attr( $item['current_bid'] ); ?>"
								data-bid-count="<?php echo esc_attr( (int) $item['bid_count'] ); ?>"
								data-end-ts="<?php echo esc_attr( $item['end_ts'] ? $item['end_ts'] : 0 ); ?>"
								data-category="<?php echo esc_attr( $cat_attr ); ?>">
								<a href="<?php echo esc_url( $item['permalink'] ); ?>" class="auction-product-image">
									<?php echo wp_kses_post( $item['image_html'] ); ?>
								</a>
								<div class="auction-product-content">
									<h3 class="auction-product-title">
										<a href="<?php echo esc_url( $item['permalink'] ); ?>">
											<?php echo esc_html( $item['title'] ); ?>
										</a>
									</h3>
									<p class="auction-product-meta">
										<span class="meta-lot"><?php esc_html_e( 'Lot #', 'auction' ); ?><?php echo esc_html( $item['lot_number'] ); ?></span>
										<span class="meta-status meta-status-<?php echo esc_attr( $item['status'] ); ?>">
											<?php echo esc_html( ucfirst( $item['status'] ) ); ?>
										</span>
									</p>
									<p class="auction-product-bids">
										<strong><?php esc_html_e( 'Current Bid:', 'auction' ); ?></strong>
										<?php echo wp_kses_post( wc_price( $item['current_bid'] ) ); ?>
										<span class="bid-count">
											<?php
											printf(
												/* translators: %d bids count */
												esc_html__( '(%d bids)', 'auction' ),
												(int) $item['bid_count']
											);
											?>
										</span>
									</p>
									<?php if ( $item['end_ts'] ) : ?>
										<p class="auction-product-end">
											<strong><?php esc_html_e( 'Ends:', 'auction' ); ?></strong>
											<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['end_ts'] ) ); ?>
										</p>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div id="auction-tab-dates-times" class="auction-tab-panel">
					<p><?php esc_html_e( 'Auction dates & times information will appear here. Use this area to describe preview days, bidding windows, inspection times, and pickup schedules.', 'auction' ); ?></p>
				</div>

				<div id="auction-tab-terms" class="auction-tab-panel">
					<p><?php esc_html_e( 'Terms & Conditions content placeholder. Add your buyer premium, payment terms, pickup requirements, and any legal disclaimers here.', 'auction' ); ?></p>
				</div>

				<div id="auction-tab-categories" class="auction-tab-panel">
					<?php 
					// Filter categories to only show those with auction products
					$filtered_categories = array_filter( $categories_map, function( $data ) {
						return isset( $data['count'] ) && $data['count'] > 0;
					});
					if ( ! empty( $filtered_categories ) ) : ?>
						<ul class="auction-category-list">
							<?php
							foreach ( $filtered_categories as $data ) :
								$term = $data['term'];
								?>
								<li>
									<a href="#auction-tab-bid-gallery" class="auction-category-link" data-category="<?php echo esc_attr( $term->slug ); ?>">
										<?php echo esc_html( $term->name ); ?>
									</a>
									<span class="auction-category-count">
										<?php
										printf(
											/* translators: %d products */
											esc_html__( '%d products', 'auction' ),
											(int) $data['count']
										);
										?>
									</span>
								</li>
								<?php
							endforeach;
							?>
						</ul>
					<?php else : ?>
						<p><?php esc_html_e( 'No categories found for current auctions.', 'auction' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<style>
			.auction-listing-wrapper { margin: 20px 0; }
			.auction-listing-header { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; padding: 15px 20px; background: #f5f5f5; border-radius: 4px; margin-bottom: 20px; }
			.auction-listing-header strong { display: block; font-size: 18px; }
			.auction-tabs { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 15px; }
			.auction-tab-button { border: none; padding: 10px 18px; cursor: pointer; background: #e0e0e0; border-radius: 3px 3px 0 0; font-weight: 600; }
			.auction-tab-button.is-active { background: #ff6600; color: #fff; }
			.auction-tab-panels { border: 1px solid #ddd; padding: 15px; background: #fff; }
			.auction-tab-panel { display: none; }
			.auction-tab-panel.is-active { display: block; }
			.auction-controls { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 10px; }
			.auction-search input { min-width: 220px; padding: 6px 10px; }
			.auction-category-filter select { min-width: 200px; padding: 6px 10px; }
			.auction-layout-toggle .layout-toggle-button { border: 1px solid #ccc; background: #fafafa; padding: 6px 10px; cursor: pointer; }
			.auction-layout-toggle .layout-toggle-button.is-active { background: #333; color: #fff; }
			.auction-sorting { margin: 10px 0 15px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 14px; }
			.auction-sorting .sort-button { border: 1px solid #ccc; background: #fafafa; padding: 5px 10px; cursor: pointer; font-size: 13px; }
			.auction-sorting .sort-button.active { background: #ff6600; color: #fff; border-color: #ff6600; }
			.auction-products { display: grid; gap: 15px; }
			.auction-products.is-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
			.auction-products.is-list { grid-template-columns: 1fr; }
			.auction-product-card { border: 1px solid #e0e0e0; border-radius: 3px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
			.auction-products.is-list .auction-product-card { flex-direction: row; }
			.auction-product-image img { width: 100%; height: auto; display: block; }
			.auction-products.is-list .auction-product-image { max-width: 200px; flex: 0 0 200px; }
			.auction-product-content { padding: 10px 12px 12px; flex: 1; }
			.auction-product-title { font-size: 16px; margin: 0 0 6px; }
			.auction-product-meta, .auction-product-bids, .auction-product-end { font-size: 13px; margin: 2px 0; }
			.auction-product-meta .meta-lot { margin-right: 10px; }
			.meta-status { padding: 1px 6px; border-radius: 3px; font-size: 11px; text-transform: uppercase; }
			.meta-status-active { background: #d4edda; color: #155724; }
			.meta-status-scheduled { background: #fff3cd; color: #856404; }
			.meta-status-ended { background: #f8d7da; color: #721c24; }
			.auction-category-list { list-style: none; margin: 0; padding: 0; }
			.auction-category-list li { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
			.auction-category-link { font-weight: 600; text-decoration: none; }
			@media (max-width: 600px) {
				.auction-listing-header { flex-direction: column; }
				.auction-products.is-list .auction-product-image { max-width: 120px; flex-basis: 120px; }
			}
		</style>

		<script>
			(function() {
				function initAuctionListing() {
					const root = document.querySelector('.auction-listing-wrapper');
					if (!root) { 
						// Retry after a short delay if root not found
						setTimeout(initAuctionListing, 100);
						return; 
					}

				// Tabs.
				const tabButtons = root.querySelectorAll('.auction-tab-button');
				const panels = root.querySelectorAll('.auction-tab-panel');
				tabButtons.forEach(function(btn) {
					btn.addEventListener('click', function() {
						const tab = btn.getAttribute('data-tab');
						tabButtons.forEach(function(b) { b.classList.remove('is-active'); });
						btn.classList.add('is-active');
						panels.forEach(function(panel) {
							panel.classList.toggle('is-active', panel.id === 'auction-tab-' + tab);
						});
					});
				});

				const productsContainer = root.querySelector('#auction-products');
				if (!productsContainer) { return; }

				const cards = Array.prototype.slice.call(productsContainer.querySelectorAll('.auction-product-card'));

				// Search and filter.
				const searchInput = root.querySelector('#auction-search-input');
				const categorySelect = root.querySelector('#auction-category-select');
				let currentSort = null;

				function sortCards(key) {
					var sorted = cards.slice().sort(function(a, b) {
						if (key === 'title') {
							var ta = (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || '');
							return ta;
						}

						function parseNum(el, attr) {
							var v = el.getAttribute(attr);
							var n = v ? parseFloat(v) : 0;
							return isNaN(n) ? 0 : n;
						}

						var av, bv;
						switch (key) {
							case 'bid_count':
								av = parseNum(a, 'data-bid-count');
								bv = parseNum(b, 'data-bid-count');
								break;
							case 'end_ts':
								av = parseNum(a, 'data-end-ts');
								bv = parseNum(b, 'data-end-ts');
								break;
							case 'lot_number':
								av = parseNum(a, 'data-lot-number');
								bv = parseNum(b, 'data-lot-number');
								break;
							case 'current_bid':
								av = parseNum(a, 'data-current-bid');
								bv = parseNum(b, 'data-current-bid');
								break;
							default:
								return 0;
						}

						// Desc for numeric values, asc for title.
						return bv - av;
					});

					sorted.forEach(function(card) {
						productsContainer.appendChild(card);
					});
				}

				function applyFiltersAndSorting() {
					var term = searchInput ? searchInput.value.trim().toLowerCase() : '';
					var category = categorySelect ? categorySelect.value : '';

					cards.forEach(function(card) {
						var title = card.getAttribute('data-title') || '';
						var cats = (card.getAttribute('data-category') || '').split(/\\s+/);
						var visible = true;

						if (term && title.indexOf(term) === -1) {
							visible = false;
						}
						if (visible && category) {
							visible = cats.indexOf(category) !== -1;
						}

						card.style.display = visible ? '' : 'none';
					});

					if (currentSort) {
						sortCards(currentSort);
					}
				}

				if (searchInput) {
					searchInput.addEventListener('input', applyFiltersAndSorting);
				}
				if (categorySelect) {
					categorySelect.addEventListener('change', applyFiltersAndSorting);
				}

				// Category tab links -> filter + switch tab.
				Array.prototype.slice.call(root.querySelectorAll('.auction-category-link')).forEach(function(link) {
					link.addEventListener('click', function(e) {
						e.preventDefault();
						var cat = this.getAttribute('data-category');
						if (categorySelect) {
							categorySelect.value = cat || '';
						}
						var bidTabButton = root.querySelector('.auction-tab-button[data-tab=\"bid-gallery\"]');
						if (bidTabButton) {
							bidTabButton.click();
						}
						applyFiltersAndSorting();
						var top = root.querySelector('#auction-tab-bid-gallery');
						if (top && top.scrollIntoView) {
							top.scrollIntoView({ behavior: 'smooth', block: 'start' });
						}
					});
				});

				// Layout toggle - support both class and ID versions
				if (productsContainer) {
					// Handle ID version (#auction-layout-toggle - single button)
					var layoutToggleBtn = root.querySelector('#auction-layout-toggle');
					if (!layoutToggleBtn) {
						layoutToggleBtn = document.querySelector('#auction-layout-toggle');
					}
					
					if (layoutToggleBtn) {
						layoutToggleBtn.addEventListener('click', function(e) {
							e.preventDefault();
							e.stopPropagation();
							
							var layout = this.getAttribute('data-layout') || 'grid';
							var newLayout = layout === 'grid' ? 'list' : 'grid';
							this.setAttribute('data-layout', newLayout);
							
							// Toggle layout classes
							productsContainer.classList.remove('is-grid', 'is-list', 'grid-view', 'list-view');
							if (newLayout === 'list') {
								productsContainer.classList.add('is-list');
							} else {
								productsContainer.classList.add('is-grid');
							}
							
							// Update button text
							this.textContent = newLayout === 'grid' ? '☰' : '⧉';
						});
					}
					
					// Handle class version (.auction-layout-toggle - container with buttons)
					var layoutToggleContainer = root.querySelector('.auction-layout-toggle');
					if (!layoutToggleContainer) {
						layoutToggleContainer = document.querySelector('.auction-layout-toggle');
					}
					
					if (layoutToggleContainer && !layoutToggleBtn) {
						layoutToggleContainer.addEventListener('click', function(e) {
							var btn = e.target;
							if (!btn || !btn.classList.contains('layout-toggle-button')) {
								return;
							}
							
							e.preventDefault();
							e.stopPropagation();
							
							var layout = btn.getAttribute('data-layout');
							if (!layout) { return; }
							
							// Update all buttons
							var allButtons = layoutToggleContainer.querySelectorAll('.layout-toggle-button');
							Array.prototype.forEach.call(allButtons, function(b) { 
								b.classList.remove('is-active'); 
							});
							btn.classList.add('is-active');
							
							// Toggle layout classes
							if (layout === 'list') {
								productsContainer.classList.remove('is-grid');
								productsContainer.classList.add('is-list');
							} else {
								productsContainer.classList.remove('is-list');
								productsContainer.classList.add('is-grid');
							}
						});
					}
				}

				// Sorting.
				var sortButtons = root.querySelectorAll('.sort-button');
				Array.prototype.forEach.call(sortButtons, function(btn) {
					btn.addEventListener('click', function() {
						var sortKey = btn.classList.contains('clear-sorting') ? null : btn.getAttribute('data-sort');
						Array.prototype.forEach.call(sortButtons, function(b) { b.classList.remove('active'); });
						if (sortKey) {
							btn.classList.add('active');
							currentSort = sortKey;
							sortCards(sortKey);
						} else {
							currentSort = null;
						}
					});
				});

				// Reset.
				var resetBtn = root.querySelector('#auction-reset-filters');
				if (resetBtn) {
					resetBtn.addEventListener('click', function() {
						if (searchInput) { searchInput.value = ''; }
						if (categorySelect) { categorySelect.value = ''; }
						currentSort = null;
						Array.prototype.forEach.call(sortButtons, function(b) { b.classList.remove('active'); });
						cards.forEach(function(card) { card.style.display = ''; });
					});
				}

				applyFiltersAndSorting();
				}
				
				// Run on DOM ready
				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', initAuctionListing);
				} else {
					initAuctionListing();
				}
			})();
		</script>
		<?php

		return (string) ob_get_clean();
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
		// Set columns to 5 for related auctions
		wc_set_loop_prop( 'columns', 5 );
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

	/**
	 * Check if we're on the auction listing page.
	 *
	 * @return bool
	 */
	private function is_auction_listing_page(): bool {
		// Check query var from rewrite rule
		global $wp_query;
		if ( $wp_query && $wp_query->get( 'auction_page' ) === '1' ) {
			return true;
		}

		// Check $_GET for compatibility
		if ( isset( $_GET['auction_page'] ) && '1' === $_GET['auction_page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}

	/**
	 * Render auction listing header (date info, tabs, search, filters).
	 *
	 * @return void
	 */
	public function render_auction_listing_header(): void {
		if ( ! $this->is_auction_listing_page() ) {
			return;
		}

		// Get all auction products to calculate counts
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_auction_enabled',
						'value' => 'yes',
					),
				),
			)
		);

		$now            = current_time( 'timestamp' );
		$today_date_key  = gmdate( 'Y-m-d', $now );
		$todays_count    = 0;
		$scheduled_map   = array(); // date_key => count
		$categories_map  = array(); // term_id => array( 'term' => term, 'count' => n )

		// Process products to get counts
		while ( $query->have_posts() ) {
			$query->the_post();
			$product = wc_get_product( get_the_ID() );

			if ( ! $product ) {
				continue;
			}

			// Double-check: ensure this is actually an auction product
			if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
				continue;
			}

			$config = Auction_Product_Helper::get_config( $product );
			$status = Auction_Product_Helper::get_auction_status( $config );
			$start_ts = $config['start_timestamp'] ?: 0;
			$end_ts   = $config['end_timestamp'] ?: 0;

			// Today live auction count: active auctions that can accept bids (status = 'active')
			// This means the auction has started and hasn't ended yet
			if ( 'active' === $status ) {
				$todays_count++;
			}

			// Next auction date map (scheduled)
			if ( 'scheduled' === $status && $start_ts ) {
				$date_key = gmdate( 'Y-m-d', $start_ts );
				if ( ! isset( $scheduled_map[ $date_key ] ) ) {
					$scheduled_map[ $date_key ] = 0;
				}
				$scheduled_map[ $date_key ]++;
			}

			// Categories - only add categories from confirmed auction products
			$terms = get_the_terms( $product->get_id(), 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( ! isset( $categories_map[ $term->term_id ] ) ) {
						$categories_map[ $term->term_id ] = array(
							'term'  => $term,
							'count' => 0,
						);
					}
					$categories_map[ $term->term_id ]['count']++;
				}
			}
		}
		wp_reset_postdata();

		// Filter out categories with zero count - only keep categories that have auction products
		// This is a safety check, but categories_map should only contain categories from auction products
		$filtered_categories = array();
		foreach ( $categories_map as $term_id => $cat_data ) {
			if ( isset( $cat_data['count'] ) && (int) $cat_data['count'] > 0 && isset( $cat_data['term'] ) && is_object( $cat_data['term'] ) ) {
				$filtered_categories[ $term_id ] = $cat_data;
			}
		}
		$categories_map = $filtered_categories;

		// Get next auction date
		$next_date = null;
		$next_count = 0;
		if ( ! empty( $scheduled_map ) ) {
			ksort( $scheduled_map );
			$next_date = array_key_first( $scheduled_map );
			$next_count = $scheduled_map[ $next_date ];
		}

		$today_formatted      = wp_date( get_option( 'date_format' ), $now );
		$next_date_formatted  = $next_date ? wp_date( get_option( 'date_format' ), strtotime( $next_date ) ) : '';

		// Load configurable frontend texts for Dates & Times and Terms & Conditions tabs.
		if ( ! class_exists( 'Auction_Settings' ) ) {
			// Frontend file is in includes/frontend/, settings class is in includes/.
			require_once plugin_dir_path( __DIR__ ) . 'class-auction-settings.php';
		}
		$dates_times_content = wp_kses_post( Auction_Settings::get( 'dates_times_content', '' ) );
		$terms_content       = wp_kses_post( Auction_Settings::get( 'terms_content', '' ) );

		// Output header HTML
		?>
		<div class="auction-listing-page">
			<!-- Header with date and counts -->
			<div class="auction-header-banner" style="background: #ff6600; color: white; padding: 20px; text-align: center; margin-bottom: 20px;">
				<div style="display: flex; justify-content: space-around; align-items: center; flex-wrap: wrap; gap: 20px;">
					<div>
						<strong><?php echo esc_html( $today_formatted ); ?></strong><br>
						<span>Live Auctions Today: <?php echo esc_html( $todays_count ); ?></span>
					</div>
					<?php if ( $next_date_formatted ) : ?>
					<div>
						<strong>Next Auction: <?php echo esc_html( $next_date_formatted ); ?></strong><br>
						<span>Live Auctions: <?php echo esc_html( $next_count ); ?></span>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Tabs -->
			<div class="auction-tabs" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ddd;">
				<button class="auction-tab active" data-tab="bid-gallery" style="padding: 10px 20px; background: #ff6600; color: white; border: none; cursor: pointer;">
					<?php esc_html_e( 'BID GALLERY', 'auction' ); ?>
				</button>
				<button class="auction-tab" data-tab="dates-times" style="padding: 10px 20px; background: #87ceeb; color: #333; border: none; cursor: pointer;">
					<?php esc_html_e( 'DATES & TIMES', 'auction' ); ?>
				</button>
				<button class="auction-tab" data-tab="terms" style="padding: 10px 20px; background: #87ceeb; color: #333; border: none; cursor: pointer;">
					<?php esc_html_e( 'TERMS & CONDITIONS', 'auction' ); ?>
				</button>
				<button class="auction-tab" data-tab="categories" style="padding: 10px 20px; background: #87ceeb; color: #333; border: none; cursor: pointer;">
					<?php esc_html_e( 'CATEGORIES', 'auction' ); ?>
				</button>
			</div>

			<!-- Tab Content: Bid Gallery -->
			<div class="auction-tab-content active" id="bid-gallery">
				<!-- Search and Filters -->
				<div class="auction-filters" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
					<input type="text" id="auction-search" placeholder="<?php esc_attr_e( 'Search by keyword...', 'auction' ); ?>" style="flex: 1; min-width: 200px; padding: 8px; border: 1px solid #ddd;">
					<button type="button" id="auction-search-btn" style="padding: 8px 15px; background: #000; color: white; border: none; cursor: pointer;">🔍</button>
					<select id="auction-category-filter" style="padding: 8px; border: 1px solid #ddd;">
						<option value=""><?php esc_html_e( 'All Categories', 'auction' ); ?></option>
						<?php
						if ( ! empty( $categories_map ) ) {
							foreach ( $categories_map as $cat_data ) {
								// Categories are already filtered, but double-check for safety
								if ( isset( $cat_data['term'] ) && isset( $cat_data['count'] ) && $cat_data['count'] > 0 ) {
									$term = $cat_data['term'];
									echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . ' (' . esc_html( $cat_data['count'] ) . ')</option>';
								}
							}
						}
						?>
					</select>
					<button type="button" id="auction-reset" style="padding: 8px 15px; background: #666; color: white; border: none; cursor: pointer;">
						<?php esc_html_e( 'Reset', 'auction' ); ?>
					</button>
					<button type="button" id="auction-layout-toggle" data-layout="grid" style="padding: 8px 15px; background: #666; color: white; border: none; cursor: pointer;">☰</button>
				</div>

				<!-- Results Summary -->
				<div class="auction-results-summary" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
					<span id="auction-results-count"><?php esc_html_e( 'Loading...', 'auction' ); ?></span>
				</div>

				<!-- Sorting -->
				<div class="auction-sorting" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
					<div class="auction-sort-wrapper" style="position: relative;">
						<button type="button" class="auction-sort-btn" data-sort="bid-count" style="padding: 8px 15px; background: #333; color: white; border: none; cursor: pointer;">
							<?php esc_html_e( 'Bid Count', 'auction' ); ?> ↕
						</button>
						<div class="auction-sort-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; min-width: 150px; margin-top: 2px;">
							<button type="button" class="auction-sort-option" data-sort="bid-count" data-direction="asc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; border-bottom: 1px solid #eee; color: #000;">
								<?php esc_html_e( 'Ascending', 'auction' ); ?> ↑
							</button>
							<button type="button" class="auction-sort-option" data-sort="bid-count" data-direction="desc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; color: #000;">
								<?php esc_html_e( 'Descending', 'auction' ); ?> ↓
							</button>
						</div>
					</div>
					<div class="auction-sort-wrapper" style="position: relative;">
						<button type="button" class="auction-sort-btn" data-sort="end-date" style="padding: 8px 15px; background: #333; color: white; border: none; cursor: pointer;">
							<?php esc_html_e( 'End Date', 'auction' ); ?> ↕
						</button>
						<div class="auction-sort-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; min-width: 150px; margin-top: 2px;">
							<button type="button" class="auction-sort-option" data-sort="end-date" data-direction="asc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; border-bottom: 1px solid #eee; color: #000;">
								<?php esc_html_e( 'Ascending', 'auction' ); ?> ↑
							</button>
							<button type="button" class="auction-sort-option" data-sort="end-date" data-direction="desc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; color: #000;">
								<?php esc_html_e( 'Descending', 'auction' ); ?> ↓
							</button>
						</div>
					</div>
					<div class="auction-sort-wrapper" style="position: relative;">
						<button type="button" class="auction-sort-btn" data-sort="title" style="padding: 8px 15px; background: #333; color: white; border: none; cursor: pointer;">
							<?php esc_html_e( 'Title', 'auction' ); ?> ↕
						</button>
						<div class="auction-sort-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; min-width: 150px; margin-top: 2px;">
							<button type="button" class="auction-sort-option" data-sort="title" data-direction="asc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; border-bottom: 1px solid #eee; color: #000;">
								<?php esc_html_e( 'Ascending', 'auction' ); ?> ↑
							</button>
							<button type="button" class="auction-sort-option" data-sort="title" data-direction="desc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; color: #000;">
								<?php esc_html_e( 'Descending', 'auction' ); ?> ↓
							</button>
						</div>
					</div>
					<div class="auction-sort-wrapper" style="position: relative;">
						<button type="button" class="auction-sort-btn" data-sort="lot-number" style="padding: 8px 15px; background: #333; color: white; border: none; cursor: pointer;">
							<?php esc_html_e( 'Lot Number', 'auction' ); ?> ↕
						</button>
						<div class="auction-sort-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; min-width: 150px; margin-top: 2px;">
							<button type="button" class="auction-sort-option" data-sort="lot-number" data-direction="asc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; border-bottom: 1px solid #eee; color: #000;">
								<?php esc_html_e( 'Ascending', 'auction' ); ?> ↑
							</button>
							<button type="button" class="auction-sort-option" data-sort="lot-number" data-direction="desc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; color: #000;">
								<?php esc_html_e( 'Descending', 'auction' ); ?> ↓
							</button>
						</div>
					</div>
					<div class="auction-sort-wrapper" style="position: relative;">
						<button type="button" class="auction-sort-btn" data-sort="current-bid" style="padding: 8px 15px; background: #333; color: white; border: none; cursor: pointer;">
							<?php esc_html_e( 'Current Bid', 'auction' ); ?> ↕
						</button>
						<div class="auction-sort-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; min-width: 150px; margin-top: 2px;">
							<button type="button" class="auction-sort-option" data-sort="current-bid" data-direction="asc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; border-bottom: 1px solid #eee; color: #000;">
								<?php esc_html_e( 'Ascending', 'auction' ); ?> ↑
							</button>
							<button type="button" class="auction-sort-option" data-sort="current-bid" data-direction="desc" style="display: block; width: 100%; padding: 8px 15px; text-align: left; background: white; border: none; cursor: pointer; color: #000;">
								<?php esc_html_e( 'Descending', 'auction' ); ?> ↓
							</button>
						</div>
					</div>
					<button type="button" id="auction-clear-sort" style="padding: 8px 15px; background: #999; color: white; border: none; cursor: pointer;">
						<?php esc_html_e( 'Clear Sorting', 'auction' ); ?>
					</button>
				</div>
			</div>

			<!-- Tab Content: Dates & Times -->
			<div class="auction-tab-content" id="dates-times" style="display: none; padding: 20px;">
				<h3><?php esc_html_e( 'Auction Dates & Times', 'auction' ); ?></h3>
				<?php if ( ! empty( $dates_times_content ) ) : ?>
					<div class="auction-dates-times-content">
						<?php echo $dates_times_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'Auction dates & times information will appear here.', 'auction' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Tab Content: Terms & Conditions -->
			<div class="auction-tab-content" id="terms" style="display: none; padding: 20px;">
				<h3><?php esc_html_e( 'Terms & Conditions', 'auction' ); ?></h3>
				<?php if ( ! empty( $terms_content ) ) : ?>
					<div class="auction-terms-content">
						<?php echo $terms_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'Terms & conditions information will appear here.', 'auction' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Tab Content: Categories -->
			<div class="auction-tab-content" id="categories" style="display: none; padding: 20px;">
				<h3><?php esc_html_e( 'Categories', 'auction' ); ?></h3>
				<div class="auction-categories-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
					<?php
					if ( ! empty( $categories_map ) ) {
						foreach ( $categories_map as $cat_data ) {
							// Categories are already filtered, but double-check for safety
							if ( isset( $cat_data['term'] ) && isset( $cat_data['count'] ) && $cat_data['count'] > 0 ) {
								$term = $cat_data['term'];
								echo '<div class="auction-category-item" style="padding: 15px; border: 1px solid #ddd; cursor: pointer; text-align: center;" data-category-slug="' . esc_attr( $term->slug ) . '">';
								echo '<strong>' . esc_html( $term->name ) . '</strong><br>';
								echo '<span>' . esc_html( $cat_data['count'] ) . ' ' . esc_html__( 'products', 'auction' ) . '</span>';
								echo '</div>';
							}
						}
					} else {
						echo '<p>' . esc_html__( 'No categories found for current auctions.', 'auction' ) . '</p>';
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render auction listing footer (closing elements and JavaScript).
	 *
	 * @return void
	 */
	public function render_auction_listing_footer(): void {
		if ( ! $this->is_auction_listing_page() ) {
			return;
		}
		?>
		<style>
		/* Sort dropdown styles */
		.auction-sort-wrapper {
			position: relative;
		}
		.auction-sort-dropdown {
			display: none;
			position: absolute;
			top: 100%;
			left: 0;
			background: white;
			border: 1px solid #ddd;
			box-shadow: 0 2px 5px rgba(0,0,0,0.2);
			z-index: 1000;
			min-width: 150px;
			margin-top: 2px;
		}
		.auction-sort-option {
			display: block;
			width: 100%;
			padding: 8px 15px;
			text-align: left;
			background: white;
			border: none;
			cursor: pointer;
			border-bottom: 1px solid #eee;
			color: #000 !important;
		}
		.auction-sort-option:last-child {
			border-bottom: none;
		}
		.auction-sort-option:hover {
			background: #f5f5f5;
			color: #000 !important;
		}
		.auction-sort-option.active {
			background: #f5f5f5;
			font-weight: bold;
			color: #000 !important;
		}
		
		/* List layout styles - only apply when .is-list class is present */
		.products.is-list,
		.products.auction-products-container.is-list,
		.auction-products-container.is-list {
			display: flex !important;
			flex-direction: column !important;
			gap: 15px !important;
		}
		
		.products.is-list li.product,
		.products.auction-products-container.is-list li.product,
		.auction-products-container.is-list li.product {
			display: flex !important;
			flex-direction: row !important;
			align-items: flex-start !important;
			width: 100% !important;
			max-width: 100% !important;
			margin: 0 !important;
			float: none !important;
		}
		
		.products.is-list li.product .woocommerce-loop-product__link,
		.products.is-list li.product .wp-post-image,
		.products.is-list li.product img {
			width: 150px !important;
			min-width: 150px !important;
			max-width: 150px !important;
			margin-right: 15px !important;
			flex-shrink: 0 !important;
		}
		
		.products.is-list li.product .woocommerce-loop-product__title,
		.products.is-list li.product .product-details {
			flex: 1 !important;
		}
		
		/* Grid layout - ensure WooCommerce's default grid layout is maintained */
		/* Don't override - let WooCommerce handle the default layout */
		.products.is-grid li.product,
		.products.auction-products-container.is-grid li.product {
			/* Reset any list-mode styles that might interfere */
			display: block;
		}
		
		/* Hide products when non-Bid Gallery tabs are active */
		.auction-listing-page:has(.auction-tab[data-tab="dates-times"].active) ~ .products,
		.auction-listing-page:has(.auction-tab[data-tab="terms"].active) ~ .products,
		.auction-listing-page:has(.auction-tab[data-tab="categories"].active) ~ .products {
			display: none !important;
		}
		</style>
		<script type="text/javascript">
		(function($) {
			'use strict';

			var currentSort = {field: null, direction: 'asc'};

			// Add data attributes to product items
			function addProductDataAttributes() {
				$('.products li.product').each(function() {
					var $item = $(this);
					
					// Skip if already processed
					if ($item.hasClass('auction-data-processed')) {
						return;
					}

					// Get product ID - try multiple methods
					var productId = null;
					
					// Method 1: From data-product_id attribute
					productId = $item.find('[data-product_id]').first().attr('data-product_id');
					
					// Method 2: From add to cart button
					if (!productId) {
						productId = $item.find('a.add_to_cart_button').attr('data-product_id');
					}
					
					// Method 3: From form
					if (!productId) {
						productId = $item.find('form.cart').attr('data-product_id');
					}
					
					// Method 4: From product link URL
					if (!productId) {
						var $productLink = $item.find('a.woocommerce-LoopProduct-link, a[href*="/product/"]').first();
						if ($productLink.length) {
							var href = $productLink.attr('href');
							// Try to extract ID from URL
							var idMatch = href.match(/\/product\/.*?(\d+)/);
							if (idMatch) {
								productId = idMatch[1];
							} else {
								// Fallback: use slug
								var slugMatch = href.match(/\/product\/([^\/\?]+)/);
								if (slugMatch) {
									productId = slugMatch[1];
								}
							}
						}
					}

					if (!productId) {
						console.warn('Could not find product ID for item:', $item);
						return;
					}

					// Get title
					var title = $item.find('h2.woocommerce-loop-product__title').text().trim() ||
					            $item.find('h2.product-title').text().trim() ||
					            $item.find('h3').text().trim() ||
					            $item.find('.product-title').text().trim() ||
					            $item.find('a.woocommerce-LoopProduct-link').text().trim() ||
					            $item.find('a').first().text().trim() ||
					            '';

					// Get categories from classes (WooCommerce adds product_cat-{slug} classes)
					// A product can have multiple categories, so collect all of them
					var categorySlugs = [];
					var classes = $item.attr('class') || '';
					var catMatches = classes.match(/product_cat-([^\s]+)/g);
					if (catMatches) {
						catMatches.forEach(function(match) {
							var slug = match.replace('product_cat-', '');
							if (categorySlugs.indexOf(slug) === -1) {
								categorySlugs.push(slug);
							}
						});
					}
					var categorySlug = categorySlugs.length > 0 ? categorySlugs[0] : ''; // Keep first for backward compatibility
					var allCategories = categorySlugs.join(' '); // Space-separated for data-category attribute

					// Get bid info from auction-loop-meta
					var $auctionMeta = $item.find('.auction-loop-meta');
					var bidCount = 0;
					var currentBid = 0;
					var endTimestamp = 0;

					if ($auctionMeta.length) {
						// Extract bid count.
						// Prefer explicit elements, but also support text patterns like "Current Bid:(bids: 2)" or "(2 bids)".
						var bidCountText = $auctionMeta.find('.auction-bid-count, .bid-count').first().text();
						if (!bidCountText) {
							bidCountText = $auctionMeta.text();
						}
						if (bidCountText) {
							var bidMatch = bidCountText.match(/bids:\s*(\d+)/i) || bidCountText.match(/\(\s*(\d+)\s*bids?\s*\)/i) || bidCountText.match(/(\d+)\s*bids?/i);
							if (bidMatch && bidMatch[1]) {
								bidCount = parseInt(bidMatch[1], 10) || 0;
							}
						}

						// Extract current bid - remove currency symbols and parse
						var currentBidText = $auctionMeta.find('.auction-current-bid').text() || '';
						if (currentBidText) {
							// Remove all non-numeric characters except decimal point
							currentBid = parseFloat(currentBidText.replace(/[^\d.]/g, '')) || 0;
						}

						// Extract end timestamp
						endTimestamp = $auctionMeta.attr('data-auction-end') || 
						               $auctionMeta.find('[data-auction-end]').attr('data-auction-end') || 0;
						endTimestamp = parseInt(endTimestamp, 10) || 0;
					}

					// Add data attributes
					$item.addClass('auction-product-item auction-data-processed')
					      .attr('data-title', title)
					      .attr('data-lot-number', productId)
					      .attr('data-bid-count', bidCount)
					      .attr('data-current-bid', currentBid)
					      .attr('data-end-timestamp', endTimestamp)
					      .attr('data-category-slug', categorySlug)
					      .attr('data-category', allCategories); // Store all categories for proper filtering
				});
			}

			// Function to show/hide products based on active tab
			function toggleProductsVisibility() {
				var $activeTab = $('.auction-tab.active');
				var activeTabId = $activeTab.length ? $activeTab.data('tab') : 'bid-gallery';
				
				if (activeTabId === 'bid-gallery') {
					// Show products when Bid Gallery tab is active
					$('.products, .auction-products-container, .woocommerce-products-header, .woocommerce-pagination, ul.products').show();
				} else {
					// Hide products when other tabs are active
					$('.products, .auction-products-container, .woocommerce-products-header, .woocommerce-pagination, ul.products').hide();
				}
			}

			// Tab switching
			$(document).on('click', '.auction-tab', function() {
				var tabId = $(this).data('tab');
				$('.auction-tab').removeClass('active').css({'background': '#87ceeb', 'color': '#333'});
				$(this).addClass('active').css({'background': '#ff6600', 'color': 'white'});
				$('.auction-tab-content').hide();
				$('#' + tabId).show();
				
				// Hide/show products based on active tab
				toggleProductsVisibility();
			});

			// Category click in Categories tab
			$(document).on('click', '.auction-category-item', function() {
				var slug = $(this).attr('data-category-slug') || $(this).data('category-slug');
				$('#auction-category-filter').val(slug);
				$('.auction-tab[data-tab="bid-gallery"]').trigger('click');
				setTimeout(function() {
					filterProducts();
					$('html, body').animate({scrollTop: $('#bid-gallery').offset().top - 100}, 500);
				}, 100);
			});

			// Search
			$(document).on('input', '#auction-search', function() {
				filterProducts();
			});
			$(document).on('keyup', '#auction-search', function(e) {
				// Also trigger on Enter key
				if (e.keyCode === 13) {
					filterProducts();
				}
			});
			$(document).on('click', '#auction-search-btn', function() {
				filterProducts();
			});

			// Category filter
			$(document).on('change', '#auction-category-filter', function() {
				filterProducts();
			});

			// Reset
			$(document).on('click', '#auction-reset', function() {
				$('#auction-search').val('');
				$('#auction-category-filter').val('');
				currentSort = {field: null, direction: 'asc'};
				$('.auction-sort-btn').removeClass('active');
				// Reset all products to visible
				$('.auction-product-item, .products li.product').show();
				filterProducts();
				updateResultsCount();
			});

			// Helper function to find products container
			function findProductsContainer() {
				// Try multiple selectors in order of preference
				var selectors = [
					'.products.auction-products-container',
					'ul.products.auction-products-container',
					'.woocommerce ul.products',
					'ul.products',
					'.products',
					'#auction-products',
					'.auction-products',
					'.auction-products-container',
					// More aggressive fallbacks
					'[class*="products"]',
					'ul[class*="product"]'
				];
				
				for (var i = 0; i < selectors.length; i++) {
					var $container = $(selectors[i]);
					// Make sure it actually contains product items
					if ($container.length > 0 && $container.find('li.product, .product, [class*="product"]').length > 0) {
						return $container.first();
					}
				}
				
				// Last resort: find any ul that contains product-related classes
				var $allUls = $('ul');
				for (var j = 0; j < $allUls.length; j++) {
					var $ul = $allUls.eq(j);
					if ($ul.find('li.product, .product').length > 0 || $ul.hasClass('products') || $ul.attr('class') && $ul.attr('class').indexOf('product') !== -1) {
						return $ul;
					}
				}
				
				return null;
			}
			
			// Layout toggle
			$(document).on('click', '#auction-layout-toggle', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var layout = $(this).attr('data-layout') || 'grid';
				var newLayout = layout === 'grid' ? 'list' : 'grid';
				$(this).attr('data-layout', newLayout);
				
				// Find products container
				var $container = findProductsContainer();
				
				if ($container && $container.length) {
					$container.removeClass('is-grid is-list grid-view list-view');
					if (newLayout === 'list') {
						$container.addClass('is-list');
					} else {
						$container.addClass('is-grid');
					}
					console.log('Auction layout toggle: Applied', newLayout, 'layout to container', $container[0]);
				} else {
					// Last resort: Find a product item and get its parent container
					var $firstProduct = $('li.product, .product, [class*="product"]').first();
					if ($firstProduct.length) {
						// Find the closest ul or div that contains products
						$container = $firstProduct.closest('ul, .products, [class*="product"]');
						if ($container.length && $container.find('li.product, .product').length > 0) {
							$container.removeClass('is-grid is-list grid-view list-view');
							if (newLayout === 'list') {
								$container.addClass('is-list');
							} else {
								$container.addClass('is-grid');
							}
							console.log('Auction layout toggle: Found container via product item', $container[0]);
						} else {
							// Try to find any ul with products
							$container = $('ul.products, .woocommerce ul.products, ul[class*="product"]').first();
							if ($container.length) {
								$container.removeClass('is-grid is-list grid-view list-view');
								if (newLayout === 'list') {
									$container.addClass('is-list');
								} else {
									$container.addClass('is-grid');
								}
								console.log('Auction layout toggle: Found fallback container', $container[0]);
							} else {
								// Debug: log what containers are available
								console.warn('Auction layout toggle: Products container not found. Available containers:', {
									products: $('.products').length,
									ulProducts: $('ul.products').length,
									woocommerceProducts: $('.woocommerce ul.products').length,
									auctionProducts: $('#auction-products').length,
									auctionProductsContainer: $('.auction-products-container').length,
									allProducts: $('ul.products, .products').length,
									productItems: $('li.product, .product').length
								});
							}
						}
					} else {
						console.warn('Auction layout toggle: No product items found on page');
					}
				}
				
				$(this).text(newLayout === 'grid' ? '☰' : '⧉');
			});
			
			// Initialize default grid layout on page load and watch for products
			function initializeLayout() {
				var $container = findProductsContainer();
				if ($container && $container.length) {
					if (!$container.hasClass('is-grid') && !$container.hasClass('is-list')) {
						$container.addClass('is-grid');
					}
					return true;
				}
				return false;
			}
			
			// Try immediately
			$(document).ready(function() {
				if (!initializeLayout()) {
					// If not found, try again after a delay
					setTimeout(function() {
						if (!initializeLayout()) {
							// Try one more time after WooCommerce might have loaded
							setTimeout(initializeLayout, 500);
						}
					}, 100);
				}
			});
			
			// Also watch for WooCommerce product updates
			$(document.body).on('updated_wc_div', function() {
				initializeLayout();
			});
			
			// Watch for products being added to DOM
			if (window.MutationObserver) {
				var observer = new MutationObserver(function(mutations) {
					var $container = findProductsContainer();
					if ($container && $container.length && !$container.hasClass('is-grid') && !$container.hasClass('is-list')) {
						$container.addClass('is-grid');
					}
				});
				
				observer.observe(document.body, {
					childList: true,
					subtree: true
				});
			}

			// Sorting - show/hide dropdown on hover and click
			$(document).on('mouseenter', '.auction-sort-wrapper', function() {
				$(this).find('.auction-sort-dropdown').stop(true, true).fadeIn(200);
			});
			
			$(document).on('mouseleave', '.auction-sort-wrapper', function() {
				$(this).find('.auction-sort-dropdown').stop(true, true).fadeOut(200);
			});
			
			// Click on sort button - toggle dropdown
			$(document).on('click', '.auction-sort-btn', function(e) {
				e.stopPropagation();
				var $wrapper = $(this).closest('.auction-sort-wrapper');
				var $dropdown = $wrapper.find('.auction-sort-dropdown');
				
				// Close all other dropdowns
				$('.auction-sort-dropdown').not($dropdown).fadeOut(200);
				
				// Toggle current dropdown
				$dropdown.stop(true, true).fadeToggle(200);
			});
			
			// Click on sort option (ascending/descending)
			$(document).on('click', '.auction-sort-option', function(e) {
				e.stopPropagation();
				var field = $(this).data('sort');
				var direction = $(this).data('direction');
				
				currentSort.field = field;
				currentSort.direction = direction;
				
				// Update active states (only for dropdown options, not buttons)
				$('.auction-sort-option').removeClass('active');
				$(this).addClass('active');
				
				// Update button text to show current direction
				var $btn = $(this).closest('.auction-sort-wrapper').find('.auction-sort-btn');
				var btnText = $btn.text().replace(/\s*[↑↓↕]\s*$/, '').trim();
				$btn.text(btnText + ' ' + (direction === 'asc' ? '↑' : '↓'));
				
				// Close dropdown
				$(this).closest('.auction-sort-dropdown').fadeOut(200);
				
				// Apply sorting
				sortProducts();
			});
			
			// Close dropdowns when clicking outside
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.auction-sort-wrapper').length) {
					$('.auction-sort-dropdown').fadeOut(200);
				}
			});

			$(document).on('click', '#auction-clear-sort', function() {
				currentSort = {field: null, direction: 'asc'};
				$('.auction-sort-btn').removeClass('active');
				$('.auction-sort-option').removeClass('active');
				
				// Reset button texts to show ↕
				$('.auction-sort-btn').each(function() {
					var $btn = $(this);
					var btnText = $btn.text().replace(/\s*[↑↓↕]\s*$/, '').trim();
					$btn.text(btnText + ' ↕');
				});
				
				// Close all dropdowns
				$('.auction-sort-dropdown').fadeOut(200);
				
				// Reset order by re-adding all items in original order
				var $container = findProductsContainer();
				if (!$container || !$container.length) {
					$container = $('.auction-products-container, .products').first();
				}
				
				// Get all items (including hidden ones from search/filter)
				var $items = $container.find('.auction-product-item, li.product');
				
				// Store current layout to preserve it
				var isListMode = $container.hasClass('is-list');
				var containerDisplay = $container.css('display');
				var containerFlexWrap = $container.css('flex-wrap');
				
				// Detach and sort by original index
				$items = $items.detach().sort(function(a, b) {
					var orderA = parseInt($(a).data('original-index'), 10);
					var orderB = parseInt($(b).data('original-index'), 10);
					// If original-index is not set, use a large number to put them at the end
					if (isNaN(orderA)) orderA = 999999;
					if (isNaN(orderB)) orderB = 999999;
					return orderA - orderB;
				});
				
				// Append items back in original order
				$container.append($items);
				
				// Preserve layout without changing styles
				if (!isListMode) {
					if (containerDisplay === 'flex' || containerDisplay === 'grid') {
						$container.css('display', containerDisplay);
					}
					if (containerFlexWrap && containerFlexWrap !== 'nowrap') {
						$container.css('flex-wrap', containerFlexWrap);
					}
				}
				
				// Re-apply filter to maintain search/filter state
				filterProducts();
			});

			function filterProducts() {
				// Support both search input IDs
				var $searchInput = $('#auction-search');
				if (!$searchInput.length) {
					$searchInput = $('#auction-search-input');
				}
				var searchTerm = ($searchInput.val() || '').toLowerCase().trim();
				
				// Support both category controls
				var categorySlug = $('#auction-category-filter').val() || '';
				if (!categorySlug) {
					categorySlug = $('#auction-category-select').val() || '';
				}
				
				var visible = 0;

				// Get container and ALL items in it (grid + shortcode cards)
				var $container = findProductsContainer();
				if (!$container || !$container.length) {
					$container = $('.auction-products-container, .products').first();
				}
				var $allItems = $container.find('.auction-product-item, li.product, .auction-product-card');

				$allItems.each(function() {
					var $item = $(this);
					
					// Get title from data attribute or fallback to element text
					var title = $item.attr('data-title');
					if (!title || title === '') {
						var $titleEl = $item.find('h2.woocommerce-loop-product__title, h2.product-title, h3, .auction-product-title, .product-title').first();
						if ($titleEl.length) {
							title = $titleEl.text();
						} else {
							title = $item.find('a.woocommerce-LoopProduct-link, a').first().text() || '';
						}
					}
					title = (title ? String(title) : '').toLowerCase().trim();
					
					// Categories
					var itemCategories = [];
					var dataCategory = $item.attr('data-category') || '';
					if (dataCategory) {
						itemCategories = dataCategory.trim().split(/\s+/);
					}
					var dataCategorySlug = $item.attr('data-category-slug') || '';
					if (dataCategorySlug && itemCategories.indexOf(dataCategorySlug) === -1) {
						itemCategories.push(dataCategorySlug);
					}
					if (itemCategories.length === 0) {
						var classes = $item.attr('class') || '';
						var catMatches = classes.match(/product_cat-([^\s]+)/g);
						if (catMatches) {
							catMatches.forEach(function(match) {
								var slug = match.replace('product_cat-', '');
								if (itemCategories.indexOf(slug) === -1) {
									itemCategories.push(slug);
								}
							});
						}
					}

					// Match logic
					var matchesSearch = true;
					if (searchTerm) {
						matchesSearch = (title && title.indexOf(searchTerm) !== -1);
					}
					var matchesCategory = !categorySlug || itemCategories.indexOf(categorySlug) !== -1;

					// Show / hide
					if (matchesSearch && matchesCategory) {
						$item.css('display', '');
						visible++;
					} else {
						$item.css('display', 'none');
					}
				});

				// IMPORTANT: do NOT re-sort inside filter – this was causing items to reappear.
				// Search should ONLY control visibility, sort controls order.
				updateResultsCount(visible);
			}

			function sortProducts() {
				if (!currentSort || !currentSort.field) {
					return;
				}

				var $container = findProductsContainer();
				if (!$container || !$container.length) {
					$container = $('.auction-products-container, .products').first();
				}
				
				// Preserve current layout class and container state
				var isListMode = $container.hasClass('is-list');
				var currentLayout = isListMode ? 'is-list' : 'is-grid';
				
				// Store container's original display style to preserve grid layout
				var containerDisplay = $container.css('display');
				var containerFlexWrap = $container.css('flex-wrap');
				
				// Only sort visible items (those that passed the search/filter)
				var $items = $container.find('.auction-product-item:visible, li.product:visible');
				
				// If no visible items, don't sort (this can happen if search filters everything out)
				if ($items.length === 0) {
					return;
				}
				
				// Sort items array
				var sortedItems = $items.toArray().sort(function(a, b) {
					var $a = $(a), $b = $(b);
					var valA, valB;

					switch(currentSort.field) {
						case 'bid-count':
							valA = parseInt($a.attr('data-bid-count') || 0, 10);
							valB = parseInt($b.attr('data-bid-count') || 0, 10);
							break;
						case 'end-date':
							valA = parseInt($a.attr('data-end-timestamp') || 0, 10);
							valB = parseInt($b.attr('data-end-timestamp') || 0, 10);
							break;
						case 'title':
							valA = ($a.attr('data-title') || $a.find('h2.woocommerce-loop-product__title, h2.product-title, h3').text() || '').toLowerCase().trim();
							valB = ($b.attr('data-title') || $b.find('h2.woocommerce-loop-product__title, h2.product-title, h3').text() || '').toLowerCase().trim();
							break;
						case 'lot-number':
							// Try to parse as number first
							valA = parseInt($a.attr('data-lot-number') || 0, 10);
							valB = parseInt($b.attr('data-lot-number') || 0, 10);
							// If not a number, compare as string
							if (isNaN(valA) || isNaN(valB)) {
								valA = String($a.attr('data-lot-number') || '');
								valB = String($b.attr('data-lot-number') || '');
							}
							break;
						case 'current-bid':
							valA = parseFloat($a.attr('data-current-bid') || 0);
							valB = parseFloat($b.attr('data-current-bid') || 0);
							break;
						default:
							return 0;
					}

					if (typeof valA === 'string' && typeof valB === 'string') {
						return currentSort.direction === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
					} else {
						return currentSort.direction === 'asc' ? valA - valB : valB - valA;
					}
				});

				// Detach all items first
				$items.detach();
				
				// Clear any inline styles on items that might interfere with grid layout
				$.each(sortedItems, function(index, item) {
					var $item = $(item);
					// Remove inline styles that could break grid layout
					$item.attr('style', '');
					// Ensure no block-level display that would cause stacking
					if (!$item.hasClass('is-list')) {
						$item.css({
							'display': '',
							'float': '',
							'width': '',
							'max-width': '',
							'flex-direction': '',
							'clear': ''
						});
					}
				});
				
				// Append sorted items back in correct order
				$.each(sortedItems, function(index, item) {
					$container.append(item);
				});
				
				// Re-apply layout class to ensure grid layout is maintained
				$container.removeClass('is-grid is-list').addClass(currentLayout);
				
				// If in grid mode, ensure container maintains flex/grid display for side-by-side layout
				if (!isListMode) {
					// Force container to maintain grid/flex layout - critical for side-by-side display
					// WooCommerce typically uses flex or grid for product lists
					if (containerDisplay === 'flex' || containerDisplay === 'grid' || !containerDisplay || containerDisplay === 'block') {
						// If it was flex or grid, restore it. If block or empty, try flex (WooCommerce default)
						var targetDisplay = (containerDisplay === 'flex' || containerDisplay === 'grid') ? containerDisplay : 'flex';
						$container.css({
							'display': targetDisplay,
							'flex-wrap': containerFlexWrap || 'wrap',
							'flex-direction': 'row'
						});
					}
					
					// Aggressively remove any styles from product items that would cause vertical stacking
					$container.find('li.product, .auction-product-item').each(function() {
						var $item = $(this);
						// Don't override if it's in list mode
						if (!$item.closest('.is-list').length) {
							// Remove ALL inline styles that could break grid layout
							$item.css({
								'display': '',
								'float': '',
								'width': '',
								'max-width': '',
								'flex-direction': '',
								'clear': '',
								'margin': '',
								'position': ''
							});
							
							// Ensure item doesn't have block display without float (which causes stacking)
							var itemDisplay = window.getComputedStyle($item[0]).display;
							if (itemDisplay === 'block' && !$item.css('float') && !$item.css('position')) {
								$item.css('display', '');
							}
						}
					});
					
					// Ensure container doesn't have flex-direction: column which would stack items
					if ($container.css('flex-direction') === 'column') {
						$container.css('flex-direction', 'row');
					}
				}
				
				// Force browser reflow to ensure layout recalculates correctly
				void $container[0].offsetWidth;
				
				// Double-check after reflow - if items are still stacking, force flex layout
				if (!isListMode) {
					setTimeout(function() {
						var firstItem = $container.find('li.product, .auction-product-item').first();
						if (firstItem.length) {
							var itemTop = firstItem.offset().top;
							var secondItem = $container.find('li.product, .auction-product-item').eq(1);
							if (secondItem.length) {
								var secondTop = secondItem.offset().top;
								// If second item is below first (not side by side), force flex layout
								if (secondTop > itemTop + 50) {
									$container.css({
										'display': 'flex',
										'flex-wrap': 'wrap',
										'flex-direction': 'row'
									});
									void $container[0].offsetWidth;
								}
							}
						}
					}, 10);
				}
				
				updateResultsCount();
			}

			function updateResultsCount(visible) {
				var total = $('.auction-product-item, .products li.product').length;
				var shown = visible !== undefined ? visible : $('.auction-product-item:visible, .products li.product:visible').length;
				$('#auction-results-count').text(shown + ' of ' + total + ' results');
			}

			// Initialize function
			function initializeAuctionListing() {
				// Wait for products to be in DOM
				var attempts = 0;
				var maxAttempts = 10;
				
				function tryInit() {
					var $products = $('.products li.product');
					
					if ($products.length > 0 || attempts >= maxAttempts) {
						// Products found or max attempts reached
						addProductDataAttributes();
						
						// Store original indices for reset - do this immediately when products are found
						var $container = findProductsContainer();
						if (!$container || !$container.length) {
							$container = $('.auction-products-container, .products').first();
						}
						
						$container.find('.auction-product-item, li.product').each(function(index) {
							$(this).data('original-index', index);
						});
						
						updateResultsCount();
						
						// Ensure container has proper classes
						var $container = $('.auction-products-container, .products').first();
						if ($container.length) {
							if (!$container.hasClass('auction-products-container')) {
								$container.addClass('auction-products-container');
							}
							if (!$container.hasClass('grid-view') && !$container.hasClass('list-view')) {
								$container.addClass('grid-view');
							}
						}
						
						// Ensure products visibility matches active tab
						if (typeof toggleProductsVisibility === 'function') {
							toggleProductsVisibility();
						}
					} else {
						// Products not loaded yet, try again
						attempts++;
						setTimeout(tryInit, 200);
					}
				}
				
				tryInit();
			}

			// Initialize on document ready
			$(document).ready(function() {
				// Set initial products visibility
				toggleProductsVisibility();
				// Initialize auction listing
				initializeAuctionListing();
			});

			// Also run after AJAX loads (if using AJAX pagination)
			$(document).on('updated_wc_div', function() {
				setTimeout(function() {
					initializeAuctionListing();
				}, 300);
			});
			
			// Watch for new products added to DOM
			if (typeof MutationObserver !== 'undefined') {
				var observer = new MutationObserver(function(mutations) {
					var shouldReinit = false;
					mutations.forEach(function(mutation) {
						if (mutation.addedNodes.length) {
							Array.prototype.forEach.call(mutation.addedNodes, function(node) {
								if (node.nodeType === 1 && (node.classList.contains('product') || node.querySelector('.product'))) {
									shouldReinit = true;
								}
							});
						}
					});
					if (shouldReinit) {
						setTimeout(initializeAuctionListing, 100);
					}
				});
				
				$(document).ready(function() {
					var $container = $('.products, .auction-products-container').first();
					if ($container.length) {
						observer.observe($container[0], { childList: true, subtree: true });
					}
				});
			}
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Wrap auction products container start.
	 *
	 * @param string $html Original HTML.
	 * @return string
	 */
	public function wrap_auction_products_start( string $html ): string {
		if ( ! $this->is_auction_listing_page() ) {
			return $html;
		}

		// Add class to existing products container
		return str_replace( 'class="products', 'class="products auction-products-container', $html );
	}

	/**
	 * Wrap auction products container end.
	 *
	 * @param string $html Original HTML.
	 * @return string
	 */
	public function wrap_auction_products_end( string $html ): string {
		if ( ! $this->is_auction_listing_page() ) {
			return $html;
		}

		return $html;
	}

}

