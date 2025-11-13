<?php
/**
 * Primary loader for the Auction plugin.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin bootstrap logic.
 */
class Auction_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var Auction_Loader|null
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
	 * Initialize hooks.
	 */
	private function __construct() {
		$this->plugin_path = plugin_dir_path( dirname( __FILE__ ) );
		$this->plugin_url  = plugin_dir_url( dirname( __FILE__ ) );

		$this->hooks();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Auction_Loader
	 */
	public static function instance(): Auction_Loader {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_bootstrap_modules' ), 20 );
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'auction',
			false,
			trailingslashit( dirname( plugin_basename( __DIR__ ) ) ) . 'languages'
		);
	}

	/**
	 * Initialize plugin modules once dependencies are ready.
	 *
	 * @return void
	 */
	public function maybe_bootstrap_modules(): void {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return;
		}

		Auction_Install::ensure_tables();

		require_once __DIR__ . '/class-auction-settings.php';
		require_once __DIR__ . '/class-auction-product-helper.php';
		require_once __DIR__ . '/class-auction-bid-manager.php';
		require_once __DIR__ . '/admin/class-auction-admin.php';
		require_once __DIR__ . '/frontend/class-auction-frontend.php';
		require_once __DIR__ . '/class-auction-event-manager.php';
		require_once __DIR__ . '/class-auction-account.php';

		Auction_Admin::instance(
			array(
				'plugin_path' => $this->plugin_path,
				'plugin_url'  => $this->plugin_url,
			)
		);

		Auction_Frontend::instance(
			array(
				'plugin_path' => $this->plugin_path,
				'plugin_url'  => $this->plugin_url,
			)
		);

		Auction_Event_Manager::instance();
		Auction_Account::instance();
	}
}

Auction_Loader::instance();

