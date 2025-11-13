<?php
/**
 * Handles scheduled auction events.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * Processes auction completions and notifications.
 */
class Auction_Event_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var Auction_Event_Manager|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'auction_check_ending_events', array( $this, 'process_ending_auctions' ) );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Auction_Event_Manager
	 */
	public static function instance(): Auction_Event_Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Process auctions that have reached their end date.
	 *
	 * @return void
	 */
	public function process_ending_auctions(): void {
		Auction_Install::ensure_tables();

		$current_mysql = current_time( 'mysql' );

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 25,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_auction_enabled',
						'value' => 'yes',
					),
					array(
						'key'     => '_auction_end_time',
						'value'   => $current_mysql,
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => '_auction_processed',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_auction_processed',
							'value'   => 'yes',
							'compare' => '!=',
						),
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return;
		}

		while ( $query->have_posts() ) {
			$query->the_post();

			$product = wc_get_product( get_the_ID() );

			if ( ! $product ) {
				continue;
			}

			$config = Auction_Product_Helper::get_config( $product );
			$status = Auction_Product_Helper::get_auction_status( $config );

			if ( 'ended' !== $status ) {
				$this->mark_as_processed( $product, 'no_winner' );
				continue;
			}

			$leading_bid = Auction_Bid_Manager::get_leading_bid( $product->get_id() );

			if ( ! $leading_bid ) {
				$this->mark_as_processed( $product, 'no_winner' );
				continue;
			}

			$winning_amount = Auction_Product_Helper::to_float( $leading_bid['bid_amount'] ?? 0 );
			$reserve_price  = Auction_Product_Helper::to_float( $config['reserve_price'] ?? 0 );

			if ( $reserve_price > 0 && $winning_amount < $reserve_price ) {
				$this->mark_as_processed( $product, 'reserve_not_met' );
				continue;
			}

			$this->finalize_winner( $product, $leading_bid, $winning_amount, $config );
		}

		wp_reset_postdata();
	}

	/**
	 * Mark auction as processed without a winner.
	 *
	 * @param WC_Product $product Product instance.
	 * @param string     $reason  Reason code.
	 *
	 * @return void
	 */
	private function mark_as_processed( WC_Product $product, string $reason ): void {
		$product->update_meta_data( '_auction_processed', 'yes' );
		$product->update_meta_data( '_auction_status_flag', $reason );
		$product->save_meta_data();
	}

	/**
	 * Finalize auction winner.
	 *
	 * @param WC_Product $product        Product.
	 * @param array      $bid            Winning bid data.
	 * @param float      $winning_amount Winning amount.
	 * @param array      $config         Auction configuration.
	 *
	 * @return void
	 */
	private function finalize_winner( WC_Product $product, array $bid, float $winning_amount, array $config ): void {
		$winner_user_id = absint( $bid['user_id'] ?? 0 );

		$winner_name = $this->format_bidder_name( $bid, $config );
		$winner_time = ! empty( $bid['created_at'] )
			? $bid['created_at']
			: current_time( 'mysql' );

		$product->update_meta_data( '_auction_processed', 'yes' );
		$product->update_meta_data( '_auction_status_flag', 'winner_notified' );
		$product->update_meta_data( '_auction_winner_user_id', $winner_user_id );
		$product->update_meta_data( '_auction_winner_session_id', $bid['session_id'] ?? '' );
		$product->update_meta_data( '_auction_winner_name', $winner_name );
		$product->update_meta_data( '_auction_winner_amount', $winning_amount );
		$product->update_meta_data( '_auction_winner_time', $winner_time );
		$product->update_meta_data( '_auction_winning_bid_id', absint( $bid['id'] ?? 0 ) );
		$product->save_meta_data();

		if ( $winner_user_id ) {
			$user = get_user_by( 'id', $winner_user_id );
			if ( $user && $user->user_email ) {
				$this->send_winner_email( $user, $product, $winning_amount, $winner_time );
			}
		}
	}

	/**
	 * Send winner email.
	 *
	 * @param WP_User    $user           Winner user object.
	 * @param WC_Product $product        Product.
	 * @param float      $winning_amount Winning amount.
	 * @param string     $winner_time    Winning time.
	 *
	 * @return void
	 */
	private function send_winner_email( WP_User $user, WC_Product $product, float $winning_amount, string $winner_time ): void {
		$subject = sprintf(
			/* translators: %s: product title */
			__( 'You won the auction for %s', 'auction' ),
			$product->get_name()
		);

		$body  = sprintf( __( 'Hi %s,', 'auction' ), $user->display_name ?: $user->user_login ) . "\n\n";
		$body .= sprintf(
			/* translators: %1$s product title, %2$s amount */
			__( 'Congratulations! You won the auction for %1$s with a winning bid of %2$s.', 'auction' ),
			$product->get_name(),
			wc_price( $winning_amount )
		) . "\n";

		$body .= sprintf(
			/* translators: %s: winning time */
			__( 'Winning bid time: %s', 'auction' ),
			wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $winner_time )
			)
		) . "\n\n";

		$body .= __( 'You can review the auction from your account page.', 'auction' ) . "\n";
		$body .= esc_url( wc_get_page_permalink( 'myaccount' ) ) . "\n\n";
		$body .= __( 'Thanks for participating!', 'auction' );

		wp_mail( $user->user_email, $subject, $body );
	}

	/**
	 * Format bidder name for notifications.
	 *
	 * @param array $record Bid record.
	 * @param array $config Auction config.
	 *
	 * @return string
	 */
	private function format_bidder_name( array $record, array $config ): string {
		if ( 'yes' === ( $config['sealed'] ?? 'no' ) ) {
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
}

