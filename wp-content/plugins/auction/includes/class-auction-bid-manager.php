<?php

/**

 * Bid management utilities.

 *

 * @package Auction

 */



defined( 'ABSPATH' ) || exit;



/**

 * Handles bid persistence and business logic.

 */

class Auction_Bid_Manager {



	/**

	 * Place a bid for a given product.

	 *

	 * @param array $args Bid arguments.

	 *

	 * @return array|WP_Error

	 */

	public static function place_bid( array $args ) {

		Auction_Install::ensure_tables();



		$defaults = array(

			'product_id'       => 0,

			'user_id'          => 0,

			'session_id'       => '',

			'bid_amount'       => 0,

			'is_auto'          => false,

			'max_auto_amount'  => null,

			'ip_address'       => '',

			'user_agent'       => '',

		);



		$args = wp_parse_args( $args, $defaults );



		$product_id = absint( $args['product_id'] );



		if ( ! $product_id ) {

			return new WP_Error( 'auction_invalid_product', __( 'Invalid product selected for auction.', 'auction' ) );

		}



		$product = wc_get_product( $product_id );



		if ( ! $product ) {

			return new WP_Error( 'auction_product_not_found', __( 'Product not found.', 'auction' ) );

		}



		if ( ! Auction_Product_Helper::is_auction_product( $product ) ) {

			return new WP_Error( 'auction_not_enabled', __( 'Auction is not enabled for this product.', 'auction' ) );

		}

		// Check if product is out of stock - prevent bidding on out of stock products
		if ( ! $product->is_in_stock() ) {
			return new WP_Error( 'auction_out_of_stock', __( 'This product is out of stock. Bidding is no longer available.', 'auction' ) );
		}

		$config = Auction_Product_Helper::get_config( $product );



		$status = Auction_Product_Helper::get_auction_status( $config );



		if ( 'scheduled' === $status ) {

			return new WP_Error( 'auction_not_started', __( 'This auction has not started yet.', 'auction' ) );

		}



		if ( 'ended' === $status ) {

			return new WP_Error( 'auction_ended', __( 'This auction has already ended.', 'auction' ) );

		}



		$user_id    = absint( $args['user_id'] );

		$session_id = $args['session_id'] ? sanitize_text_field( $args['session_id'] ) : self::generate_session_id();



		if ( ! $user_id && ! $session_id ) {

			return new WP_Error( 'auction_anonymous_disallowed', __( 'Unable to identify bidder.', 'auction' ) );

		}



		$bid_amount      = Auction_Product_Helper::to_float( $args['bid_amount'] );

		$is_auto_request = ! empty( $args['is_auto'] ) && $config['automatic_bidding'];



		if ( $is_auto_request ) {

			$max_auto = $args['max_auto_amount'];

			$max_auto = null === $max_auto ? null : Auction_Product_Helper::to_float( $max_auto );



			if ( null === $max_auto ) {

				return new WP_Error( 'auction_missing_auto_max', __( 'Please enter a maximum automatic bid amount.', 'auction' ) );

			}



			if ( $max_auto <= 0 ) {

				return new WP_Error( 'auction_invalid_auto_max', __( 'Automatic bid maximum must be greater than zero.', 'auction' ) );

			}



			if ( $max_auto < $bid_amount ) {

				return new WP_Error( 'auction_auto_max_small', __( 'Automatic bid maximum must be greater than or equal to your initial bid.', 'auction' ) );

			}

		} else {

			$max_auto = null;

		}



		$manual_increment = Auction_Product_Helper::get_manual_increment( $config );



		$state              = Auction_Product_Helper::get_runtime_state( $product );

		$current_bid_amount = $state['winning_bid_id'] ? $state['current_bid'] : 0;



		$start_price   = Auction_Product_Helper::get_start_price( $config );

		$minimum_first = max( $start_price, $manual_increment );



		$minimum_required = $state['winning_bid_id'] ? $current_bid_amount + $manual_increment : $minimum_first;

		$minimum_required = max( $minimum_required, $minimum_first );



		if ( $bid_amount < $minimum_required ) {

			return new WP_Error(

				'auction_bid_too_low',

				sprintf(

					/* translators: %s minimum bid amount */

					__( 'Your bid must be at least %s.', 'auction' ),

					wc_price( $minimum_required )

				)

			);

		}



		if ( $config['sealed'] && ! self::user_can_bid_sealed( $user_id, $session_id, $product_id ) ) {

			return new WP_Error( 'auction_sealed_limit', __( 'You have already placed a bid on this sealed auction.', 'auction' ) );

		}



		$existing_proxy_user_id = $state['proxy_user_id'];

		$existing_proxy_max     = $state['proxy_max'];

		$existing_proxy_bid_id  = $state['proxy_bid_id'];



		$now = current_time( 'mysql' );



		$insert_args = array(

			'product_id'      => $product_id,

			'user_id'         => $user_id ?: null,

			'session_id'      => $user_id ? null : $session_id,

			'bid_amount'      => $bid_amount,

			'max_auto_amount' => $is_auto_request ? $max_auto : null,

			'is_auto'         => $is_auto_request ? 1 : 0,

			'status'          => 'active',

			'ip_address'      => $args['ip_address'] ? sanitize_text_field( $args['ip_address'] ) : '',

			'user_agent'      => $args['user_agent'] ? sanitize_textarea_field( $args['user_agent'] ) : '',

			'created_at'      => $now,

			'updated_at'      => $now,

		);



		$bid_id = self::insert_bid( $insert_args );



		if ( is_wp_error( $bid_id ) ) {

			return $bid_id;

		}



		$result = array(

			'bid_id'         => $bid_id,

			'status'         => 'accepted',

			'current_bid'    => null,

			'was_outbid'     => false,

			'automatic_diff' => false,

		);



		// Handle scenarios with existing proxy auto bidder.

		if ( $existing_proxy_bid_id && $existing_proxy_max > 0 && $existing_proxy_user_id && $existing_proxy_user_id !== $user_id ) {

			$result = self::handle_existing_proxy(

				$product,

				$config,

				$state,

				$result,

				array(

					'bid_id'         => $bid_id,

					'user_id'        => $user_id,

					'session_id'     => $session_id,

					'bid_amount'     => $bid_amount,

					'is_auto'        => $is_auto_request,

					'max_auto'       => $max_auto,

					'minimum_needed' => $minimum_required,

				)

			);



			return $result;

		}



		if ( $is_auto_request ) {

			$result = self::handle_new_auto_bid(

				$product,

				$config,

				$state,

				$result,

				array(

					'bid_id'     => $bid_id,

					'user_id'    => $user_id,

					'session_id' => $session_id,

					'bid_amount' => $bid_amount,

					'max_auto'   => $max_auto,

				)

			);

		} else {

			$result = self::handle_new_manual_bid(

				$product,

				$config,

				$state,

				$result,

				array(

					'bid_id'     => $bid_id,

					'user_id'    => $user_id,

					'session_id' => $session_id,

					'bid_amount' => $bid_amount,

				)

			);

		}



		return $result;

	}



