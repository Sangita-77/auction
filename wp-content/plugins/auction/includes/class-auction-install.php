<?php
/**
 * Handles plugin installation tasks.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installation routines for the Auction plugin.
 */
class Auction_Install {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::register_cron_events();
	}

	/**
	 * Ensure required tables exist.
	 *
	 * @return void
	 */
	public static function ensure_tables(): void {
		global $wpdb;

		$table_name = self::get_bids_table_name();

		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( $found !== $table_name ) {
			self::create_tables();
		}
	}

	/**
	 * Create required database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::get_bids_table_name();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			session_id VARCHAR(64) DEFAULT NULL,
			bid_amount DECIMAL(19,4) NOT NULL,
			max_auto_amount DECIMAL(19,4) DEFAULT NULL,
			is_auto TINYINT(1) DEFAULT 0,
			status VARCHAR(20) DEFAULT 'active',
			ip_address VARCHAR(100) DEFAULT NULL,
			user_agent TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY product_status (product_id, status),
			KEY user_lookup (user_id),
			KEY session_lookup (session_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register WP-Cron events.
	 *
	 * @return void
	 */
	private static function register_cron_events(): void {
		if ( ! wp_next_scheduled( 'auction_check_ending_events' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'auction_check_ending_events' );
		}
	}

	/**
	 * Get bids table name.
	 *
	 * @return string
	 */
	public static function get_bids_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'auction_bids';
	}
}

