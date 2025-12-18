<?php
/**
 * Admin menu and settings pages for Auction plugin.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers Auction admin menu items.
 */
class Auction_Admin_Menu {

	/**
	 * Singleton instance.
	 *
	 * @var Auction_Admin_Menu|null
	 */
	private static $instance = null;

	/**
	 * Option name for global settings.
	 *
	 * @var string
	 */
	private $option_name = 'auction_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private $settings_cache = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->hooks();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Auction_Admin_Menu
	 */
	public static function instance(): Auction_Admin_Menu {
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_csv_export_trigger' ) );
	}

	/**
	 * Register admin menu items.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Auctions', 'auction' ),
			__( 'Auctions', 'auction' ),
			'manage_woocommerce',
			'auction-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-hammer',
			56
		);

		add_submenu_page(
			'auction-dashboard',
			__( 'All Auctions', 'auction' ),
			__( 'All Auctions', 'auction' ),
			'manage_woocommerce',
			'auction-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'auction-dashboard',
			__( 'Settings', 'auction' ),
			__( 'Settings', 'auction' ),
			'manage_woocommerce',
			'auction-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'auction' ) );
		}

		// Auto-create menu item when dashboard is accessed
		// Always try to ensure it's in the header menu
		require_once __DIR__ . '/../../class-auction-install.php';
		
		// Check if menu item exists in the menu assigned to header
		$menu_item_in_header = false;
		$locations = get_nav_menu_locations();
		$menu_locations = array( 'primary', 'header', 'main', 'menu-1', 'primary-menu', 'top', 'navigation' );
		
		foreach ( $menu_locations as $location ) {
			if ( isset( $locations[ $location ] ) && $locations[ $location ] > 0 ) {
				$header_menu_id = $locations[ $location ];
				$menu_items = wp_get_nav_menu_items( $header_menu_id );
				if ( $menu_items ) {
					foreach ( $menu_items as $item ) {
						if ( isset( $item->url ) && ( strpos( $item->url, 'auction_page=1' ) !== false || strpos( $item->url, '/auctions' ) !== false ) ) {
							$menu_item_in_header = true;
							break 2;
						}
					}
				}
				break;
			}
		}
		
		// If not in header menu, create it
		if ( ! $menu_item_in_header ) {
			delete_option( 'auction_menu_item_created' ); // Force recreation
			Auction_Install::create_menu_item_manually();
		}

		$filters    = $this->get_dashboard_filters();
		$rows       = $this->get_auction_rows( $filters );
		$export_url = $this->get_export_url( $filters );

		?>
		<div class="wrap auction-admin-wrap">
			<h1><?php esc_html_e( 'All Auctions', 'auction' ); ?></h1>
			<p><?php esc_html_e( 'Below you will find a list of all the auctions created on your site.', 'auction' ); ?></p>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">
					<?php esc_html_e( 'Create New Auction Product', 'auction' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $export_url ); ?>">
					<?php esc_html_e( 'Export CSV', 'auction' ); ?>
				</a>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=auction-dashboard&create_menu_item=1' ) ); ?>">
					<?php esc_html_e( 'Create Auction Menu Item', 'auction' ); ?>
				</a>
			</p>
			
			<?php
			// Check if menu item exists and menu is assigned
			$menu_item_exists = false;
			$menu_assigned = false;
			$all_menus = wp_get_nav_menus();
			$locations = get_nav_menu_locations();
			
			foreach ( $all_menus as $menu ) {
				$menu_items = wp_get_nav_menu_items( $menu->term_id );
				if ( $menu_items ) {
					foreach ( $menu_items as $item ) {
						if ( isset( $item->url ) && ( strpos( $item->url, 'auction_page=1' ) !== false || strpos( $item->url, '/auctions' ) !== false ) ) {
							$menu_item_exists = true;
							// Check if this menu is assigned to a location
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
			
			if ( $menu_item_exists && ! $menu_assigned ) :
				?>
				<div class="notice notice-warning" style="margin-top: 20px;">
					<p>
						<strong><?php esc_html_e( 'Important:', 'auction' ); ?></strong>
						<?php esc_html_e( 'The Auction menu item exists but the menu is not assigned to a header location. Click the button above to fix this.', 'auction' ); ?>
					</p>
				</div>
				<?php
			elseif ( ! $menu_item_exists ) :
				?>
				<div class="notice notice-info" style="margin-top: 20px;">
					<p>
						<strong><?php esc_html_e( 'Setup:', 'auction' ); ?></strong>
						<?php esc_html_e( 'Click the "Create Auction Menu Item" button above to automatically add the Auction link to your navigation menu.', 'auction' ); ?>
					</p>
				</div>
				<?php
			endif;
			?>

			<form method="get" class="auction-admin-filters">
				<input type="hidden" name="page" value="auction-dashboard" />
				<label for="auction_status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'auction' ); ?></label>
				<select name="auction_status" id="auction_status">
					<?php
					$statuses = array(
						'all'       => __( 'All statuses', 'auction' ),
						'active'    => __( 'Active', 'auction' ),
						'scheduled' => __( 'Scheduled', 'auction' ),
						'ended'     => __( 'Ended', 'auction' ),
					);
					foreach ( $statuses as $value => $label ) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label for="auction_search" class="screen-reader-text"><?php esc_html_e( 'Search auctions', 'auction' ); ?></label>
				<input
					type="search"
					name="auction_search"
					id="auction_search"
					value="<?php echo esc_attr( $filters['search'] ); ?>"
					placeholder="<?php esc_attr_e( 'Search products…', 'auction' ); ?>"
				/>

				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Filter', 'auction' ); ?></button>

				<?php if ( 'all' !== $filters['status'] || ! empty( $filters['search'] ) ) : ?>
					<a class="button button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=auction-dashboard' ) ); ?>">
						<?php esc_html_e( 'Reset', 'auction' ); ?>
					</a>
				<?php endif; ?>
			</form>

			<?php if ( ! empty( $rows ) ) : ?>
				<table class="widefat striped auction-dashboard-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'auction' ); ?></th>
							<th><?php esc_html_e( 'Status', 'auction' ); ?></th>
							<th><?php esc_html_e( 'Auction Type', 'auction' ); ?></th>
							<th><?php esc_html_e( 'Start Time', 'auction' ); ?></th>
							<th><?php esc_html_e( 'End Time', 'auction' ); ?></th>
							<th><?php esc_html_e( 'Start Price', 'auction' ); ?></th>
							<th><?php esc_html_e( 'Current Bid', 'auction' ); ?></th>
							<th><?php esc_html_e( 'Reserve Price', 'auction' ); ?></th>
							<th><?php esc_html_e( 'Total Bids', 'auction' ); ?></th>
							<th><?php esc_html_e( 'Latest Bidder', 'auction' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'auction' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $rows as $row ) :
							$current_bid_display = wc_price( $row['current_bid'] );
							$start_price_display = wc_price( $row['start_price'] );
							$reserve_price_display = $row['reserve_price'] > 0 ? wc_price( $row['reserve_price'] ) : __( 'N/A', 'auction' );
							$buy_now_display = $row['buy_now_enabled'] ? wc_price( $row['buy_now_price'] ) : __( 'Disabled', 'auction' );
							?>
							<tr class="auction-row-main" data-product-id="<?php echo esc_attr( $row['product_id'] ); ?>">
								<td>
									<strong>
										<a href="<?php echo esc_url( $row['edit_link'] ); ?>">
											<?php echo esc_html( $row['product_name'] ); ?>
										</a>
									</strong>
									<br>
									<small class="description"><?php esc_html_e( 'SKU:', 'auction' ); ?> <?php echo esc_html( $row['product_sku'] ); ?></small>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url( $row['edit_link'] ); ?>">
												<?php esc_html_e( 'Edit', 'auction' ); ?>
											</a>
											|
										</span>
										<span class="view">
											<a href="<?php echo esc_url( $row['view_link'] ); ?>" target="_blank" rel="noopener noreferrer">
												<?php esc_html_e( 'View', 'auction' ); ?>
											</a>
											|
										</span>
										<span class="details">
											<a href="#" class="auction-toggle-details" data-product-id="<?php echo esc_attr( $row['product_id'] ); ?>" data-show-text="<?php esc_attr_e( 'Show Details', 'auction' ); ?>" data-hide-text="<?php esc_attr_e( 'Hide Details', 'auction' ); ?>">
												<?php esc_html_e( 'Show Details', 'auction' ); ?>
											</a>
										</span>
									</div>
								</td>
								<td>
									<span class="auction-status-badge status-<?php echo esc_attr( $row['status'] ); ?>">
										<?php echo esc_html( $this->get_status_label( $row['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $row['auction_type'] ); ?></td>
								<td><?php echo esc_html( $row['start_time'] ); ?></td>
								<td><?php echo esc_html( $row['end_time'] ); ?></td>
								<td><?php echo wp_kses_post( $start_price_display ); ?></td>
								<td><strong><?php echo wp_kses_post( $current_bid_display ); ?></strong></td>
								<td><?php echo is_numeric( $row['reserve_price'] ) && $row['reserve_price'] > 0 ? wp_kses_post( $reserve_price_display ) : esc_html( $reserve_price_display ); ?></td>
								<td>
									<strong><?php echo esc_html( $row['total_bids'] ); ?></strong>
									<?php if ( $row['active_bids'] > 0 ) : ?>
										<br><small class="description"><?php echo esc_html( $row['active_bids'] ); ?> <?php esc_html_e( 'active', 'auction' ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $row['latest_name'] && $row['latest_name'] !== '—' ) : ?>
										<?php echo esc_html( $row['latest_name'] ); ?>
										<?php if ( $row['latest_user_id'] ) : ?>
											<br><small class="description">
												<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $row['latest_user_id'] ) ); ?>">
													<?php esc_html_e( 'View User', 'auction' ); ?>
												</a>
											</small>
										<?php endif; ?>
									<?php else : ?>
										<?php esc_html_e( 'No bids yet', 'auction' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo esc_url( $row['edit_link'] ); ?>" class="button button-small">
										<?php esc_html_e( 'Edit', 'auction' ); ?>
									</a>
									<br><br>
									<a href="#" class="button button-small auction-toggle-details" data-product-id="<?php echo esc_attr( $row['product_id'] ); ?>" data-show-text="<?php esc_attr_e( 'Show Details', 'auction' ); ?>" data-hide-text="<?php esc_attr_e( 'Hide Details', 'auction' ); ?>">
										<?php esc_html_e( 'Show Details', 'auction' ); ?>
									</a>
								</td>
							</tr>
							<tr class="auction-row-details" id="details-<?php echo esc_attr( $row['product_id'] ); ?>" style="display: none;">
								<td colspan="11">
									<div class="auction-details-panel">
										<h3><?php esc_html_e( 'Complete Auction Details', 'auction' ); ?></h3>
										<div class="auction-details-grid">
											<div class="auction-details-section">
												<h4><?php esc_html_e( 'Product Information', 'auction' ); ?></h4>
												<table class="auction-details-table">
													<tr>
														<th><?php esc_html_e( 'Product ID:', 'auction' ); ?></th>
														<td><?php echo esc_html( $row['product_id'] ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Product Name:', 'auction' ); ?></th>
														<td><?php echo esc_html( $row['product_name'] ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'SKU:', 'auction' ); ?></th>
														<td><?php echo esc_html( $row['product_sku'] ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Stock Status:', 'auction' ); ?></th>
														<td><?php echo esc_html( ucfirst( $row['stock_status'] ) ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Stock Quantity:', 'auction' ); ?></th>
														<td><?php echo esc_html( $row['stock_quantity'] ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Condition:', 'auction' ); ?></th>
														<td><?php echo esc_html( $row['condition'] ); ?></td>
													</tr>
												</table>
											</div>

											<div class="auction-details-section">
												<h4><?php esc_html_e( 'Auction Configuration', 'auction' ); ?></h4>
												<table class="auction-details-table">
													<tr>
														<th><?php esc_html_e( 'Auction Type:', 'auction' ); ?></th>
														<td><?php echo esc_html( $row['auction_type'] ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Status:', 'auction' ); ?></th>
														<td><span class="auction-status-badge status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $this->get_status_label( $row['status'] ) ); ?></span></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Start Time:', 'auction' ); ?></th>
														<td><?php echo esc_html( $row['start_time'] ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'End Time:', 'auction' ); ?></th>
														<td><?php echo esc_html( $row['end_time'] ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Start Price:', 'auction' ); ?></th>
														<td><?php echo wp_kses_post( $start_price_display ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Minimum Increment:', 'auction' ); ?></th>
														<td><?php echo wp_kses_post( wc_price( $row['min_increment'] ) ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Reserve Price:', 'auction' ); ?></th>
														<td><?php echo is_numeric( $row['reserve_price'] ) && $row['reserve_price'] > 0 ? wp_kses_post( $reserve_price_display ) : esc_html__( 'Not set', 'auction' ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Buy Now:', 'auction' ); ?></th>
														<td><?php echo $row['buy_now_enabled'] ? wp_kses_post( $buy_now_display ) : esc_html__( 'Disabled', 'auction' ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Automatic Bidding:', 'auction' ); ?></th>
														<td><?php echo $row['automatic_bidding'] ? esc_html__( 'Enabled', 'auction' ) : esc_html__( 'Disabled', 'auction' ); ?></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Bid Increment Mode:', 'auction' ); ?></th>
														<td><?php echo esc_html( ucfirst( $row['bid_increment_mode'] ) ); ?></td>
													</tr>
												</table>
											</div>

											<div class="auction-details-section">
												<h4><?php esc_html_e( 'Bidding Information', 'auction' ); ?></h4>
												<table class="auction-details-table">
													<tr>
														<th><?php esc_html_e( 'Current Bid:', 'auction' ); ?></th>
														<td><strong><?php echo wp_kses_post( $current_bid_display ); ?></strong></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Total Bids:', 'auction' ); ?></th>
														<td><strong><?php echo esc_html( $row['total_bids'] ); ?></strong></td>
													</tr>
													<tr>
														<th><?php esc_html_e( 'Active Bids:', 'auction' ); ?></th>
														<td><?php echo esc_html( $row['active_bids'] ); ?></td>
													</tr>
													<?php if ( $row['latest_name'] && $row['latest_name'] !== '—' ) : ?>
														<tr>
															<th><?php esc_html_e( 'Latest Bidder:', 'auction' ); ?></th>
															<td>
																<?php echo esc_html( $row['latest_name'] ); ?>
																<?php if ( $row['latest_user_id'] ) : ?>
																	<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $row['latest_user_id'] ) ); ?>" class="button button-small" style="margin-left: 10px;">
																		<?php esc_html_e( 'View User', 'auction' ); ?>
																	</a>
																<?php endif; ?>
															</td>
														</tr>
														<tr>
															<th><?php esc_html_e( 'Latest Bid Amount:', 'auction' ); ?></th>
															<td><?php echo wp_kses_post( wc_price( $row['latest_amount'] ) ); ?></td>
														</tr>
														<tr>
															<th><?php esc_html_e( 'Latest Bid Time:', 'auction' ); ?></th>
															<td><?php echo esc_html( $row['latest_time'] ); ?></td>
														</tr>
													<?php else : ?>
														<tr>
															<th><?php esc_html_e( 'Latest Bidder:', 'auction' ); ?></th>
															<td><?php esc_html_e( 'No bids yet', 'auction' ); ?></td>
														</tr>
													<?php endif; ?>
													<?php if ( $row['proxy_max'] > 0 ) : ?>
														<tr>
															<th><?php esc_html_e( 'Active Proxy Bid:', 'auction' ); ?></th>
															<td>
																<?php echo wp_kses_post( wc_price( $row['proxy_max'] ) ); ?>
																<?php if ( $row['proxy_user_id'] ) : ?>
																	<?php
																	$proxy_user = get_user_by( 'id', $row['proxy_user_id'] );
																	if ( $proxy_user ) :
																		?>
																		<br><small><?php esc_html_e( 'User:', 'auction' ); ?> 
																			<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $row['proxy_user_id'] ) ); ?>">
																				<?php echo esc_html( $proxy_user->display_name ?: $proxy_user->user_login ); ?>
																			</a>
																		</small>
																	<?php endif; ?>
																<?php endif; ?>
															</td>
														</tr>
													<?php endif; ?>
												</table>
											</div>

											<?php if ( $row['status'] === 'ended' && $row['winner_user_id'] ) : ?>
												<div class="auction-details-section">
													<h4><?php esc_html_e( 'Winner Information', 'auction' ); ?></h4>
													<table class="auction-details-table">
														<tr>
															<th><?php esc_html_e( 'Winner:', 'auction' ); ?></th>
															<td>
																<strong><?php echo esc_html( $row['winner_name'] ); ?></strong>
																<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $row['winner_user_id'] ) ); ?>" class="button button-small" style="margin-left: 10px;">
																	<?php esc_html_e( 'View User', 'auction' ); ?>
																</a>
															</td>
														</tr>
														<tr>
															<th><?php esc_html_e( 'Winning Amount:', 'auction' ); ?></th>
															<td><strong><?php echo wp_kses_post( wc_price( $row['winner_amount'] ) ); ?></strong></td>
														</tr>
														<tr>
															<th><?php esc_html_e( 'Winning Time:', 'auction' ); ?></th>
															<td><?php echo esc_html( $row['winner_time'] ); ?></td>
														</tr>
													</table>
												</div>
											<?php endif; ?>

											<div class="auction-details-section">
												<h4><?php esc_html_e( 'Quick Actions', 'auction' ); ?></h4>
												<p>
													<a href="<?php echo esc_url( $row['edit_link'] ); ?>" class="button button-primary">
														<?php esc_html_e( 'Edit Product', 'auction' ); ?>
													</a>
													<a href="<?php echo esc_url( $row['view_link'] ); ?>" class="button" target="_blank" rel="noopener noreferrer">
														<?php esc_html_e( 'View on Frontend', 'auction' ); ?>
													</a>
													<?php
													$bid_history_url = add_query_arg(
														array(
															'post_type' => 'product',
															'page' => 'auction-bid-history',
															'product_id' => $row['product_id'],
														),
														admin_url( 'edit.php' )
													);
													?>
													<a href="<?php echo esc_url( $row['edit_link'] . '#auction_bids' ); ?>" class="button">
														<?php esc_html_e( 'View Bid History', 'auction' ); ?>
													</a>
												</p>
											</div>
										</div>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No auction products found yet.', 'auction' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Retrieve sanitized dashboard filters from the request.
	 *
	 * @return array
	 */
	private function get_dashboard_filters(): array {
		$status = isset( $_GET['auction_status'] ) ? sanitize_key( wp_unslash( $_GET['auction_status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $status, array( 'all', 'active', 'scheduled', 'ended' ), true ) ) {
			$status = 'all';
		}

		$search = isset( $_GET['auction_search'] ) ? sanitize_text_field( wp_unslash( $_GET['auction_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array(
			'status' => $status,
			'search' => $search,
		);
	}

	/**
	 * Intercept CSV export requests early in admin lifecycle.
	 *
	 * @return void
	 */
	public function maybe_handle_csv_export_trigger(): void {
		if ( empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'auction-dashboard' !== $page ) {
			return;
		}

		if ( empty( $_GET['auction_export'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$filters = $this->get_dashboard_filters();

		$this->maybe_handle_csv_export( $filters );
	}

	/**
	 * Build CSV export link preserving filters.
	 *
	 * @param array $filters Current filters.
	 *
	 * @return string
	 */
	private function get_export_url( array $filters ): string {
		$args = array(
			'page'            => 'auction-dashboard',
			'auction_export'  => 'csv',
			'_wpnonce'        => wp_create_nonce( 'auction_export_csv' ),
		);

		if ( 'all' !== $filters['status'] ) {
			$args['auction_status'] = $filters['status'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$args['auction_search'] = $filters['search'];
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Maybe output CSV export and exit.
	 *
	 * @param array $filters Current filters.
	 *
	 * @return void
	 */
	private function maybe_handle_csv_export( array $filters ): void {
		if ( empty( $_GET['auction_export'] ) || 'csv' !== sanitize_key( wp_unslash( $_GET['auction_export'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export auctions.', 'auction' ) );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, 'auction_export_csv' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'auction' ) );
		}

		$rows = $this->get_auction_rows( $filters );

		$filename = sprintf( 'auction-export-%s.csv', gmdate( 'Ymd-His' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		fputcsv(
			$output,
			array(
				__( 'Product ID', 'auction' ),
				__( 'Product Name', 'auction' ),
				__( 'SKU', 'auction' ),
				__( 'Status', 'auction' ),
				__( 'Auction Type', 'auction' ),
				__( 'Start Time', 'auction' ),
				__( 'End Time', 'auction' ),
				__( 'Start Price', 'auction' ),
				__( 'Current Bid', 'auction' ),
				__( 'Reserve Price', 'auction' ),
				__( 'Minimum Increment', 'auction' ),
				__( 'Buy Now Price', 'auction' ),
				__( 'Total Bids', 'auction' ),
				__( 'Active Bids', 'auction' ),
				__( 'Latest Bidder', 'auction' ),
				__( 'Latest Bid Amount', 'auction' ),
				__( 'Latest Bid Time', 'auction' ),
				__( 'Winner', 'auction' ),
				__( 'Winning Amount', 'auction' ),
				__( 'Stock Status', 'auction' ),
			)
		);

		$currency_symbol = get_woocommerce_currency_symbol();

		foreach ( $rows as $row ) {
			$current_bid = wc_format_decimal( $row['current_bid'], wc_get_price_decimals() );
			$start_price = wc_format_decimal( $row['start_price'], wc_get_price_decimals() );
			$reserve_price = $row['reserve_price'] > 0 ? wc_format_decimal( $row['reserve_price'], wc_get_price_decimals() ) : '';
			$min_increment = wc_format_decimal( $row['min_increment'], wc_get_price_decimals() );
			$buy_now_price = $row['buy_now_enabled'] && $row['buy_now_price'] > 0 ? wc_format_decimal( $row['buy_now_price'], wc_get_price_decimals() ) : '';
			$latest_bid_amount = null === $row['latest_amount']
				? ''
				: wc_format_decimal( $row['latest_amount'], wc_get_price_decimals() );
			$winner_amount = $row['winner_amount'] > 0 ? wc_format_decimal( $row['winner_amount'], wc_get_price_decimals() ) : '';

			fputcsv(
				$output,
				array(
					$row['product_id'],
					$row['product_name'],
					$row['product_sku'],
					$this->get_status_label( $row['status'] ),
					$row['auction_type'],
					$row['start_time'],
					$row['end_time'],
					trim( $currency_symbol . ' ' . $start_price ),
					trim( $currency_symbol . ' ' . $current_bid ),
					$reserve_price ? trim( $currency_symbol . ' ' . $reserve_price ) : __( 'Not set', 'auction' ),
					trim( $currency_symbol . ' ' . $min_increment ),
					$buy_now_price ? trim( $currency_symbol . ' ' . $buy_now_price ) : __( 'Disabled', 'auction' ),
					$row['total_bids'],
					$row['active_bids'],
					$row['latest_name'],
					$latest_bid_amount ? trim( $currency_symbol . ' ' . $latest_bid_amount ) : '',
					$row['latest_time'],
					$row['winner_name'] ?: __( 'N/A', 'auction' ),
					$winner_amount ? trim( $currency_symbol . ' ' . $winner_amount ) : '',
					ucfirst( $row['stock_status'] ),
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Collect auction rows based on filters.
	 *
	 * @param array $filters Filters array.
	 *
	 * @return array
	 */
	private function get_auction_rows( array $filters ): array {
		global $wpdb;

		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'future', 'draft' ),
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_auction_enabled',
					'value' => 'yes',
				),
			),
		);

		if ( ! empty( $filters['search'] ) ) {
			$query_args['s'] = $filters['search'];
		}

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return array();
		}

		$rows = array();
		$bids_table = Auction_Install::get_bids_table_name();

		while ( $query->have_posts() ) {
			$query->the_post();

			$product = wc_get_product( get_the_ID() );

			if ( ! $product ) {
				continue;
			}

			$config = Auction_Product_Helper::get_config( $product );
			$state  = Auction_Product_Helper::get_runtime_state( $product );
			$status = Auction_Product_Helper::get_auction_status( $config );

			if ( 'all' !== $filters['status'] && $status !== $filters['status'] ) {
				continue;
			}

			$latest_bid    = Auction_Bid_Manager::get_leading_bid( $product->get_id() );
			$latest_name   = __( '—', 'auction' );
			$latest_amount = null;
			$latest_time   = __( 'N/A', 'auction' );
			$latest_user_id = null;

			if ( $latest_bid ) {
				$latest_name   = $this->format_bidder_name_admin( $latest_bid, $config );
				$latest_amount = Auction_Product_Helper::to_float( $latest_bid['bid_amount'] ?? 0 );
				$latest_user_id = absint( $latest_bid['user_id'] ?? 0 );

				if ( ! empty( $latest_bid['created_at'] ) ) {
					$latest_time = wp_date(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						strtotime( $latest_bid['created_at'] )
					);
				}
			}

			$start_time = $config['start_timestamp']
				? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $config['start_timestamp'] )
				: __( 'Not set', 'auction' );
			$end_time   = $config['end_timestamp']
				? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $config['end_timestamp'] )
				: __( 'Not set', 'auction' );

			$current_bid = $state['winning_bid_id']
				? Auction_Product_Helper::to_float( $state['current_bid'] ?? 0 )
				: Auction_Product_Helper::get_start_price( $config );

			// Get total bid count
			$total_bids = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$bids_table} WHERE product_id = %d",
					$product->get_id()
				)
			);

			// Get active bid count
			$active_bids = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$bids_table} WHERE product_id = %d AND status = 'active'",
					$product->get_id()
				)
			);

			// Get winner information if auction ended
			$winner_user_id = absint( $product->get_meta( '_auction_winner_user_id', true ) );
			$winner_name = '';
			$winner_amount = 0;
			$winner_time = '';

			if ( $winner_user_id ) {
				$user = get_user_by( 'id', $winner_user_id );
				if ( $user ) {
					$winner_name = $user->display_name ?: $user->user_login;
				}
				$winner_amount = Auction_Product_Helper::to_float( $product->get_meta( '_auction_winner_amount', true ) );
				$winner_time_raw = $product->get_meta( '_auction_winner_time', true );
				if ( $winner_time_raw ) {
					$winner_time = wp_date(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						strtotime( $winner_time_raw )
					);
				}
			}

			// Get product details
			$product_sku = $product->get_sku();
			$stock_status = $product->get_stock_status();
			$stock_quantity = $product->get_stock_quantity();

			$rows[] = array(
				'product_id'        => $product->get_id(),
				'product_name'      => $product->get_name(),
				'product_sku'       => $product_sku ?: __( 'N/A', 'auction' ),
				'edit_link'         => get_edit_post_link( $product->get_id() ),
				'view_link'         => get_permalink( $product->get_id() ),
				'status'            => $status,
				'start_time'        => $start_time,
				'end_time'          => $end_time,
				'start_timestamp'   => $config['start_timestamp'],
				'end_timestamp'     => $config['end_timestamp'],
				'start_price'       => Auction_Product_Helper::to_float( $config['start_price'] ?? 0 ),
				'reserve_price'     => Auction_Product_Helper::to_float( $config['reserve_price'] ?? 0 ),
				'min_increment'     => Auction_Product_Helper::to_float( $config['min_increment'] ?? 0 ),
				'buy_now_enabled'  => $config['buy_now_enabled'] ?? false,
				'buy_now_price'     => Auction_Product_Helper::to_float( $config['buy_now_price'] ?? 0 ),
				'auction_type'      => $config['sealed'] ? __( 'Sealed', 'auction' ) : __( 'Standard', 'auction' ),
				'condition'         => $config['condition'] ?? __( 'N/A', 'auction' ),
				'automatic_bidding' => $config['automatic_bidding'] ?? false,
				'bid_increment_mode' => $config['bid_increment_mode'] ?? 'simple',
				'current_bid'        => Auction_Product_Helper::to_float( $current_bid ),
				'latest_name'        => $latest_name,
				'latest_amount'      => $latest_amount,
				'latest_time'       => $latest_time,
				'latest_user_id'    => $latest_user_id,
				'total_bids'         => absint( $total_bids ),
				'active_bids'       => absint( $active_bids ),
				'winner_user_id'    => $winner_user_id,
				'winner_name'        => $winner_name,
				'winner_amount'      => $winner_amount,
				'winner_time'        => $winner_time,
				'stock_status'       => $stock_status,
				'stock_quantity'    => $stock_quantity !== null ? $stock_quantity : __( 'N/A', 'auction' ),
				'proxy_max'         => Auction_Product_Helper::to_float( $state['proxy_max'] ?? 0 ),
				'proxy_user_id'     => absint( $state['proxy_user_id'] ?? 0 ),
			);
		}

		wp_reset_postdata();

		return $rows;
	}

	/**
	 * Human readable label for auction status.
	 *
	 * @param string $status Status slug.
	 *
	 * @return string
	 */
	private function get_status_label( string $status ): string {
		switch ( $status ) {
			case 'scheduled':
				return __( 'Scheduled', 'auction' );
			case 'ended':
				return __( 'Ended', 'auction' );
			default:
				return __( 'Active', 'auction' );
		}
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'auction' ) );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['auction_settings_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->handle_settings_save();
		}

		$schema   = $this->get_settings_schema();
		$settings = $this->get_settings();

		settings_errors( 'auction_settings' );
		?>
		<div class="wrap auction-admin-wrap">
			<h1><?php esc_html_e( 'Auction Settings', 'auction' ); ?></h1>

			<form method="post">
				<?php wp_nonce_field( 'auction_save_settings', 'auction_settings_nonce' ); ?>

				<div class="nav-tab-wrapper">
					<?php
					$tab_index = 0;
					foreach ( $schema as $section_id => $section ) :
						$active_class = 0 === $tab_index ? ' nav-tab-active' : '';
						?>
						<a href="#<?php echo esc_attr( $section_id ); ?>" class="nav-tab<?php echo esc_attr( $active_class ); ?>">
							<?php echo esc_html( $section['title'] ); ?>
						</a>
						<?php
						$tab_index++;
					endforeach;
					?>
				</div>

				<?php foreach ( $schema as $section_id => $section ) : ?>
					<div class="auction-admin-card" id="<?php echo esc_attr( $section_id ); ?>">
						<h2><?php echo esc_html( $section['title'] ); ?></h2>
						<?php if ( ! empty( $section['description'] ) ) : ?>
							<p class="description"><?php echo esc_html( $section['description'] ); ?></p>
						<?php endif; ?>

						<?php if ( empty( $section['fields'] ) ) : ?>
							<p class="description">
								<?php esc_html_e( 'This section will be completed in a future update.', 'auction' ); ?>
							</p>
						<?php else : ?>
							<?php foreach ( $section['fields'] as $field_key => $field_config ) : ?>
								<?php echo $this->render_settings_field( $field_key, $field_config, $settings[ $field_key ] ?? ( $field_config['default'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<?php submit_button( __( 'Save Settings', 'auction' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle settings save request.
	 *
	 * @return void
	 */
	private function handle_settings_save(): void {
		if ( ! isset( $_POST['auction_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['auction_settings_nonce'] ), 'auction_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'auction' ) );
		}

		$raw_settings = isset( $_POST['auction_settings'] ) ? wp_unslash( $_POST['auction_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_array( $raw_settings ) ) {
			$raw_settings = array();
		}

		$sanitized = $this->sanitize_settings( $raw_settings );

		update_option( $this->option_name, $sanitized );
		$this->settings_cache = $sanitized;

		add_settings_error(
			'auction_settings',
			'auction_settings_saved',
			__( 'Settings saved successfully.', 'auction' ),
			'updated'
		);
	}

	/**
	 * Render individual settings field.
	 *
	 * @param string $field_key Field key.
	 * @param array  $config    Field configuration.
	 * @param mixed  $value     Current value.
	 *
	 * @return string
	 */
	private function render_settings_field( string $field_key, array $config, $value ): string {
		$id          = 'auction_settings_' . $field_key;
		$name        = 'auction_settings[' . $field_key . ']';
		$label       = $config['label'] ?? '';
		$description = $config['description'] ?? '';
		$type        = $config['type'] ?? 'text';

		ob_start();
		?>
		<div class="auction-setting-field">
			<?php if ( 'checkbox' === $type ) : ?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input
						type="checkbox"
						name="<?php echo esc_attr( $name ); ?>"
						id="<?php echo esc_attr( $id ); ?>"
						value="yes"
						<?php checked( 'yes', $value ); ?>
					/>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php else : ?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
				<div class="auction-setting-control">
					<?php
					switch ( $type ) {
						case 'select':
							?>
							<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
								<?php foreach ( $config['options'] as $option_value => $option_label ) : ?>
									<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $option_value, $value ); ?>>
										<?php echo esc_html( $option_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php
							break;
						case 'radio':
							foreach ( $config['options'] as $option_value => $option_label ) :
								?>
								<label class="auction-radio-option">
									<input
										type="radio"
										name="<?php echo esc_attr( $name ); ?>"
										value="<?php echo esc_attr( $option_value ); ?>"
										<?php checked( $option_value, $value ); ?>
									/>
									<?php echo esc_html( $option_label ); ?>
								</label>
								<?php
							endforeach;
							break;
						case 'number':
						case 'integer':
							?>
							<input
								type="number"
								name="<?php echo esc_attr( $name ); ?>"
								id="<?php echo esc_attr( $id ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
								<?php
								if ( ! empty( $config['attributes'] ) ) {
									foreach ( $config['attributes'] as $attr_key => $attr_value ) {
										echo esc_attr( $attr_key ) . '="' . esc_attr( $attr_value ) . '" ';
									}
								}
								?>
							/>
							<?php
							break;
						case 'textarea':
							?>
							<textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" rows="4"><?php echo esc_textarea( $value ); ?></textarea>
							<?php
							break;
						case 'editor':
							wp_editor(
								$value,
								$id,
								array(
									'textarea_name' => $name,
									'textarea_rows' => 10,
									'media_buttons' => true,
									'teeny'         => false,
									'tinymce'       => true,
									'quicktags'     => true,
								)
							);
							break;
						case 'color':
							?>
							<input
								type="text"
								class="auction-color-field"
								name="<?php echo esc_attr( $name ); ?>"
								id="<?php echo esc_attr( $id ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
								placeholder="#000000"
							/>
							<?php
							break;
						case 'media':
							?>
							<div class="auction-media-control">
								<input
									type="text"
									name="<?php echo esc_attr( $name ); ?>"
									id="<?php echo esc_attr( $id ); ?>"
									class="regular-text auction-media-url"
									value="<?php echo esc_attr( $value ); ?>"
								/>
								<button
									type="button"
									class="button auction-media-upload"
									data-target="<?php echo esc_attr( $id ); ?>"
								>
									<?php esc_html_e( 'Upload', 'auction' ); ?>
								</button>
								<button
									type="button"
									class="button-link auction-media-clear"
									data-target="<?php echo esc_attr( $id ); ?>"
								>
									<?php esc_html_e( 'Clear', 'auction' ); ?>
								</button>
							</div>
							<?php
							break;
						default:
							?>
							<input
								type="text"
								name="<?php echo esc_attr( $name ); ?>"
								id="<?php echo esc_attr( $id ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
							/>
							<?php
							break;
					}
					?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $description ) ) : ?>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param array $input Raw input.
	 *
	 * @return array
	 */
	private function sanitize_settings( array $input ): array {
		$schema    = $this->get_settings_schema();
		$sanitized = $this->get_default_settings();

		foreach ( $schema as $section ) {
			foreach ( $section['fields'] as $field_key => $field_config ) {
				$value = $input[ $field_key ] ?? null;
				$sanitized[ $field_key ] = $this->sanitize_field( $value, $field_config );
			}
		}

		return $sanitized;
	}

	/**
	 * Format bidder name for admin list.
	 *
	 * @param array $record Bid record.
	 * @param array $config Auction configuration.
	 *
	 * @return string
	 */
	private function format_bidder_name_admin( array $record, array $config ): string {
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

	/**
	 * Sanitize individual field.
	 *
	 * @param mixed $value  Field value.
	 * @param array $config Field configuration.
	 *
	 * @return mixed
	 */
	private function sanitize_field( $value, array $config ) {
		$type    = $config['type'] ?? 'text';
		$default = $config['default'] ?? '';

		switch ( $type ) {
			case 'checkbox':
				return ! empty( $value ) && 'yes' === $value ? 'yes' : 'no';

			case 'select':
			case 'radio':
				$options = $config['options'] ?? array();
				$value   = is_string( $value ) ? sanitize_text_field( $value ) : $default;
				return array_key_exists( $value, $options ) ? $value : $default;

			case 'number':
				return is_numeric( $value ) ? floatval( $value ) : ( is_numeric( $default ) ? floatval( $default ) : 0 );

			case 'integer':
				return is_numeric( $value ) ? absint( $value ) : absint( $default );

			case 'textarea':
				return is_string( $value ) ? sanitize_textarea_field( $value ) : $default;

			case 'editor':
				// Allow HTML content but sanitize it
				return is_string( $value ) ? wp_kses_post( $value ) : $default;

			case 'color':
				$value = is_string( $value ) ? sanitize_hex_color( $value ) : '';
				return $value ? $value : '';

			default:
				return is_string( $value ) ? sanitize_text_field( $value ) : $default;
		}
	}

	/**
	 * Retrieve settings from database merged with defaults.
	 *
	 * @return array
	 */
	private function get_settings(): array {
		if ( null !== $this->settings_cache ) {
			return $this->settings_cache;
		}

		$stored = get_option( $this->option_name, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$this->settings_cache = wp_parse_args( $stored, $this->get_default_settings() );

		return $this->settings_cache;
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	private function get_default_settings(): array {
		$defaults = array();

		foreach ( $this->get_settings_schema() as $section ) {
			foreach ( $section['fields'] as $field_key => $field_config ) {
				$defaults[ $field_key ] = $field_config['default'] ?? ( 'checkbox' === ( $field_config['type'] ?? '' ) ? 'no' : '' );
			}
		}

		return $defaults;
	}

	/**
	 * Settings schema definition.
	 *
	 * @return array
	 */
	private function get_settings_schema(): array {
		return array(
			'auction_options'      => array(
				'title'       => __( 'Auction Options', 'auction' ),
				'description' => __( 'Global settings for all auctions.', 'auction' ),
				'fields'      => array(
					'show_on_shop'                  => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show auctions on the shop page', 'auction' ),
						'description' => __( 'Enable to show auction products in the shop page. If disabled, all auctions will be shown only in the page with the auction shortcode.', 'auction' ),
						'default'     => 'yes',
					),
					'product_display_mode'          => array(
						'type'        => 'select',
						'label'       => __( 'Product display mode', 'auction' ),
						'description' => __( 'Choose which products to display on the shop page: only buy products (without auction), only auction products, auction products with buy now button, or auction products without buy now option.', 'auction' ),
						'options'     => array(
							'all'                    => __( 'All products (default)', 'auction' ),
							'buy_only'               => __( 'Only buy products (without auction)', 'auction' ),
							'auction_only'           => __( 'Only auction products', 'auction' ),
							'auction_with_buy_now'   => __( 'Auction products with buy now button', 'auction' ),
							'auction_without_buy_now' => __( 'Auction products without buy now option', 'auction' ),
						),
						'default'     => 'all',
					),
					'hide_out_of_stock'             => array(
						'type'        => 'checkbox',
						'label'       => __( 'Hide out-of-stock auctions', 'auction' ),
						'description' => __( 'Enable to hide out-of-stock auctions in the shop pages.', 'auction' ),
					),
					'hide_ended'                    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Hide ended auctions', 'auction' ),
						'description' => __( 'Enable to hide ended auctions in the shop page.', 'auction' ),
					),
					'hide_future'                   => array(
						'type'        => 'checkbox',
						'label'       => __( 'Hide future auctions', 'auction' ),
						'description' => __( 'Enable to hide auctions, that have not yet started, in the shop page.', 'auction' ),
					),
					'show_countdown_loop'           => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show countdown in product loop', 'auction' ),
						'description' => __( 'Enable to show auction countdown or end time also in the shop pages.', 'auction' ),
					),
					'hide_buy_now_over_bid'         => array(
						'type'        => 'checkbox',
						'label'       => __( "Hide 'Buy Now' when bid exceeds price", 'auction' ),
						'description' => __( "Enable to hide the 'Buy Now' button when a user bids an amount that exceeds the 'Buy Now' price.", 'auction' ),
					),
					'hide_buy_now_after_first_bid'  => array(
						'type'        => 'checkbox',
						'label'       => __( "Hide 'Buy Now' after first bid", 'auction' ),
						'description' => __( "Enable to hide the 'Buy Now' button when a user places the first bid.", 'auction' ),
					),
					'bid_type'                      => array(
						'type'        => 'select',
						'label'       => __( 'Set bid type', 'auction' ),
						'description' => __( 'Choose how automatic bidding works for your auctions.', 'auction' ),
						'options'     => array(
							'automatic' => __( 'Automatic bidding', 'auction' ),
							'simple'    => __( 'Simple bidding', 'auction' ),
						),
						'default'     => 'automatic',
					),
					'show_bid_increments'           => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show bid increments info', 'auction' ),
						'description' => __( 'Enable to show the automatic bid increment info on the page.', 'auction' ),
					),
					'bid_approval_modal'            => array(
						'type'        => 'checkbox',
						'label'       => __( 'Ask for approval before bid', 'auction' ),
						'description' => __( 'If enabled, bidders will see a confirmation modal before their bid is published.', 'auction' ),
					),
					'fee_before_bidding'            => array(
						'type'        => 'checkbox',
						'label'       => __( 'Ask fee payment before bidding', 'auction' ),
						'description' => __( 'Enable to ask users to pay a fee before placing a bid.', 'auction' ),
					),
					'enable_overtime'               => array(
						'type'        => 'checkbox',
						'label'       => __( 'Set overtime', 'auction' ),
						'description' => __( 'Enable to extend the auction duration if someone places a bid when the auction is about to end.', 'auction' ),
					),
					'show_highest_bidder_modal'     => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show higher bidder modal', 'auction' ),
						'description' => __( 'Enable to show a modal to the highest bidder suggesting to refresh the page.', 'auction' ),
					),
					'enable_watchlist'              => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable watchlist', 'auction' ),
						'description' => __( 'Allow logged-in users to create a watchlist with auctions they are interested in.', 'auction' ),
					),
					'allow_followers'               => array(
						'type'        => 'checkbox',
						'label'       => __( 'Allow users to follow auctions', 'auction' ),
						'description' => __( 'If enabled, users can receive a notification when an auction is about to end.', 'auction' ),
					),
					'email_new_bid'                 => array(
						'type'        => 'checkbox',
						'label'       => __( 'Email bidders on new bid', 'auction' ),
						'description' => __( 'Send an email to bidders to notify any new bid.', 'auction' ),
					),
					'email_ending'                  => array(
						'type'        => 'checkbox',
						'label'       => __( 'Email when auction about to end', 'auction' ),
						'description' => __( 'Send an email to bidders and followers when the auction is about to end.', 'auction' ),
					),
					'email_lost'                    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Email when bidder loses', 'auction' ),
						'description' => __( 'Send an email when bidders lose the auction.', 'auction' ),
					),
					'email_buy_now_closed'          => array(
						'type'        => 'checkbox',
						'label'       => __( 'Email when closed by Buy Now', 'auction' ),
						'description' => __( 'Send an email when the auction is closed by a Buy Now purchase.', 'auction' ),
					),
					'notify_ending_days'            => array(
						'type'        => 'integer',
						'label'       => __( 'Notify ending auctions before (days)', 'auction' ),
						'description' => __( 'Set when to send the email to notify bidders and followers that the auction is about to end.', 'auction' ),
						'default'     => 1,
						'attributes'  => array(
							'min' => '0',
						),
					),
					'show_unsubscribe_link'         => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show unsubscribe link in emails', 'auction' ),
						'description' => __( 'Enable to add an unsubscribe link in email notifications for bidders and followers.', 'auction' ),
					),
					'unsubscribe_label'             => array(
						'type'        => 'text',
						'label'       => __( 'Unsubscribe link label', 'auction' ),
						'description' => __( 'Set the label for the unsubscribe link in email notifications.', 'auction' ),
						'default'     => __( 'Unsubscribe', 'auction' ),
					),
					'auto_refresh_page'             => array(
						'type'        => 'checkbox',
						'label'       => __( 'Automatically refresh auction page', 'auction' ),
						'description' => __( 'Enable to automatically refresh the auction page via Ajax.', 'auction' ),
					),
					'auto_refresh_my_account'       => array(
						'type'        => 'checkbox',
						'label'       => __( 'Automatically refresh My Account > My auctions', 'auction' ),
						'description' => __( 'Enable to automatically refresh the "My auctions" section via Ajax.', 'auction' ),
					),
					'show_login_modal'              => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show login/register modal', 'auction' ),
						'description' => __( 'Allow users to login or register directly from the auction page.', 'auction' ),
					),
				),
			),
			'auction_frontend_content' => array(
				'title'       => __( 'Auction Frontend Content', 'auction' ),
				'description' => __( 'Content shown in the Dates & Times and Terms & Conditions tabs on the main auctions page.', 'auction' ),
				'fields'      => array(
					'dates_times_content' => array(
						'type'        => 'editor',
						'label'       => __( 'Dates & Times content', 'auction' ),
						'description' => __( 'Text or HTML shown in the "Dates & Times" tab on the auction listing page.', 'auction' ),
						'default'     => '',
					),
					'terms_content'       => array(
						'type'        => 'editor',
						'label'       => __( 'Terms & Conditions content', 'auction' ),
						'description' => __( 'Text or HTML shown in the "Terms & Conditions" tab on the auction listing page.', 'auction' ),
						'default'     => '',
					),
				),
			),
			'auctions_payments'   => array(
				'title'       => __( 'Auctions Payments', 'auction' ),
				'description' => __( 'Options related to the management and payment of won auctions. (Coming soon)', 'auction' ),
				'fields'      => array(),
			),
			'auctions_reschedule' => array(
				'title'       => __( 'Auctions Rescheduling', 'auction' ),
				'description' => __( 'Set the general conditions to reschedule auctions.', 'auction' ),
				'fields'      => array(
					'reschedule_without_bids'        => array(
						'type'        => 'checkbox',
						'label'       => __( 'Reschedule ended auctions without bids', 'auction' ),
						'description' => __( 'Enable to automatically reschedule ended auctions without a bid.', 'auction' ),
					),
					'reschedule_reserve_not_met'     => array(
						'type'        => 'checkbox',
						'label'       => __( 'Reschedule reserve-not-met auctions', 'auction' ),
						'description' => __( 'Enable to automatically reschedule ended auctions if the reserve price was not reached.', 'auction' ),
					),
					'reschedule_length'              => array(
						'type'        => 'text',
						'label'       => __( 'Reschedule duration', 'auction' ),
						'description' => __( 'Set the length of time for which the auction will run again (e.g. "3 days").', 'auction' ),
					),
					'manage_unpaid'                  => array(
						'type'        => 'checkbox',
						'label'       => __( 'Manage unpaid auctions', 'auction' ),
						'description' => __( 'Enable to choose how to manage unpaid auctions (reschedule, contact the 2nd highest bidder, etc.).', 'auction' ),
					),
					'unpaid_action'                  => array(
						'type'        => 'select',
						'label'       => __( 'Unpaid auctions options', 'auction' ),
						'description' => __( 'Set how to manage unpaid auctions.', 'auction' ),
						'options'     => array(
							'reschedule'            => __( 'Reschedule the auction', 'auction' ),
							'contact_second_bidder' => __( 'Contact the 2nd highest bidder', 'auction' ),
							'nothing'               => __( 'Do nothing', 'auction' ),
						),
						'default'     => 'reschedule',
					),
					'unpaid_threshold_value'         => array(
						'type'        => 'integer',
						'label'       => __( 'Unpaid threshold value', 'auction' ),
						'description' => __( 'If winning bidder does not pay within this time, trigger the unpaid action.', 'auction' ),
						'default'     => 20,
						'attributes'  => array(
							'min' => '0',
						),
					),
					'unpaid_threshold_unit'          => array(
						'type'        => 'select',
						'label'       => __( 'Unpaid threshold unit', 'auction' ),
						'description' => __( 'Choose the time unit for the unpaid threshold.', 'auction' ),
						'options'     => array(
							'minutes' => __( 'Minutes', 'auction' ),
							'hours'   => __( 'Hours', 'auction' ),
							'days'    => __( 'Days', 'auction' ),
						),
						'default'     => 'minutes',
					),
					'unpaid_reschedule_length'       => array(
						'type'        => 'text',
						'label'       => __( 'Unpaid reschedule duration', 'auction' ),
						'description' => __( 'Set the length of time for which the auction will run again if rescheduled.', 'auction' ),
					),
					'notify_admin_rescheduled'       => array(
						'type'        => 'checkbox',
						'label'       => __( 'Send email to admin when rescheduled', 'auction' ),
						'description' => __( 'Enable to notify admin by email when an auction is automatically rescheduled.', 'auction' ),
					),
				),
			),
			'auction_page'        => array(
				'title'       => __( 'Auction Page', 'auction' ),
				'description' => __( 'Customization options for the auction product page.', 'auction' ),
				'fields'      => array(
					'show_badge_product'      => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show auction badge on product image', 'auction' ),
						'description' => __( 'Enable to show the auction badge in the auction product page.', 'auction' ),
					),
					'show_items_condition'    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show items condition', 'auction' ),
						'description' => __( 'Enable to show the item condition.', 'auction' ),
					),
					'show_product_stock'      => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show product stock', 'auction' ),
						'description' => __( 'Enable to show the product stock.', 'auction' ),
					),
					'show_reserve_reached'    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show reserve price notice', 'auction' ),
						'description' => __( 'Enable to show a notice if the reserve price has been reached.', 'auction' ),
					),
					'show_overtime_notice'    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show overtime notice', 'auction' ),
						'description' => __( 'Enable to show a notice if the auction is in overtime.', 'auction' ),
					),
					'bid_quantity_buttons'    => array(
						'type'        => 'select',
						'label'       => __( 'Quantity buttons in bid amount fields', 'auction' ),
						'description' => __( 'Choose to show or hide the buttons to increase or decrease the bid input.', 'auction' ),
						'options'     => array(
							'hide'   => __( 'Hide quantity buttons', 'auction' ),
							'theme'  => __( 'Use theme style buttons', 'auction' ),
							'plugin' => __( 'Use plugin style buttons', 'auction' ),
						),
						'default'     => 'theme',
					),
					'show_next_bid_amount'    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show next available amount', 'auction' ),
						'description' => __( 'Enable to show the suggested bid (current bid + minimal increment) in the bid input field.', 'auction' ),
					),
					'bid_username_display'    => array(
						'type'        => 'select',
						'label'       => __( 'In bid tab show', 'auction' ),
						'description' => __( 'Choose whether to show the full username of bidders or only the first and last letters.', 'auction' ),
						'options'     => array(
							'full'   => __( 'Full username', 'auction' ),
							'masked' => __( 'Only first and last letter (A****E)', 'auction' ),
						),
						'default'     => 'masked',
					),
					'show_end_reason'         => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show how the auction has ended', 'auction' ),
						'description' => __( 'If enabled, the reason why the auction has ended will be shown on the auction page.', 'auction' ),
					),
					'suggest_other_auctions'  => array(
						'type'        => 'checkbox',
						'label'       => __( 'Suggest other auctions', 'auction' ),
						'description' => __( 'Enable to suggest other auctions to customers that open an ended auction product page.', 'auction' ),
					),
					'suggest_filter'          => array(
						'type'        => 'select',
						'label'       => __( 'Suggest active auctions', 'auction' ),
						'description' => __( 'Choose to suggest auctions of the same category or all categories.', 'auction' ),
						'options'     => array(
							'same_category'  => __( 'Of same category', 'auction' ),
							'all_categories' => __( 'Of all categories', 'auction' ),
						),
						'default'     => 'same_category',
					),
					'suggest_limit'           => array(
						'type'        => 'integer',
						'label'       => __( 'Auctions to suggest', 'auction' ),
						'description' => __( 'Set how many auctions to suggest.', 'auction' ),
						'default'     => 3,
						'attributes'  => array(
							'min' => '0',
						),
					),
				),
			),
			'customization'       => array(
				'title'       => __( 'Customization', 'auction' ),
				'description' => __( 'Display and countdown customization.', 'auction' ),
				'fields'      => array(
					'custom_badge_enable'            => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show auction badge', 'auction' ),
						'description' => __( 'Enable to show a badge to identify auction products.', 'auction' ),
					),
					'custom_badge_asset'             => array(
					'type'        => 'media',
						'label'       => __( 'Badge image URL', 'auction' ),
						'description' => __( 'Upload or paste the URL of the graphic badge used to identify auctions.', 'auction' ),
					),
					'show_end_date_on_product'       => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show end date on product page', 'auction' ),
						'description' => __( 'Enable to show the end date of auctions on the product page.', 'auction' ),
					),
					'timezone'                       => array(
						'type'        => 'text',
						'label'       => __( 'Time zone', 'auction' ),
						'description' => __( 'Enter an optional time zone code to show with the auction end date.', 'auction' ),
					),
					'date_format'                    => array(
						'type'        => 'text',
						'label'       => __( 'Date format', 'auction' ),
						'description' => __( 'Set date format for the countdown.', 'auction' ),
						'default'     => 'Y-m-d',
					),
					'time_format'                    => array(
						'type'        => 'text',
						'label'       => __( 'Time format', 'auction' ),
						'description' => __( 'Set time format for the countdown.', 'auction' ),
						'default'     => 'H:i',
					),
					'show_countdown'                 => array(
						'type'        => 'checkbox',
						'label'       => __( 'Show countdown', 'auction' ),
						'description' => __( 'Enable to show the countdown.', 'auction' ),
					),
					'countdown_style'                => array(
						'type'        => 'select',
						'label'       => __( 'Countdown style', 'auction' ),
						'description' => __( 'Choose a countdown style.', 'auction' ),
						'options'     => array(
							'default' => __( 'Default', 'auction' ),
							'compact' => __( 'Compact', 'auction' ),
							'boxed'   => __( 'Boxed', 'auction' ),
						),
						'default'     => 'default',
					),
					'countdown_color_text'           => array(
						'type'        => 'color',
						'label'       => __( 'Countdown text color', 'auction' ),
						'description' => __( 'Set the countdown text color.', 'auction' ),
					),
					'countdown_color_section_bg'     => array(
						'type'        => 'color',
						'label'       => __( 'Countdown section background', 'auction' ),
						'description' => __( 'Set the countdown section background color.', 'auction' ),
					),
					'countdown_color_blocks_bg'      => array(
						'type'        => 'color',
						'label'       => __( 'Countdown blocks background', 'auction' ),
						'description' => __( 'Set the countdown blocks background color.', 'auction' ),
					),
					'countdown_color_ending_text'    => array(
						'type'        => 'color',
						'label'       => __( 'Ending soon countdown text color', 'auction' ),
						'description' => __( 'Change countdown text color if the auction is near the end.', 'auction' ),
					),
					'countdown_ending_threshold'     => array(
						'type'        => 'integer',
						'label'       => __( 'Ending soon threshold (hours)', 'auction' ),
						'description' => __( 'Set the number of hours before ending to apply the ending soon color.', 'auction' ),
						'default'     => 24,
						'attributes'  => array(
							'min' => '0',
						),
					),
				),
			),
		);
	}
}

Auction_Admin_Menu::instance();