	/**

	 * Determine whether bidder can place multiple bids on sealed auction.

	 *

	 * @param int    $user_id    User ID.

	 * @param string $session_id Session identifier.

	 * @param int    $product_id Product ID.

	 *

	 * @return bool

	 */

	private static function user_can_bid_sealed( int $user_id, string $session_id, int $product_id ): bool {

		global $wpdb;



		$table = Auction_Install::get_bids_table_name();



		if ( $user_id ) {

			$existing = $wpdb->get_var(

				$wpdb->prepare(

					"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND user_id = %d AND status IN ('active','outbid')",

					$product_id,

					$user_id

				)

			);

		} else {

			$existing = $wpdb->get_var(

				$wpdb->prepare(

					"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND session_id = %s AND status IN ('active','outbid')",

					$product_id,

					$session_id

				)

			);

		}



		return ! $existing;

	}



	/**

	 * Handle new manual bid scenario.

	 *

	 * @param WC_Product $product Product.

	 * @param array      $config  Config.

	 * @param array      $state   State.

	 * @param array      $result  Result payload.

	 * @param array      $bid     Bid context.

	 *

	 * @return array

	 */

	private static function handle_new_manual_bid( WC_Product $product, array $config, array $state, array $result, array $bid ): array {

		$manual_increment = Auction_Product_Helper::get_manual_increment( $config );



		$current_bid_amount = $state['winning_bid_id'] ? $state['current_bid'] : 0;

		$previous_winner_id = $state['winning_bid_id'];



		$new_current = $bid['bid_amount'];



		if ( $previous_winner_id ) {

			$new_current = max( $current_bid_amount + $manual_increment, $new_current );

			self::mark_bid_outbid( $previous_winner_id );

		}



		self::update_runtime_state(

			$product,

			array(

				'current_bid'        => $new_current,

				'winning_bid_id'     => $bid['bid_id'],

				'winning_user_id'    => $bid['user_id'],

				'winning_session_id' => $bid['session_id'],

				'proxy_max'          => 0,

				'proxy_user_id'      => 0,

				'proxy_bid_id'       => 0,

			)

		);



		self::update_bid_row(

			$bid['bid_id'],

			array(

				'bid_amount' => $new_current,

				'status'     => 'active',

			)

		);



		$result['current_bid'] = $new_current;



		return $result;

	}



