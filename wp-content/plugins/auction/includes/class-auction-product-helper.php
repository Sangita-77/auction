<?php
/**
 * Helper utilities for auction products.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Utility methods to work with auction-enabled products.
 */
class Auction_Product_Helper {

	/**
	 * Meta prefix.
	 *
	 * @var string
	 */
	private const META_PREFIX = '_auction_';

	/**
	 * Check whether a product has auction enabled.
	 *
	 * @param WC_Product $product Product instance.
	 *
	 * @return bool
	 */
	public static function is_auction_product( WC_Product $product ): bool {
		return 'yes' === $product->get_meta( self::META_PREFIX . 'enabled', true );
	}

	/**
	 * Retrieve auction configuration for product.
	 *
	 * @param WC_Product $product Product instance.
	 *
	 * @return array
	 */
	public static function get_config( WC_Product $product ): array {
		$meta_keys = array(
			'condition',
			'type',
			'sealed',
			'start_time',
			'end_time',
			'start_price',
			'min_increment',
			'reserve_price',
			'buy_now_enabled',
			'buy_now_price',
			'override_bid_options',
			'automatic_bidding',
			'bid_increment_mode',
			'automatic_increment_value',
			'automatic_increment_rules',
			'override_fee_options',
			'override_commission_options',
			'override_reschedule_options',
			'override_overtime_options',
		);

		$config = array(
			'product_id' => $product->get_id(),
		);

		foreach ( $meta_keys as $key ) {
			$config[ $key ] = $product->get_meta( self::META_PREFIX . $key, true );
		}

		$config['start_price']            = self::to_float( $config['start_price'] ?? 0 );
		$config['min_increment']          = self::to_float( $config['min_increment'] ?? 0 );
		$config['reserve_price']          = self::to_float( $config['reserve_price'] ?? 0 );
		$config['automatic_increment_value'] = self::to_float( $config['automatic_increment_value'] ?? 0 );
		$config['automatic_increment_rules'] = self::parse_rules( $config['automatic_increment_rules'] );

		$config['sealed']             = 'yes' === ( $config['sealed'] ?? 'no' );
		$config['buy_now_enabled']    = 'yes' === ( $config['buy_now_enabled'] ?? 'no' );
		$config['automatic_bidding']  = 'yes' === ( $config['automatic_bidding'] ?? 'no' );
		$config['bid_increment_mode'] = $config['bid_increment_mode'] ?: 'simple';

		$config['start_timestamp'] = self::to_timestamp( $config['start_time'] ?? '' );
		$config['end_timestamp']   = self::to_timestamp( $config['end_time'] ?? '' );

		return $config;
	}

	/**
	 * Parse automatic increment rules.
	 *
	 * @param mixed $raw Raw rules.
	 *
	 * @return array
	 */
	private static function parse_rules( $raw ): array {
		if ( empty( $raw ) ) {
			return array();
		}

		if ( is_string( $raw ) ) {
			$data = json_decode( $raw, true );
			if ( is_array( $data ) ) {
				$raw = $data;
			}
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$rules = array();

		foreach ( $raw as $rule ) {
			if ( empty( $rule['increment'] ) ) {
				continue;
			}

			$rules[] = array(
				'from'      => self::to_float( $rule['from'] ?? 0 ),
				'to'        => isset( $rule['to'] ) && '' !== $rule['to'] ? self::to_float( $rule['to'] ) : null,
				'increment' => self::to_float( $rule['increment'] ?? 0 ),
			);
		}

		usort(
			$rules,
			static function ( $a, $b ) {
				return $a['from'] <=> $b['from'];
			}
		);

		return $rules;
	}

	/**
	 * Convert value to float safely.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return float
	 */
	public static function to_float( $value ): float {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		if ( is_string( $value ) ) {
			$value = wc_clean( $value );
			$value = str_replace( ',', '.', $value );
			if ( is_numeric( $value ) ) {
				return (float) $value;
			}
		}

		return 0.0;
	}

	/**
	 * Convert datetime string to timestamp.
	 *
	 * @param string $datetime Datetime string.
	 *
	 * @return int|null
	 */
	private static function to_timestamp( string $datetime ): ?int {
		if ( empty( $datetime ) ) {
			return null;
		}

		$timezones = array(
			wp_timezone(),
			new DateTimeZone( 'UTC' ),
		);

		$try = array(
			$datetime,
			str_replace( 'T', ' ', $datetime ),
			preg_replace( '/(\d{2})-(\d{2})-(\d{4})/u', '$3-$2-$1', $datetime ),
		);

		$formats = array(
			'Y-m-d H:i:s',
			'Y-m-d H:i',
			'Y-m-d\TH:i:s',
			'Y-m-d\TH:i',
			'd-m-Y H:i:s',
			'd-m-Y H:i',
			'd-m-Y h:i a',
			'd-m-Y h:i A',
			'd/m/Y H:i:s',
			'd/m/Y H:i',
			'd/m/Y h:i a',
			'd/m/Y h:i A',
		);

		foreach ( $timezones as $timezone ) {
			foreach ( $try as $candidate ) {
				$candidate = trim( (string) $candidate );

				if ( '' === $candidate ) {
					continue;
				}

				foreach ( $formats as $format ) {
					$dt = DateTimeImmutable::createFromFormat( $format, $candidate, $timezone );

					if ( $dt instanceof DateTimeImmutable ) {
						return $dt->getTimestamp();
					}
				}

				try {
					$dt = new DateTimeImmutable( $candidate, $timezone );

					return $dt->getTimestamp();
				} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
					// Ignore and try next value.
				}
			}
		}

		return null;
	}

