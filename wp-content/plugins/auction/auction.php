<?php
/**
 * Plugin Name: Auction
 * Plugin URI:  https://example.com/
 * Description: Custom auction enhancements for WooCommerce products.
 * Version:     1.0.0
 * Author:      Sangita
 * Author URI:  https://example.com/
 * Text Domain: auction
 * Domain Path: /languages
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Auction_Plugin', false ) ) {

	/**
	 * Core plugin class.
	 */
	final class Auction_Plugin {

		/**
		 * Singleton instance.
		 *
		 * @var Auction_Plugin|null
		 */
		private static $instance = null;

		/**
		 * Plugin version.
		 */
		public const VERSION = '0.1.0';

		/**
		 * Plugin slug.
		 */
		public const SLUG = 'auction';

		/**
		 * Plugin constructor.
		 */
		private function __construct() {
			$this->includes();
			$this->init_hooks();
		}

		/**
		 * Bootstrap plugin.
		 *
		 * @return void
		 */
		public static function init(): void {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
		}

		/**
		 * Include required files.
		 *
		 * @return void
		 */
		private function includes(): void {
			require_once __DIR__ . '/includes/class-auction-install.php';
			require_once __DIR__ . '/includes/class-auction-loader.php';
		}

		/**
		 * Register core hooks.
		 *
		 * @return void
		 */
		private function init_hooks(): void {
			register_activation_hook( __FILE__, array( $this, 'activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		}

		/**
		 * Plugin activation callback.
		 *
		 * @return void
		 */
		public function activate(): void {
			Auction_Install::activate();
		}

		/**
		 * Plugin deactivation callback.
		 *
		 * @return void
		 */
		public function deactivate(): void {
			$timestamp = wp_next_scheduled( 'auction_check_ending_events' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'auction_check_ending_events' );
			}
		}
	}

	add_action( 'plugins_loaded', array( 'Auction_Plugin', 'init' ) );
}