	/**

	 * Handle new automatic bid scenario when no existing proxy conflicts.

	 *

	 * @param WC_Product $product Product.

	 * @param array      $config  Config.

	 * @param array      $state   State.

	 * @param array      $result  Result.

	 * @param array      $bid     Bid payload.

	 *

	 * @return array

	 */

	private static function handle_new_auto_bid( WC_Product $product, array $config, array $state, array $result, array $bid ): array {

		$manual_increment = Auction_Product_Helper::get_manual_increment( $config );



		$current_bid_amount = $state['winning_bid_id'] ? $state['current_bid'] : 0;

		$previous_winner_id = $state['winning_bid_id'];



		if ( $previous_winner_id ) {

			$new_current = max( $current_bid_amount + $manual_increment, $bid['bid_amount'] );

			self::mark_bid_outbid( $previous_winner_id );

		} else {

			$start_price = Auction_Product_Helper::get_start_price( $config );

			$new_current = max( $start_price, $bid['bid_amount'] );

		}



		self::update_runtime_state(

			$product,

			array(

				'current_bid'        => min( $new_current, $bid['max_auto'] ),

				'winning_bid_id'     => $bid['bid_id'],

				'winning_user_id'    => $bid['user_id'],

				'winning_session_id' => $bid['session_id'],

				'proxy_max'          => $bid['max_auto'],

				'proxy_user_id'      => $bid['user_id'],

				'proxy_bid_id'       => $bid['bid_id'],

			)

		);



		self::update_bid_row(

			$bid['bid_id'],

			array(

				'bid_amount'      => min( $new_current, $bid['max_auto'] ),

				'max_auto_amount' => $bid['max_auto'],

				'status'          => 'active',

			)

		);



		$result['current_bid'] = min( $new_current, $bid['max_auto'] );



		return $result;

	}



	/**

	 * Handle scenario when an existing proxy is present.

	 *

	 * @param WC_Product $product Product.

	 * @param array      $config  Config.

	 * @param array      $state   State.

	 * @param array      $result  Result payload.

	 * @param array      $bid     Incoming bid context.

	 *

	 * @return array

	 */

