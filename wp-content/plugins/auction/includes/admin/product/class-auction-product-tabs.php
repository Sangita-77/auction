<?php
/**
 * WooCommerce product data integrations.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles WooCommerce product data panels for auctions.
 */
class Auction_Product_Tabs {

	/**
	 * Singleton instance.
	 *
	 * @var Auction_Product_Tabs|null
	 */
	private static $instance = null;

	/**
	 * Meta prefix.
	 *
	 * @var string
	 */
	private $meta_prefix = '_auction_';

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->hooks();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Auction_Product_Tabs
	 */
	public static function instance(): Auction_Product_Tabs {
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
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'register_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_meta' ) );
	}

	/**
	 * Register Auction tab within WooCommerce product data panels.
	 *
	 * @param array $tabs Existing tabs.
	 *
	 * @return array
	 */
	public function register_product_tab( array $tabs ): array {
		$tabs['auction'] = array(
			'label'    => __( 'Auction', 'auction' ),
			'target'   => 'auction_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable', 'show_if_external', 'show_if_grouped' ),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Get product object helper.
	 *
	 * @return WC_Product|null
	 */
	private function get_current_product(): ?WC_Product {
		global $post;

		if ( empty( $post ) ) {
			return null;
		}

		return wc_get_product( $post->ID );
	}

	/**
	 * Render Auction panel HTML.
	 *
	 * @return void
	 */
	public function render_product_panel(): void {
		$product = $this->get_current_product();

		$values = array();

		foreach ( $this->get_meta_fields_flat() as $field_key => $config ) {
			$meta_key        = $this->meta_prefix . $field_key;
			$default         = $config['default'] ?? '';
			$values[ $field_key ] = $product ? $product->get_meta( $meta_key, true ) : $default;

			if ( '' === $values[ $field_key ] && isset( $config['default'] ) ) {
				$values[ $field_key ] = $config['default'];
			}
		}

		$values['automatic_increment_rules'] = $this->normalize_rules_value( $values['automatic_increment_rules'] ?? array() );

		include __DIR__ . '/views/html-auction-product-panel.php';
	}

	/**
	 * Normalize saved rules data.
	 *
	 * @param mixed $raw_value Raw value.
	 *
	 * @return array
	 */
	private function normalize_rules_value( $raw_value ): array {
		if ( is_string( $raw_value ) ) {
			$json = json_decode( wp_unslash( $raw_value ), true );
			if ( is_array( $json ) ) {
				$raw_value = $json;
			}
		}

		if ( ! is_array( $raw_value ) ) {
			return array();
		}

		$rules = array();

		foreach ( $raw_value as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$rules[] = array(
				'from'      => $rule['from'] ?? '',
				'to'        => $rule['to'] ?? '',
				'increment' => $rule['increment'] ?? '',
			);
		}

		return $rules;
	}

	/**
	 * Save product meta on update.
	 *
	 * @param WC_Product $product Product object.
	 *
	 * @return void
	 */
	public function save_product_meta( WC_Product $product ): void {
		$meta_fields = $this->get_meta_fields_flat();

		foreach ( $meta_fields as $field_key => $config ) {
			$meta_key = $this->meta_prefix . $field_key;

			switch ( $config['type'] ) {
				case 'checkbox':
					$value = isset( $_POST[ $meta_key ] ) ? 'yes' : 'no';
					break;
				case 'number':
					$value = isset( $_POST[ $meta_key ] ) ? wc_clean( wp_unslash( $_POST[ $meta_key ] ) ) : '';
					if ( '' !== $value ) {
						$value = (float) $value;
					}
					break;
				case 'datetime':
					$value = isset( $_POST[ $meta_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) : '';
					if ( $value ) {
						$timestamp = strtotime( $value );
						if ( $timestamp ) {
							$value = gmdate( 'Y-m-d H:i:s', $timestamp );
						}
					}
					break;
				case 'select':
				case 'text':
					$value = isset( $_POST[ $meta_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) : '';
					break;
				case 'rules':
					$value = array();
					if ( isset( $_POST[ $meta_key ] ) && is_array( $_POST[ $meta_key ] ) ) {
						foreach ( $_POST[ $meta_key ] as $rule ) {
							if ( empty( $rule['increment'] ) ) {
								continue;
							}

							$value[] = array(
								'from'      => sanitize_text_field( wp_unslash( $rule['from'] ?? '' ) ),
								'to'        => sanitize_text_field( wp_unslash( $rule['to'] ?? '' ) ),
								'increment' => sanitize_text_field( wp_unslash( $rule['increment'] ?? '' ) ),
							);
						}
					}
					break;
				default:
					$value = isset( $_POST[ $meta_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) : '';
					break;
			}

			if ( 'checkbox' === $config['type'] && 'no' === $value && empty( $config['persist_false'] ) ) {
				$product->delete_meta_data( $meta_key );
				continue;
			}

			if ( '' === $value && empty( $config['allow_empty'] ) ) {
				$product->delete_meta_data( $meta_key );
				continue;
			}

			$product->update_meta_data( $meta_key, $value );
		}

		$this->synchronize_catalog_price( $product );
	}

	/**
	 * Ensure auction products maintain a visible catalog price.
	 *
	 * @param WC_Product $product Product instance.
	 *
	 * @return void
	 */
	private function synchronize_catalog_price( WC_Product $product ): void {
		$is_enabled = 'yes' === $product->get_meta( $this->meta_prefix . 'enabled', true );

		if ( ! $is_enabled ) {
			return;
		}

		$start_price = Auction_Product_Helper::to_float(
			$product->get_meta( $this->meta_prefix . 'start_price', true )
		);

		$current_bid = Auction_Product_Helper::to_float(
			$product->get_meta( $this->meta_prefix . 'current_bid', true )
		);

		$visible_price = $current_bid > 0 ? $current_bid : $start_price;

		if ( $visible_price <= 0 ) {
			return;
		}

		if ( '' === $product->get_regular_price() || $product->get_regular_price() <= 0 ) {
			$product->set_regular_price( $visible_price );
		}

		if ( '' === $product->get_price() || $product->get_price() <= 0 ) {
			$product->set_price( $visible_price );
		}

		if ( 'hidden' === $product->get_catalog_visibility() ) {
			$product->set_catalog_visibility( 'visible' );
		}
	}

	/**
	 * Flatten meta field config.
	 *
	 * @return array<string,array>
	 */
	private function get_meta_fields_flat(): array {
		$groups = $this->get_meta_fields();
		$flat   = array();

		foreach ( $groups as $fields ) {
			foreach ( $fields as $field_key => $config ) {
				$flat[ $field_key ] = $config;
			}
		}

		return $flat;
	}

	/**
	 * Render a WooCommerce field.
	 *
	 * @param string $field_key Field key.
	 * @param array  $config    Field configuration.
	 * @param array  $values    Field values.
	 *
	 * @return string
	 */
	public function render_field( string $field_key, array $config, array $values ): string {
		$meta_key = $this->meta_prefix . $field_key;
		$value    = $values[ $field_key ] ?? ( $config['default'] ?? '' );

		$defaults = array(
			'id'          => $meta_key,
			'label'       => $config['label'] ?? '',
			'description' => $config['description'] ?? '',
			'desc_tip'    => ! empty( $config['description'] ),
			'wrapper_class' => 'auction-field ' . ( $config['wrapper_class'] ?? '' ),
		);

		ob_start();

		switch ( $config['type'] ) {
			case 'checkbox':
				woocommerce_wp_checkbox(
					array_merge(
						$defaults,
						array(
							'value'   => 'yes' === $value ? 'yes' : 'no',
							'cbvalue' => 'yes',
						)
					)
				);
				break;
			case 'select':
				woocommerce_wp_select(
					array_merge(
						$defaults,
						array(
							'value'   => $value,
							'options' => $config['options'] ?? array(),
						)
					)
				);
				break;
			case 'number':
				woocommerce_wp_text_input(
					array_merge(
						$defaults,
						array(
							'type'              => 'number',
							'value'             => '' !== $value ? $value : '',
							'custom_attributes' => $config['attributes'] ?? array(),
						)
					)
				);
				break;
			case 'datetime':
				woocommerce_wp_text_input(
					array_merge(
						$defaults,
						array(
							'type'  => 'datetime-local',
							'value' => $this->format_datetime_value( $value ),
						)
					)
				);
				break;
			default:
				woocommerce_wp_text_input(
					array_merge(
						$defaults,
						array(
							'type'  => 'text',
							'value' => $value,
						)
					)
				);
				break;
		}

		return ob_get_clean();
	}

	/**
	 * Render rules table field.
	 *
	 * @param string $field_key Field key.
	 * @param array  $config    Field configuration.
	 * @param array  $values    Saved values.
	 *
	 * @return string
	 */
	public function render_rules_field( string $field_key, array $config, array $values ): string {
		$meta_key = $this->meta_prefix . $field_key;
		$rules    = $values[ $field_key ] ?? array();

		if ( empty( $rules ) ) {
			$rules = array();
		}

		ob_start();
		?>
		<p class="form-field">
			<label>
				<?php echo esc_html( $config['label'] ); ?>
			</label>
			<span class="description">
				<?php echo esc_html( $config['description'] ); ?>
			</span>
		</p>

		<table class="auction-rules-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Current bid from', 'auction' ); ?></th>
					<th><?php esc_html_e( 'to', 'auction' ); ?></th>
					<th><?php esc_html_e( 'Automatic increment', 'auction' ); ?></th>
					<th><?php esc_html_e( 'Action', 'auction' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $rules ) ) : ?>
					<?php foreach ( $rules as $index => $rule ) : ?>
						<tr>
							<td>
								<input type="number" step="0.01" min="0" name="<?php echo esc_attr( "{$meta_key}[{$index}][from]" ); ?>" value="<?php echo esc_attr( $rule['from'] ?? '' ); ?>" />
							</td>
							<td>
								<input type="number" step="0.01" min="0" name="<?php echo esc_attr( "{$meta_key}[{$index}][to]" ); ?>" value="<?php echo esc_attr( $rule['to'] ?? '' ); ?>" />
							</td>
							<td>
								<input type="number" step="0.01" min="0" name="<?php echo esc_attr( "{$meta_key}[{$index}][increment]" ); ?>" value="<?php echo esc_attr( $rule['increment'] ?? '' ); ?>" required />
							</td>
							<td class="actions">
								<button type="button" class="button-link-delete auction-remove-rule">
									<?php esc_html_e( 'Remove', 'auction' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr class="no-rules">
						<td colspan="4"><?php esc_html_e( 'No advanced rules defined yet.', 'auction' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<p>
			<button type="button" class="button auction-add-rule">
				<?php esc_html_e( 'Add rule', 'auction' ); ?>
			</button>
		</p>
		<?php

		return ob_get_clean();
	}

	/**
	 * Format datetime meta for input field.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private function format_datetime_value( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$timestamp = strtotime( $value );

		if ( ! $timestamp ) {
			return $value;
		}

		return gmdate( 'Y-m-d\TH:i', $timestamp );
	}

	/**
	 * Get meta fields definition.
	 *
	 * @return array
	 */
	private function get_meta_fields(): array {
		return array(
			'general' => array(
				'enabled'      => array(
					'type'        => 'checkbox',
					'label'       => __( 'Enable auction for this product', 'auction' ),
					'description' => __( 'Enable to turn this WooCommerce product into an auction listing.', 'auction' ),
					'default'     => 'no',
					'persist_false' => true,
				),
				'condition'    => array(
					'type'        => 'text',
					'label'       => __( 'Item condition', 'auction' ),
					'description' => __( 'Optional: Enter the item condition (new, used, damaged...).', 'auction' ),
				),
				'type'         => array(
					'type'        => 'select',
					'label'       => __( 'Auction type', 'auction' ),
					'description' => __( 'Choose the auction type. In a normal auction, the higher bid wins; in a reverse auction, the lower bid wins.', 'auction' ),
					'options'     => array(
						'normal'  => __( 'Normal auction', 'auction' ),
						'reverse' => __( 'Reverse auction', 'auction' ),
					),
					'default'     => 'normal',
				),
				'sealed'       => array(
					'type'        => 'checkbox',
					'label'       => __( 'Sealed auction', 'auction' ),
					'description' => __( 'Enable if you want to make this a sealed auction. All bids will be hidden.', 'auction' ),
					'default'     => 'no',
					'persist_false' => true,
				),
				'start_time'   => array(
					'type'        => 'datetime',
					'label'       => __( 'Start time', 'auction' ),
					'description' => __( 'Set the start time for this auction.', 'auction' ),
				),
				'end_time'     => array(
					'type'        => 'datetime',
					'label'       => __( 'End time', 'auction' ),
					'description' => __( 'Set the end time for this auction.', 'auction' ),
				),
			),
			'price'   => array(
				'start_price'              => array(
					'type'        => 'number',
					'label'       => __( 'Starting price', 'auction' ),
					'description' => __( 'Set a starting price for this auction.', 'auction' ),
					'attributes'  => array(
						'step' => '0.01',
						'min'  => '0',
					),
				),
				'min_increment'            => array(
					'type'        => 'number',
					'label'       => __( 'Minimum manual bid increment', 'auction' ),
					'description' => __( 'Set the minimum increment amount for manual bids. Note: if you enable automatic bidding, this value will be overridden by the automatic bid increment rules.', 'auction' ),
					'attributes'  => array(
						'step' => '0.01',
						'min'  => '0',
					),
				),
				'reserve_price'            => array(
					'type'        => 'number',
					'label'       => __( 'Reserve price', 'auction' ),
					'description' => __( 'Set the reserve price for this auction.', 'auction' ),
					'attributes'  => array(
						'step' => '0.01',
						'min'  => '0',
					),
				),
				'buy_now_enabled'          => array(
					'type'        => 'checkbox',
					'label'       => __( 'Enable Buy Now', 'auction' ),
					'description' => __( "Enable to show a 'Buy Now' button to allow users to buy this product without bidding.", 'auction' ),
					'default'     => 'no',
					'persist_false' => true,
				),
				'buy_now_price'            => array(
					'type'        => 'number',
					'label'       => __( 'Buy Now price', 'auction' ),
					'description' => __( 'Optional Buy Now price.', 'auction' ),
					'attributes'  => array(
						'step' => '0.01',
						'min'  => '0',
					),
				),
			),
			'extras'  => array(
				'override_bid_options'        => array(
					'type'        => 'checkbox',
					'label'       => __( 'Override global bid options', 'auction' ),
					'description' => __( 'Enable to override the global options and set specific bid type options for this auction.', 'auction' ),
					'default'     => 'no',
					'persist_false' => true,
				),
				'automatic_bidding'           => array(
					'type'        => 'checkbox',
					'label'       => __( 'Enable automatic bidding', 'auction' ),
					'description' => __( "With automatic bidding, the user enters the maximum amount they're willing to pay. The system will automatically bid for the user.", 'auction' ),
					'default'     => 'no',
					'persist_false' => true,
				),
				'bid_increment_mode'          => array(
					'type'        => 'select',
					'label'       => __( 'Automatic bid increment type', 'auction' ),
					'description' => __( 'Choose between simple and advanced automatic bid increments.', 'auction' ),
					'options'     => array(
						'simple'   => __( 'Simple increment', 'auction' ),
						'advanced' => __( 'Advanced rules', 'auction' ),
					),
					'default'     => 'simple',
				),
				'automatic_increment_value'   => array(
					'type'        => 'number',
					'label'       => __( 'Automatic bid increment', 'auction' ),
					'description' => __( 'Set the bidding increment for automatic bidding. Used when Simple increment type is selected.', 'auction' ),
					'attributes'  => array(
						'step' => '0.01',
						'min'  => '0',
					),
				),
				'automatic_increment_rules'   => array(
					'type'        => 'rules',
					'label'       => __( 'Advanced automatic bidding rules', 'auction' ),
					'description' => __( 'Create rules to set different bid increments based on the auction\'s current bid.', 'auction' ),
				),
				'override_fee_options'        => array(
					'type'        => 'checkbox',
					'label'       => __( 'Override fee options', 'auction' ),
					'description' => __( 'Enable to override the global options and set specific fee options for this auction.', 'auction' ),
					'default'     => 'no',
					'persist_false' => true,
				),
				'override_commission_options' => array(
					'type'        => 'checkbox',
					'label'       => __( 'Override commission options', 'auction' ),
					'description' => __( 'Enable to override the global options and set specific commission fee options for this auction.', 'auction' ),
					'default'     => 'no',
					'persist_false' => true,
				),
				'override_reschedule_options' => array(
					'type'        => 'checkbox',
					'label'       => __( 'Override rescheduling options', 'auction' ),
					'description' => __( 'Enable to override the global options and set specific rescheduling options for this auction.', 'auction' ),
					'default'     => 'no',
					'persist_false' => true,
				),
				'override_overtime_options'   => array(
					'type'        => 'checkbox',
					'label'       => __( 'Override overtime options', 'auction' ),
					'description' => __( 'Enable to override the global options and set specific overtime options for this auction.', 'auction' ),
					'default'     => 'no',
					'persist_false' => true,
				),
			),
		);
	}
}

Auction_Product_Tabs::instance();

