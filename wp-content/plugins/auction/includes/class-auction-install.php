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
		self::flush_rewrite_rules_late();
		
		// Force menu creation - clear any previous flags
		delete_option( 'auction_menu_item_created' );
		delete_option( 'auction_menu_last_attempt' );
		
		// Set a flag to trigger menu creation on next page load (with high priority)
		update_option( 'auction_should_create_menu', true );
		update_option( 'auction_force_create_menu', true ); // Force flag that never expires
	}

	/**
	 * Public method to manually create menu item (can be called from admin).
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_menu_item_manually() {
		return self::create_auction_menu_item();
	}

	/**
	 * Try to create menu item on init (if flag is set).
	 *
	 * @return void
	 */
	public static function maybe_create_menu_on_init(): void {
		$should_create = get_option( 'auction_should_create_menu', false );
		if ( ! $should_create ) {
			return;
		}

		// Check if already created
		$created = get_option( 'auction_menu_item_created', false );
		if ( $created ) {
			delete_option( 'auction_should_create_menu' );
			return;
		}

		// Try to create
		$result = self::create_auction_menu_item();
		
		// If successful, clear the flag
		if ( ! is_wp_error( $result ) ) {
			delete_option( 'auction_should_create_menu' );
		}
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
	 * Flush rewrite rules after endpoints are registered.
	 *
	 * @return void
	 */
	private static function flush_rewrite_rules_late(): void {
		add_action(
			'init',
			static function () {
				flush_rewrite_rules();
			}
		);
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

	/**
	 * Create auction menu item automatically on activation.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_auction_menu_item() {
		// Prevent duplicate calls
		static $creating = false;
		if ( $creating ) {
			return false;
		}
		$creating = true;

		// Get auction page URL
		$auction_url = self::get_auction_page_url();
		
		// Debug: Log attempt
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Auction: Attempting to create menu item. URL: ' . $auction_url );
		}

		// Get registered menu locations
		$locations = get_nav_menu_locations();

		// CRITICAL: Find the menu that's ACTUALLY assigned to header/primary location
		// This is the menu that shows in the frontend header - we MUST add to this menu!
		$menu_locations = array( 'primary', 'header', 'main', 'menu-1', 'primary-menu', 'top', 'navigation' );

		$menu_id = null;
		$header_menu_id = null;
		
		// PRIORITY 1: Find which menu is assigned to header location (this shows in frontend)
		foreach ( $menu_locations as $location ) {
			if ( isset( $locations[ $location ] ) && $locations[ $location ] > 0 ) {
				$header_menu_id = $locations[ $location ];
				$menu_id = $header_menu_id; // Use the menu that's actually assigned to header
				break;
			}
		}
		
		// If we found a header menu, ALWAYS use it - this is what shows in frontend!
		if ( $header_menu_id ) {
			$menu_id = $header_menu_id;
		}

		// If no primary menu found, try to find menu that has shop page OR any menu with items
		if ( ! $menu_id ) {
			$all_menus = wp_get_nav_menus();
			$shop_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
			
			$menu_with_shop = null;
			$menu_with_items = null;
			
			foreach ( $all_menus as $menu ) {
				$menu_items = wp_get_nav_menu_items( $menu->term_id );
				if ( $menu_items && ! empty( $menu_items ) ) {
					// Remember first menu with items
					if ( ! $menu_with_items ) {
						$menu_with_items = $menu->term_id;
					}
					
					// Check if this menu has the shop page
					if ( $shop_page_id ) {
						foreach ( $menu_items as $item ) {
							// Check if this menu has the shop page
							if ( isset( $item->object_id ) && (int) $item->object_id === $shop_page_id ) {
								$menu_with_shop = $menu->term_id;
								break 2;
							}
							// Also check by URL
							if ( isset( $item->url ) && ( strpos( $item->url, '/shop' ) !== false || strpos( $item->url, 'shop' ) !== false ) ) {
								$menu_with_shop = $menu->term_id;
								break 2;
							}
						}
					}
				}
			}
			
			// Prefer menu with shop, otherwise use any menu with items
			$menu_id = $menu_with_shop ?: $menu_with_items;
		}

		// If still no menu found, get the first available menu from locations
		if ( ! $menu_id && ! empty( $locations ) ) {
			$menu_id = reset( $locations );
		}

		// If still no menu, get any menu
		if ( ! $menu_id ) {
			$all_menus = wp_get_nav_menus();
			if ( ! empty( $all_menus ) ) {
				$menu_id = $all_menus[0]->term_id;
			}
		}

		// If still no menu, create a new menu
		if ( ! $menu_id ) {
			$menu_name = __( 'Main Menu', 'auction' );
			$menu_id   = wp_create_nav_menu( $menu_name );

			// Assign to primary location if available
			$registered_menus = get_registered_nav_menus();
			if ( ! empty( $registered_menus ) ) {
				$first_location = array_key_first( $registered_menus );
				$locations[ $first_location ] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );
			}
		}

		// CRITICAL: Ensure the menu is assigned to at least one location
		// This is why the menu doesn't show in frontend!
		if ( $menu_id ) {
			$registered_menus = get_registered_nav_menus();
			$current_locations = get_nav_menu_locations();
			$menu_assigned = false;

			// Check if menu is assigned to any location
			foreach ( $current_locations as $loc_id ) {
				if ( (int) $loc_id === (int) $menu_id ) {
					$menu_assigned = true;
					break;
				}
			}

			// If not assigned, assign it to the first available location
			if ( ! $menu_assigned && ! empty( $registered_menus ) ) {
				// Try common header locations first
				$preferred_locations = array( 'primary', 'header', 'main', 'menu-1', 'primary-menu', 'top', 'navigation' );
				$location_found = false;

				foreach ( $preferred_locations as $pref_loc ) {
					if ( isset( $registered_menus[ $pref_loc ] ) ) {
						$current_locations[ $pref_loc ] = $menu_id;
						$location_found = true;
						break;
					}
				}

				// If no preferred location, use first available
				if ( ! $location_found ) {
					$first_location = array_key_first( $registered_menus );
					$current_locations[ $first_location ] = $menu_id;
				}

				// Save the location assignment
				set_theme_mod( 'nav_menu_locations', $current_locations );
			}
		}

		if ( ! $menu_id ) {
			$creating = false;
			return new WP_Error( 'no_menu', __( 'No menu found to add auction item to.', 'auction' ) );
		}

		// Ensure menu is assigned to a location (critical for frontend display)
		$registered_menus = get_registered_nav_menus();
		$current_locations = get_nav_menu_locations();
		$menu_assigned = false;

		// Check if this menu is assigned to any location
		foreach ( $current_locations as $loc_name => $loc_menu_id ) {
			if ( (int) $loc_menu_id === (int) $menu_id ) {
				$menu_assigned = true;
				break;
			}
		}

		// If menu exists but isn't assigned to any location, assign it
		if ( ! $menu_assigned && ! empty( $registered_menus ) ) {
			// Try common header locations first
			$preferred_locations = array( 'primary', 'header', 'main', 'menu-1', 'primary-menu', 'top', 'navigation' );
			$location_found = false;

			foreach ( $preferred_locations as $pref_loc ) {
				if ( isset( $registered_menus[ $pref_loc ] ) ) {
					// Only assign if location is empty or we're creating a new menu
					if ( empty( $current_locations[ $pref_loc ] ) ) {
						$current_locations[ $pref_loc ] = $menu_id;
						$location_found = true;
						break;
					}
				}
			}

			// If no preferred location available, use first empty location
			if ( ! $location_found ) {
				foreach ( $registered_menus as $loc_name => $loc_desc ) {
					if ( empty( $current_locations[ $loc_name ] ) ) {
						$current_locations[ $loc_name ] = $menu_id;
						$location_found = true;
						break;
					}
				}
			}

			// Save the location assignment
			if ( $location_found ) {
				set_theme_mod( 'nav_menu_locations', $current_locations );
			}
		}

		// Check if auction menu item already exists in this menu
		$menu_items = wp_get_nav_menu_items( $menu_id );
		if ( $menu_items ) {
			foreach ( $menu_items as $item ) {
				if ( isset( $item->url ) && ( strpos( $item->url, 'auction_page=1' ) !== false || strpos( $item->url, '/auctions' ) !== false ) ) {
					// Already exists, mark as created and return
					update_option( 'auction_menu_item_created', true );
					delete_option( 'auction_should_create_menu' );
					delete_option( 'auction_force_create_menu' );
					$creating = false;
					return true;
				}
				// Also check by title
				if ( isset( $item->title ) && strtolower( trim( $item->title ) ) === 'auction' ) {
					update_option( 'auction_menu_item_created', true );
					$creating = false;
					return true;
				}
			}
		}

		// Create the menu item
		$menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => __( 'Auction', 'auction' ),
				'menu-item-url'    => $auction_url,
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
				'menu-item-classes' => 'auctions-menu-item',
			)
		);

		// If successful, mark as created
		if ( ! is_wp_error( $menu_item_id ) && $menu_item_id > 0 ) {
			update_option( 'auction_menu_item_created', true );
			delete_option( 'auction_should_create_menu' );
			delete_option( 'auction_force_create_menu' );
			$creating = false;
			return true;
		}

		$creating = false;
		return new WP_Error( 'menu_item_failed', __( 'Failed to create auction menu item.', 'auction' ) );
	}

	/**
	 * Get the auction page URL.
	 *
	 * @return string
	 */
	private static function get_auction_page_url(): string {
		$shop_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
		if ( $shop_page_id ) {
			$shop_url = get_permalink( $shop_page_id );
			return add_query_arg( 'auction_page', '1', $shop_url );
		}

		// Fallback to /auctions/ if rewrite rules are set up
		return home_url( '/auctions/' );
	}
}