	/**
	 * Determine manual increment amount.
	 *
	 * @param array $config Auction configuration.
	 *
	 * @return float
	 */
	public static function get_manual_increment( array $config ): float {
		$increment = $config['min_increment'] ?? 0;

		return $increment > 0 ? $increment : 1.0;
	}

	/**
	 * Derive automatic increment amount based on current bid and rules.
	 *
	 * @param array $config       Auction configuration.
	 * @param float $current_bid  Current bid value.
	 *
	 * @return float
	 */
	public static function get_automatic_increment( array $config, float $current_bid ): float {
		if ( 'advanced' === ( $config['bid_increment_mode'] ?? 'simple' ) ) {
			foreach ( $config['automatic_increment_rules'] as $rule ) {
				$from = $rule['from'];
				$to   = $rule['to'];

				if ( $current_bid >= $from && ( null === $to || $current_bid < $to ) ) {
					return max( 0.01, $rule['increment'] );
				}
			}
		}

		$value = $config['automatic_increment_value'] ?? 0;

		if ( $value > 0 ) {
			return $value;
		}

		return self::get_manual_increment( $config );
	}

	/**
	 * Retrieve auction runtime state stored in meta.
	 *
	 * @param WC_Product $product Product instance.
	 *
	 * @return array
	 */
	public static function get_runtime_state( WC_Product $product ): array {
		return array(
			'current_bid'        => self::to_float( $product->get_meta( self::META_PREFIX . 'current_bid', true ) ),
			'winning_bid_id'     => absint( $product->get_meta( self::META_PREFIX . 'winning_bid_id', true ) ),
			'winning_user_id'    => absint( $product->get_meta( self::META_PREFIX . 'winning_user_id', true ) ),
			'winning_session_id' => $product->get_meta( self::META_PREFIX . 'winning_session_id', true ),
			'proxy_max'          => self::to_float( $product->get_meta( self::META_PREFIX . 'proxy_max', true ) ),
			'proxy_user_id'      => absint( $product->get_meta( self::META_PREFIX . 'proxy_user_id', true ) ),
			'proxy_bid_id'       => absint( $product->get_meta( self::META_PREFIX . 'proxy_bid_id', true ) ),
		);
	}

	/**
	 * Persist runtime state into product meta.
	 *
	 * @param WC_Product $product Product instance.
	 * @param array      $state   State data.
	 *
	 * @return void
	 */
	public static function set_runtime_state( WC_Product $product, array $state ): void {
		$product->update_meta_data( self::META_PREFIX . 'current_bid', $state['current_bid'] ?? 0 );
		$product->update_meta_data( self::META_PREFIX . 'winning_bid_id', $state['winning_bid_id'] ?? 0 );
		$product->update_meta_data( self::META_PREFIX . 'winning_user_id', $state['winning_user_id'] ?? 0 );
		$product->update_meta_data( self::META_PREFIX . 'winning_session_id', $state['winning_session_id'] ?? '' );
		$product->update_meta_data( self::META_PREFIX . 'proxy_max', $state['proxy_max'] ?? 0 );
		$product->update_meta_data( self::META_PREFIX . 'proxy_user_id', $state['proxy_user_id'] ?? 0 );
		$product->update_meta_data( self::META_PREFIX . 'proxy_bid_id', $state['proxy_bid_id'] ?? 0 );
		$product->save_meta_data();
	}

	/**
	 * Determine auction status based on schedule.
	 *
	 * @param array $config Auction configuration.
	 *
	 * @return string active|scheduled|ended
	 */
	public static function get_auction_status( array $config ): string {
		$now        = current_time( 'timestamp' );
		// print_r($now);
		// echo "..................................";
		$start_time = $config['start_timestamp'];
		// print_r($start_time);

		$end_time   = $config['end_timestamp'];

		if ( $start_time && $now < $start_time ) {
			return 'scheduled';
		}

		if ( $end_time && $now > $end_time ) {
			return 'ended';
		}

		return 'active';
	}

	/**
	 * Get auction start price or fallback.
	 *
	 * @param array $config Auction configuration.
	 *
	 * @return float
	 */
	public static function get_start_price( array $config ): float {
		$start_price = $config['start_price'] ?? 0;

		if ( $start_price > 0 ) {
			return $start_price;
		}

		return self::to_float( get_post_meta( $config['product_id'], '_price', true ) );
	}
}