	private static function handle_existing_proxy( WC_Product $product, array $config, array $state, array $result, array $bid ): array {

		$manual_increment = Auction_Product_Helper::get_manual_increment( $config );

		$auto_increment   = Auction_Product_Helper::get_automatic_increment( $config, $state['current_bid'] );



		$proxy_max     = $state['proxy_max'];

		$proxy_bid_id  = $state['proxy_bid_id'];

		$proxy_user_id = $state['proxy_user_id'];



		// Bidder increasing their own proxy maximum.

		if ( $bid['is_auto'] && $proxy_user_id === $bid['user_id'] ) {

			$new_max = max( $proxy_max, $bid['max_auto'] );

			$new_bid = max( $state['current_bid'], min( $new_max, $bid['bid_amount'] ) );



			self::update_runtime_state(

				$product,

				array(

					'current_bid'    => $new_bid,

					'proxy_max'      => $new_max,

					'proxy_bid_id'   => $proxy_bid_id,

					'proxy_user_id'  => $proxy_user_id,

					'winning_bid_id' => $proxy_bid_id,

				)

			);



			self::update_bid_row(

				$proxy_bid_id,

				array(

					'bid_amount'      => $new_bid,

					'max_auto_amount' => $new_max,

					'status'          => 'active',

				)

			);



			self::mark_bid_outbid( $bid['bid_id'] );



			$result['status']         = 'proxy_updated';

			$result['was_outbid']     = true;

			$result['automatic_diff'] = true;

			$result['current_bid']    = $new_bid;



			return $result;

		}



		// Manual bid loses against existing proxy.

		if ( ! $bid['is_auto'] && $bid['bid_amount'] <= $proxy_max ) {

			$new_proxy_bid = min( $proxy_max, $bid['bid_amount'] + max( $manual_increment, $auto_increment ) );

			$new_proxy_bid = max( $state['current_bid'] + $manual_increment, $new_proxy_bid );

			$new_proxy_bid = min( $new_proxy_bid, $proxy_max );



			self::update_runtime_state(

				$product,

				array(

					'current_bid'    => $new_proxy_bid,

					'winning_bid_id' => $proxy_bid_id,

					'proxy_bid_id'   => $proxy_bid_id,

					'proxy_max'      => $proxy_max,

					'proxy_user_id'  => $proxy_user_id,

				)

			);



			self::update_bid_row(

				$proxy_bid_id,

				array(

					'bid_amount' => $new_proxy_bid,

				)

			);



			self::mark_bid_outbid( $bid['bid_id'] );



			$result['status']         = 'outbid';

			$result['was_outbid']     = true;

			$result['automatic_diff'] = true;

			$result['current_bid']    = $new_proxy_bid;



			return $result;

		}



		// Automatic bid does not beat existing proxy maximum.

		if ( $bid['is_auto'] && $bid['max_auto'] <= $proxy_max ) {

			$new_proxy_bid = min( $proxy_max, $bid['max_auto'] + max( $manual_increment, $auto_increment ) );

			$new_proxy_bid = max( $state['current_bid'] + $manual_increment, $new_proxy_bid );

			$new_proxy_bid = min( $new_proxy_bid, $proxy_max );



			self::update_runtime_state(

				$product,

				array(

					'current_bid'    => $new_proxy_bid,

					'winning_bid_id' => $proxy_bid_id,

					'proxy_bid_id'   => $proxy_bid_id,

					'proxy_max'      => $proxy_max,

					'proxy_user_id'  => $proxy_user_id,

				)

			);



			self::update_bid_row(

				$proxy_bid_id,

				array(

					'bid_amount' => $new_proxy_bid,

				)

			);



			self::mark_bid_outbid( $bid['bid_id'] );



			$result['status']         = 'outbid';

			$result['was_outbid']     = true;

			$result['automatic_diff'] = true;

			$result['current_bid']    = $new_proxy_bid;



			return $result;

		}



		// New manual/automatic bid beats previous proxy.

		$amount_needed = min( $bid['bid_amount'], $proxy_max + $manual_increment );

		$amount_needed = max( $bid['minimum_needed'], $amount_needed );



		if ( $bid['is_auto'] ) {

			$amount_needed = min( $bid['max_auto'], $amount_needed );

		}



		self::mark_bid_outbid( $proxy_bid_id );



		self::update_runtime_state(

			$product,

			array(

				'current_bid'        => $amount_needed,

				'winning_bid_id'     => $bid['bid_id'],

				'winning_user_id'    => $bid['user_id'],

				'winning_session_id' => $bid['session_id'],

				'proxy_max'          => $bid['is_auto'] ? $bid['max_auto'] : 0,

				'proxy_user_id'      => $bid['is_auto'] ? $bid['user_id'] : 0,

				'proxy_bid_id'       => $bid['is_auto'] ? $bid['bid_id'] : 0,

			)

		);



		self::update_bid_row(

			$bid['bid_id'],

			array(

				'bid_amount'      => $amount_needed,

				'max_auto_amount' => $bid['is_auto'] ? $bid['max_auto'] : null,

				'status'          => 'active',

			)

		);



		$result['current_bid'] = $amount_needed;



		return $result;

	}



