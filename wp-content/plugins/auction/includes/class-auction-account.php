<?php
/**
 * Adds My Account integrations for auctions.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles My Account menu and endpoint.
 */
class Auction_Account {

	/**
	 * Singleton instance.
	 *
	 * @var Auction_Account|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_auction-wins_endpoint', array( $this, 'render_endpoint' ) );
		add_action( 'woocommerce_account_auction-watchlist_endpoint', array( $this, 'render_watchlist_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_pay_now' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_auction_winning_bid_price' ), 10, 1 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_auction_info_to_order_item' ), 10, 4 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'display_auction_bid_label_on_checkout' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'display_auction_bid_label_on_checkout' ), 10, 3 );
		// As a final safeguard, also override the generic price HTML for auction items in cart/checkout.
		add_filter( 'woocommerce_get_price_html', array( $this, 'override_price_html_with_winning_bid' ), 99, 2 );
		add_filter( 'woocommerce_product_get_price', array( $this, 'get_auction_winning_bid_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'get_auction_winning_bid_price' ), 99, 2 );
		add_filter( 'woocommerce_cart_product_subtotal', array( $this, 'override_product_subtotal_with_winning_bid' ), 99, 4 );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Auction_Account
	 */
	public static function instance(): Auction_Account {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register account endpoint.
	 *
	 * @return void
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( 'auction-wins', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'auction-watchlist', EP_ROOT | EP_PAGES );
	}

	/**
	 * Register query variable.
	 *
	 * @param array $vars Query vars.
	 *
	 * @return array
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = 'auction-wins';
		$vars[] = 'auction-watchlist';

		return $vars;
	}

	/**
	 * Add menu item to My Account.
	 *
	 * @param array $items Menu items.
	 *
	 * @return array
	 */
	public function add_menu_item( array $items ): array {
		$new_items = array();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( 'orders' === $key ) {
				$new_items['auction-wins'] = __( 'My Auctions', 'auction' );
			}

			if ( 'auction-wins' === $key && Auction_Settings::is_enabled( 'enable_watchlist' ) ) {
				$new_items['auction-watchlist'] = __( 'Watchlist', 'auction' );
			}
		}

		if ( ! isset( $new_items['auction-wins'] ) ) {
			$new_items['auction-wins'] = __( 'My Auctions', 'auction' );
		}

		if ( Auction_Settings::is_enabled( 'enable_watchlist' ) && ! isset( $new_items['auction-watchlist'] ) ) {
			$new_items['auction-watchlist'] = __( 'Watchlist', 'auction' );
		}

