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



		// Try to create menu item if needed (after everything is loaded)

		if ( get_option( 'auction_force_create_menu', false ) || get_option( 'auction_should_create_menu', false ) ) {

			add_action( 'wp_loaded', array( __CLASS__, 'maybe_create_auction_menu' ), 999 );

			add_action( 'admin_init', array( __CLASS__, 'maybe_create_auction_menu' ), 999 );

		}

	}



	/**

	 * Maybe create auction menu item.

	 *

	 * @return void

	 */

	public static function maybe_create_auction_menu(): void {

		// Check if already created

		$created = get_option( 'auction_menu_item_created', false );

		if ( $created ) {

			delete_option( 'auction_should_create_menu' );

			delete_option( 'auction_force_create_menu' );

			return;

		}



		// Try to create

		$result = Auction_Install::create_menu_item_manually();

	}

}



Auction_Loader::instance();