	/**

	 * Update runtime state helper.

	 *

	 * @param WC_Product $product Product.

	 * @param array      $state   State.

	 *

	 * @return void

	 */

	private static function update_runtime_state( WC_Product $product, array $state ): void {

		Auction_Product_Helper::set_runtime_state( $product, $state );

	}



	/**

	 * Insert bid row into database.

	 *

	 * @param array $data Row data.

	 *

	 * @return int|WP_Error

	 */

	private static function insert_bid( array $data ) {

		global $wpdb;



		$table = Auction_Install::get_bids_table_name();



		$data['user_id']    = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;

		$data['session_id'] = $data['session_id'] ? sanitize_text_field( $data['session_id'] ) : '';



		$inserted = $wpdb->insert(

			$table,

			$data,

			array(

				'%d',

				'%d',

				'%s',

				'%f',

				'%f',

				'%d',

				'%s',

				'%s',

				'%s',

				'%s',

				'%s',

			)

		);



		if ( false === $inserted ) {

			return new WP_Error( 'auction_bid_insert_failed', __( 'An error occurred while saving your bid. Please try again.', 'auction' ) );

		}



		return (int) $wpdb->insert_id;

	}



	/**

	 * Update bid row data.

	 *

	 * @param int   $bid_id Bid ID.

	 * @param array $data   Data to update.

	 *

	 * @return void

	 */

	private static function update_bid_row( int $bid_id, array $data ): void {

		global $wpdb;



		if ( empty( $data ) ) {

			return;

		}



		$data['updated_at'] = current_time( 'mysql' );



		$wpdb->update(

			Auction_Install::get_bids_table_name(),

			$data,

			array( 'id' => $bid_id )

		);

	}



	/**

	 * Mark bid as outbid.

	 *

	 * @param int $bid_id Bid ID.

	 *

	 * @return void

	 */

	private static function mark_bid_outbid( int $bid_id ): void {

		if ( ! $bid_id ) {

			return;

		}



		self::update_bid_row(

			$bid_id,

			array(

				'status' => 'outbid',

			)

		);

	}



	/**

	 * Get current leading bid data.

	 *

	 * @param int $product_id Product ID.

	 *

	 * @return array|null

	 */

	public static function get_leading_bid( int $product_id ): ?array {

		global $wpdb;



		$table = Auction_Install::get_bids_table_name();



		$sql = $wpdb->prepare(

			"SELECT *

			 FROM {$table}

			 WHERE product_id = %d

			   AND status = 'active'

			 ORDER BY bid_amount DESC, created_at ASC

			 LIMIT 1",

			$product_id

		);



		$bid = $wpdb->get_row( $sql, ARRAY_A );



		return $bid ?: null;

	}



	/**

	 * Retrieve bid history.

	 *

	 * @param int  $product_id Product ID.

	 * @param int  $limit      Number of rows.

	 * @param bool $include_outbid Include outbid bids.

	 *

	 * @return array

	 */

	public static function get_bid_history( int $product_id, int $limit = 10, bool $include_outbid = false ): array {

		global $wpdb;



		$table = Auction_Install::get_bids_table_name();

		$where = $include_outbid ? "status IN ('active','outbid')" : "status = 'active'";



		$sql = $wpdb->prepare(

			"SELECT * FROM {$table}

			 WHERE product_id = %d AND {$where}

			 ORDER BY created_at DESC

			 LIMIT %d",

			$product_id,

			$limit

		);



		return $wpdb->get_results( $sql, ARRAY_A );

	}



	/**

	 * Generate session identifier.

	 *

	 * @return string

	 */

	public static function generate_session_id(): string {

		return wp_hash( microtime( true ) . wp_rand() );

	}

}