		return $new_items;
	}

	/**
	 * Render endpoint content.
	 *
	 * @return void
	 */
	public function render_endpoint(): void {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'You need to be logged in to view this page.', 'auction' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$user_id        = get_current_user_id();
		$participated   = $this->get_user_participated_product_ids( $user_id );
		$query_args     = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'post__in',
		);

		if ( empty( $participated ) ) {
			echo '<p>' . esc_html__( 'You have not participated in any auctions yet.', 'auction' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$query_args['post__in'] = $participated;

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			echo '<p>' . esc_html__( 'You have not participated in any auctions yet.', 'auction' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$latest_bids = $this->get_user_latest_bids( $user_id );

		echo '<table class="shop_table shop_table_responsive">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Product', 'auction' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'auction' ) . '</th>';
		echo '<th>' . esc_html__( 'Your last bid', 'auction' ) . '</th>';
		echo '<th>' . esc_html__( 'Updated', 'auction' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'auction' ) . '</th>';
		echo '</tr></thead><tbody>';

		while ( $query->have_posts() ) {
			$query->the_post();
			$product = wc_get_product( get_the_ID() );

			if ( ! $product ) {
				continue;
			}

			$config      = Auction_Product_Helper::get_config( $product );
			$status      = Auction_Product_Helper::get_auction_status( $config );
			$status_text = '';

			switch ( $status ) {
				case 'scheduled':
					$status_text = __( 'Upcoming', 'auction' );
					break;
				case 'ended':
					$winner = absint( $product->get_meta( '_auction_winner_user_id', true ) );
					$status_text = $winner === $user_id ? __( 'Won', 'auction' ) : __( 'Ended', 'auction' );
					break;
				default:
					$status_text = __( 'Active', 'auction' );
					break;
			}

			$latest_bid = $latest_bids[ $product->get_id() ] ?? null;
			$is_won = false;
			$has_order = false;

			// Check if user won this auction
			if ( 'ended' === $status ) {
				$winner = absint( $product->get_meta( '_auction_winner_user_id', true ) );
				$is_won = ( $winner === $user_id );
				
				// Check if order already exists for this won auction
				if ( $is_won ) {
					$has_order = $this->has_order_for_product( $user_id, $product->get_id() );
				}
			}

			echo '<tr>';
			echo '<td><a href="' . esc_url( get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></td>';
			echo '<td>' . esc_html( $status_text ) . '</td>';

			if ( $latest_bid ) {
				echo '<td>' . wp_kses_post( wc_price( $latest_bid['bid_amount'] ) ) . '</td>';
				echo '<td>' . esc_html(
					wp_date(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						strtotime( $latest_bid['created_at'] )
					)
				) . '</td>';
			} else {
				echo '<td>' . esc_html__( 'N/A', 'auction' ) . '</td>';
				echo '<td>' . esc_html__( 'N/A', 'auction' ) . '</td>';
			}

			// Actions column
			echo '<td>';
			if ( $is_won && ! $has_order ) {
				$pay_now_url = add_query_arg(
					array(
						'auction_pay_now' => $product->get_id(),
						'_wpnonce'        => wp_create_nonce( 'auction_pay_now_' . $product->get_id() ),
					),
					wc_get_account_endpoint_url( 'auction-wins' )
				);
				echo '<a href="' . esc_url( $pay_now_url ) . '" class="button pay-now-button">' . esc_html__( 'Pay Now', 'auction' ) . '</a>';
			} elseif ( $is_won && $has_order ) {
				echo '<span class="paid-badge">' . esc_html__( 'Paid', 'auction' ) . '</span>';
			} else {
				echo '—';
			}
			echo '</td>';

			echo '</tr>';
		}

		echo '</tbody></table>';

		wp_reset_postdata();
	}

	/**
	 * Render watchlist endpoint content.
	 *
	 * @return void
	 */
	public function render_watchlist_endpoint(): void {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'You need to be logged in to view this page.', 'auction' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		if ( ! Auction_Settings::is_enabled( 'enable_watchlist' ) ) {
			echo '<p>' . esc_html__( 'Watchlists are currently disabled.', 'auction' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo do_shortcode( '[auction_watchlist]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Retrieve product IDs where the user has placed bids.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	private function get_user_participated_product_ids( int $user_id ): array {
		if ( ! $user_id ) {
			return array();
		}

		global $wpdb;

		$table_name = Auction_Install::get_bids_table_name();
		$results    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT product_id FROM {$table_name} WHERE user_id = %d ORDER BY product_id DESC",
				$user_id
			)
		);

		return array_map( 'absint', $results ?: array() );
	}

	/**
	 * Fetch the latest bid details for each product placed by the user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	private function get_user_latest_bids( int $user_id ): array {
		if ( ! $user_id ) {
			return array();
		}

		global $wpdb;

		$table_name = Auction_Install::get_bids_table_name();
		$rows       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, bid_amount, created_at FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			),
			ARRAY_A
		);

		$latest = array();

		foreach ( $rows as $row ) {
			$product_id = absint( $row['product_id'] );

			if ( $product_id && ! isset( $latest[ $product_id ] ) ) {
				$latest[ $product_id ] = array(
					'bid_amount' => Auction_Product_Helper::to_float( $row['bid_amount'] ),
					'created_at' => $row['created_at'],
				);
			}
		}

		return $latest;
	}

	/**
	 * Check if user has an order for a specific product.
	 *
	 * @param int $user_id    User ID.
	 * @param int $product_id Product ID.
	 *
	 * @return bool
	 */
	private function has_order_for_product( int $user_id, int $product_id ): bool {
		if ( ! $user_id || ! $product_id ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		// Check if user has bought this product
		return wc_customer_bought_product( $user->user_email, $user_id, $product_id );
	}

	/**
	 * Handle Pay Now action - add product to cart and redirect to checkout.
	 *
	 * @return void
	 */
	public function handle_pay_now(): void {
		// Check if we're on the account page and pay now action is requested
		if ( ! is_account_page() || ! isset( $_GET['auction_pay_now'] ) ) {
			return;
		}

		// Verify nonce
		$product_id = absint( $_GET['auction_pay_now'] );
		if ( ! $product_id || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'auction_pay_now_' . $product_id ) ) {
			wc_add_notice( __( 'Security check failed. Please try again.', 'auction' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'auction-wins' ) );
			exit;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wc_add_notice( __( 'You must be logged in to pay for won auctions.', 'auction' ), 'error' );
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		$user_id = get_current_user_id();
		$product = wc_get_product( $product_id );

		// Validate product
		if ( ! $product ) {
			wc_add_notice( __( 'Product not found.', 'auction' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'auction-wins' ) );
			exit;
		}

		// Check if it's an auction product
		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {
			wc_add_notice( __( 'This is not an auction product.', 'auction' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'auction-wins' ) );
			exit;
		}

		// Check if user won this auction
		$config = Auction_Product_Helper::get_config( $product );
		$status = Auction_Product_Helper::get_auction_status( $config );
		
		if ( 'ended' !== $status ) {
			wc_add_notice( __( 'This auction has not ended yet.', 'auction' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'auction-wins' ) );
			exit;
		}

		$winner = absint( $product->get_meta( '_auction_winner_user_id', true ) );
		if ( $winner !== $user_id ) {
			wc_add_notice( __( 'You did not win this auction.', 'auction' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'auction-wins' ) );
			exit;
		}

		// Check if order already exists
		if ( $this->has_order_for_product( $user_id, $product_id ) ) {
			wc_add_notice( __( 'You have already paid for this auction.', 'auction' ), 'notice' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'auction-wins' ) );
			exit;
		}

		// Get winning bid amount. To make sure this matches the "Your last bid" shown in My Auctions,
		// we look up the latest bid placed by THIS user for THIS product in the bids table.
		$winning_bid_amount = 0;

		global $wpdb;
		$table_name = Auction_Install::get_bids_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT bid_amount FROM {$table_name}
				 WHERE product_id = %d AND user_id = %d
				 ORDER BY created_at DESC
				 LIMIT 1",
				$product->get_id(),
				$user_id
			),
			ARRAY_A
		);

		if ( $row && isset( $row['bid_amount'] ) ) {
			$winning_bid_amount = Auction_Product_Helper::to_float( $row['bid_amount'] );
		}

		// Fallbacks if, for some reason, no row is found.
		if ( $winning_bid_amount <= 0 ) {
			$state = Auction_Product_Helper::get_runtime_state( $product );
			if ( isset( $state['current_bid'] ) ) {
				$winning_bid_amount = Auction_Product_Helper::to_float( $state['current_bid'] );
			} else {
				$winning_bid_amount = Auction_Product_Helper::get_start_price( $config );
			}
		}
		
		if ( $winning_bid_amount <= 0 ) {
			wc_add_notice( __( 'Invalid bid amount. Please contact support.', 'auction' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'auction-wins' ) );
			exit;
		}

		// At this point we KNOW the winning amount and user are correct.
		// Instead of relying on cart/checkout templates (which may show sale price),
		// we create a dedicated WooCommerce order for this auction at the winning amount
		// and redirect the user to the secure "Pay for order" page.

		// Create new order for this user.
		$order = wc_create_order(
			array(
				'customer_id' => $user_id,
			)
		);

		if ( is_wp_error( $order ) ) {
			wc_add_notice( __( 'Could not create order for this auction. Please contact support.', 'auction' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'auction-wins' ) );
			exit;
		}

		// Add the auction product as a line item with the winning bid amount.
		$order->add_product(
			$product,
			1,
			array(
				'totals' => array(
					'subtotal' => $winning_bid_amount,
					'total'    => $winning_bid_amount,
				),
			)
		);

		// Mark this as an auction payment and record metadata.
		$order->update_meta_data( '_is_auction_payment', 'yes' );
		$order->update_meta_data( '_auction_product_id', $product_id );
		$order->update_meta_data( '_auction_winning_bid', $winning_bid_amount );

		// Let WooCommerce calculate taxes/shipping if applicable.
		$order->calculate_totals();

		// Ensure pending payment status and save.
		$order->set_status( 'pending' );
		$order->save();

		// Redirect user to the "Pay for order" page where they can enter billing details and pay.
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	/**
	 * Set custom price for auction won products in cart.
	 *
	 * @param WC_Cart $cart Cart object.
	 *
	 * @return void
	 */
	public function set_auction_winning_bid_price( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Don't run multiple times
		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['auction_winning_bid'] ) ) {
				$winning_bid = floatval( $cart_item['auction_winning_bid'] );
				
				// Set price on the product object
				$cart->cart_contents[ $cart_item_key ]['data']->set_price( $winning_bid );
				
				// Also set the line subtotal and total directly
				$quantity = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
				$line_subtotal = $winning_bid * $quantity;
				
				// Calculate tax if product is taxable
				if ( $cart_item['data']->is_taxable() ) {
					$tax_rates = WC_Tax::get_rates( $cart_item['data']->get_tax_class() );
					$taxes = WC_Tax::calc_tax( $line_subtotal, $tax_rates, wc_prices_include_tax() );
					$tax_amount = array_sum( $taxes );
					
					if ( wc_prices_include_tax() ) {
						$line_subtotal = $line_subtotal - $tax_amount;
					}
					
					$cart->cart_contents[ $cart_item_key ]['line_subtotal'] = $line_subtotal;
					$cart->cart_contents[ $cart_item_key ]['line_total'] = $line_subtotal;
					$cart->cart_contents[ $cart_item_key ]['line_subtotal_tax'] = $tax_amount;
					$cart->cart_contents[ $cart_item_key ]['line_tax'] = $tax_amount;
					$cart->cart_contents[ $cart_item_key ]['line_tax_data'] = array(
						'subtotal' => $taxes,
						'total' => $taxes,
					);
				} else {
					$cart->cart_contents[ $cart_item_key ]['line_subtotal'] = $line_subtotal;
					$cart->cart_contents[ $cart_item_key ]['line_total'] = $line_subtotal;
					$cart->cart_contents[ $cart_item_key ]['line_subtotal_tax'] = 0;
					$cart->cart_contents[ $cart_item_key ]['line_tax'] = 0;
					$cart->cart_contents[ $cart_item_key ]['line_tax_data'] = array(
						'subtotal' => array(),
						'total' => array(),
					);
				}
			}
		}
	}

	/**
	 * Save auction information to order item.
	 *
	 * @param WC_Order_Item_Product $item Order item object.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values Cart item values.
	 * @param WC_Order              $order Order object.
	 *
	 * @return void
	 */
	public function save_auction_info_to_order_item( $item, $cart_item_key, $values, $order ): void {
		if ( isset( $values['auction_winning_bid'] ) && isset( $values['auction_product_id'] ) ) {
			$item->add_meta_data( '_auction_winning_bid', floatval( $values['auction_winning_bid'] ) );
			$item->add_meta_data( '_auction_product_id', absint( $values['auction_product_id'] ) );
			$item->add_meta_data( '_is_auction_payment', 'yes' );
		}
	}

	/**
	 * Display "Your last bid (highest bid)" label on checkout page.
	 *
	 * @param string $price_html Price HTML.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 *
	 * @return string
	 */
	public function display_auction_bid_label_on_checkout( string $price_html, array $cart_item, string $cart_item_key ): string {
		// Check if this is an auction payment
		if ( isset( $cart_item['auction_winning_bid'] ) && isset( $cart_item['auction_product_id'] ) ) {
			$winning_bid = floatval( $cart_item['auction_winning_bid'] );
			$quantity = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
			$subtotal = $winning_bid * $quantity;
			
			// Get tax if applicable
			if ( isset( $cart_item['line_subtotal_tax'] ) && $cart_item['line_subtotal_tax'] > 0 ) {
				if ( WC()->cart->display_prices_including_tax() ) {
					$subtotal += $cart_item['line_subtotal_tax'];
				}
			}
			
			$formatted_price = wc_price( $subtotal );
			
			// Only add label on checkout page
			if ( is_checkout() ) {
				$label = __( 'Your last bid (highest bid)', 'auction' );
				return '<span class="auction-bid-label">' . esc_html( $label ) . ': </span>' . $formatted_price;
			}
			
			return $formatted_price;
		}

		return $price_html;
	}

	/**
	 * Get auction winning bid price for product.
	 *
	 * @param float       $price Product price.
	 * @param WC_Product  $product Product object.
	 *
	 * @return float
	 */
	public function get_auction_winning_bid_price( $price, $product ): float {
		// Only modify in cart context
		if ( ! WC()->cart || is_admin() ) {
			return $price;
		}

		// Check if this product is in cart with auction winning bid
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['data'] ) && $cart_item['data']->get_id() === $product->get_id() ) {
				if ( isset( $cart_item['auction_winning_bid'] ) ) {
					return floatval( $cart_item['auction_winning_bid'] );
				}
			}
		}

		return $price;
	}

	/**
	 * Override product subtotal with winning bid amount.
	 *
	 * @param string      $product_subtotal Formatted subtotal.
	 * @param WC_Product   $product Product object.
	 * @param int          $quantity Quantity.
	 * @param WC_Cart      $cart Cart object.
	 *
	 * @return string
	 */
	public function override_product_subtotal_with_winning_bid( string $product_subtotal, $product, int $quantity, $cart ): string {
		// Check if this product is in cart with auction winning bid
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['data'] ) && $cart_item['data']->get_id() === $product->get_id() ) {
				if ( isset( $cart_item['auction_winning_bid'] ) ) {
					$winning_bid = floatval( $cart_item['auction_winning_bid'] );
					$base_price = $winning_bid;
					
					// Calculate subtotal with tax if product is taxable
					if ( $product->is_taxable() ) {
						$tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
						$taxes = WC_Tax::calc_tax( $base_price * $quantity, $tax_rates, wc_prices_include_tax() );
						$tax_amount = array_sum( $taxes );
						
						if ( $cart->display_prices_including_tax() ) {
							$subtotal = ( $base_price * $quantity ) + $tax_amount;
							$formatted = wc_price( $subtotal );
							if ( ! wc_prices_include_tax() && $tax_amount > 0 ) {
								$formatted .= ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
							}
							return $formatted;
						} else {
							$subtotal = $base_price * $quantity;
							$formatted = wc_price( $subtotal );
							if ( wc_prices_include_tax() && $tax_amount > 0 ) {
								$formatted .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
							}
							return $formatted;
						}
					} else {
						$subtotal = $winning_bid * $quantity;
						return wc_price( $subtotal );
					}
				}
			}
		}

		return $product_subtotal;
	}

	/**
	 * Override generic price HTML with winning bid amount on cart/checkout for auction items.
	 *
	 * This is a safeguard for themes/templates that call wc_get_price_html()
	 * instead of the cart item filters when rendering prices.
	 *
	 * @param string      $price_html Original price HTML.
	 * @param WC_Product  $product    Product object.
	 *
	 * @return string
	 */
	public function override_price_html_with_winning_bid( string $price_html, WC_Product $product ): string {
		// Only adjust on the frontend cart/checkout context.
		if ( is_admin() || ! ( is_cart() || is_checkout() ) || ! WC()->cart ) {
			return $price_html;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['data'] ) && $cart_item['data']->get_id() === $product->get_id() ) {
				if ( isset( $cart_item['auction_winning_bid'] ) ) {
					$winning_bid = floatval( $cart_item['auction_winning_bid'] );

					// Format using WooCommerce helpers so currency/decimals are correct.
					return wc_price( $winning_bid );
				}
			}
		}

		return $price_html;
	}
}

