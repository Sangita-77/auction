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
}

