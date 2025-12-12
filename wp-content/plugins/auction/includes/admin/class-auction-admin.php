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
		add_action( 'admin_notices', array( $this, 'maybe_show_menu_creation_notice' ) );
		add_action( 'admin_init', array( $this, 'maybe_create_menu_item' ) );
		
		// Always try to create menu item on every admin page load until it succeeds
		add_action( 'admin_init', array( $this, 'auto_create_menu_item' ), 5 );
	}

	/**
	 * Auto-create menu item on every admin page load until it succeeds.
	 *
	 * @return void
	 */
	public function auto_create_menu_item(): void {
		// Check if already created AND menu is assigned to location
		$created = get_option( 'auction_menu_item_created', false );
		if ( $created ) {
			// Double-check: verify menu is actually assigned to a location
			$menu_assigned = false;
			$all_menus = wp_get_nav_menus();
			$locations = get_nav_menu_locations();
			
			foreach ( $all_menus as $menu ) {
				$menu_items = wp_get_nav_menu_items( $menu->term_id );
				if ( $menu_items ) {
					foreach ( $menu_items as $item ) {
						if ( isset( $item->url ) && ( strpos( $item->url, 'auction_page=1' ) !== false || strpos( $item->url, '/auctions' ) !== false ) ) {
							// Menu item exists, check if menu is assigned
							foreach ( $locations as $loc_menu_id ) {
								if ( (int) $loc_menu_id === (int) $menu->term_id ) {
									$menu_assigned = true;
									break 2;
								}
							}
						}
					}
				}
			}
			
			// If menu item exists but menu isn't assigned, force reassignment
			if ( ! $menu_assigned ) {
				delete_option( 'auction_menu_item_created' );
			} else {
				return;
			}
		}

		// Try to create on every admin page load
		require_once __DIR__ . '/../class-auction-install.php';
		Auction_Install::create_menu_item_manually();
	}

	/**
	 * Show admin notice if menu item needs to be created.
	 *
	 * @return void
	 */
	public function maybe_show_menu_creation_notice(): void {
		// Only show on relevant pages
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'nav-menus', 'dashboard', 'plugins' ), true ) ) {
			return;
		}

		// Check if menu item exists
		$menu_item_exists = $this->check_auction_menu_item_exists();
		if ( $menu_item_exists ) {
			return;
		}

		// Show notice
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Auction Plugin:', 'auction' ); ?></strong>
				<?php esc_html_e( 'The "Auction" navigation menu item was not automatically created.', 'auction' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=auction-dashboard&create_menu_item=1' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
					<?php esc_html_e( 'Create Menu Item Now', 'auction' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if auction menu item exists.
	 *
	 * @return bool
	 */
	private function check_auction_menu_item_exists(): bool {
		$all_menus = wp_get_nav_menus();
		if ( empty( $all_menus ) ) {
			return false;
		}

		foreach ( $all_menus as $menu ) {
			$menu_items = wp_get_nav_menu_items( $menu->term_id );
			if ( $menu_items ) {
				foreach ( $menu_items as $item ) {
					if ( isset( $item->url ) && ( strpos( $item->url, 'auction_page=1' ) !== false || strpos( $item->url, '/auctions' ) !== false ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Handle manual menu item creation request.
	 *
	 * @return void
	 */
	public function maybe_create_menu_item(): void {
		if ( ! isset( $_GET['create_menu_item'] ) || '1' !== $_GET['create_menu_item'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Clear the created flag to force creation and menu assignment
		delete_option( 'auction_menu_item_created' );
		delete_option( 'auction_should_create_menu' );
		delete_option( 'auction_force_create_menu' );

		require_once __DIR__ . '/../class-auction-install.php';
		$result = Auction_Install::create_menu_item_manually();

		if ( is_wp_error( $result ) ) {
			add_action( 'admin_notices', function() use ( $result ) {
				?>
				<div class="notice notice-error">
					<p><strong><?php esc_html_e( 'Auction Plugin:', 'auction' ); ?></strong> <?php echo esc_html( $result->get_error_message() ); ?></p>
					<p><?php esc_html_e( 'Please check that you have at least one menu created in Appearance → Menus', 'auction' ); ?></p>
				</div>
				<?php
			} );
		} else {
			add_action( 'admin_notices', function() {
				?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e( 'Auction Plugin:', 'auction' ); ?></strong> <?php esc_html_e( 'Auction menu item created successfully! Check your header navigation.', 'auction' ); ?></p>
				</div>
				<?php
			} );
		}
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

