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

		$user_id = get_current_user_id();

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_auction_winner_user_id',
						'value' => $user_id,
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			echo '<p>' . esc_html__( 'You have not won any auctions yet.', 'auction' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo '<table class="shop_table shop_table_responsive">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Product', 'auction' ) . '</th>';
		echo '<th>' . esc_html__( 'Winning bid', 'auction' ) . '</th>';
		echo '<th>' . esc_html__( 'Winning time', 'auction' ) . '</th>';
		echo '</tr></thead><tbody>';

		while ( $query->have_posts() ) {
			$query->the_post();
			$product = wc_get_product( get_the_ID() );

			if ( ! $product ) {
				continue;
			}

			$amount = Auction_Product_Helper::to_float( $product->get_meta( '_auction_winner_amount', true ) );
			$time   = $product->get_meta( '_auction_winner_time', true );

			echo '<tr>';
			echo '<td><a href="' . esc_url( get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></td>';
			echo '<td>' . wp_kses_post( wc_price( $amount ) ) . '</td>';
			echo '<td>' . esc_html(
				$time
					? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $time ) )
					: __( 'N/A', 'auction' )
			) . '</td>';
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
}

