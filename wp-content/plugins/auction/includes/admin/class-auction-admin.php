<?php
/**
 * Admin bootstrap for Auction plugin.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin functionality for plugin.
 */
class Auction_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Auction_Admin|null
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
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	private function __construct( array $args = array() ) {
		$this->plugin_path = $args['plugin_path'] ?? '';
		$this->plugin_url  = $args['plugin_url'] ?? '';

		$this->includes();
		$this->hooks();
	}

	/**
	 * Get singleton instance.
	 *
	 * @param array $args Arguments.
	 *
	 * @return Auction_Admin
	 */
	public static function instance( array $args = array() ): Auction_Admin {
		if ( null === self::$instance ) {
			self::$instance = new self( $args );
		}

		return self::$instance;
	}

	/**
	 * Include admin files.
	 *
	 * @return void
	 */
	private function includes(): void {
		require_once __DIR__ . '/product/class-auction-product-tabs.php';
		require_once __DIR__ . '/settings/class-auction-admin-menu.php';
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Current screen hook.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$styles = array();
		$scripts = array();

		if ( in_array( $screen->id, array( 'product', 'edit-product' ), true ) ) {
			$styles[]  = array(
				'handle' => 'auction-admin-product',
				'src'    => $this->plugin_url . 'assets/css/admin-product.css',
				'deps'   => array(),
			);
			$scripts[] = array(
				'handle' => 'auction-admin-product',
				'src'    => $this->plugin_url . 'assets/js/admin-product.js',
				'deps'   => array( 'jquery', 'woocommerce_admin' ),
			);
		}

		$should_localize_pages = false;

		if ( in_array( $screen->id, array( 'toplevel_page_auction-dashboard', 'auction_page_auction-settings' ), true ) ) {
			$styles[] = array(
				'handle' => 'auction-admin-pages',
				'src'    => $this->plugin_url . 'assets/css/admin-pages.css',
				'deps'   => array(),
			);
			$scripts[] = array(
				'handle' => 'auction-admin-pages',
				'src'    => $this->plugin_url . 'assets/js/admin-pages.js',
				'deps'   => array( 'jquery' ),
			);
			$should_localize_pages = true;

			if ( ! did_action( 'wp_enqueue_media' ) ) {
				wp_enqueue_media();
			}
		}

		foreach ( $styles as $style ) {
			wp_enqueue_style(
				$style['handle'],
				$style['src'],
				$style['deps'],
				Auction_Plugin::VERSION
			);
		}

		foreach ( $scripts as $script ) {
			wp_enqueue_script(
				$script['handle'],
				$script['src'],
				$script['deps'],
				Auction_Plugin::VERSION,
				true
			);

			if ( 'auction-admin-product' === $script['handle'] ) {
				wp_localize_script(
					'auction-admin-product',
					'AuctionProductConfig',
					array(
						'i18n' => array(
							'add_rule'    => __( 'Add rule', 'auction' ),
							'delete_rule' => __( 'Remove', 'auction' ),
							'no_rules'    => __( 'No advanced rules defined yet.', 'auction' ),
						),
					)
				);
			}
		}

		if ( $should_localize_pages ) {
			wp_localize_script(
				'auction-admin-pages',
				'auctionAdminPages',
				array(
					'i18n' => array(
						'mediaTitle'  => __( 'Select badge image', 'auction' ),
						'mediaButton' => __( 'Use image', 'auction' ),
					),
				)
			);
		}
	}
}

