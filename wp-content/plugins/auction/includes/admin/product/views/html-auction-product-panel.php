<?php
/**
 * Auction product data panel.
 *
 * @var array $values Pre-populated meta values.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

$meta_prefix = $this->meta_prefix;
$field_groups = $this->get_meta_fields();
?>

<div id="auction_product_data" class="panel woocommerce_options_panel">
	<div class="auction-product-panel">
		<h2><?php esc_html_e( 'Auction Options', 'auction' ); ?></h2>

		<p><?php esc_html_e( 'Configure the auction settings for this product.', 'auction' ); ?></p>

		<div class="auction-subtabs" role="tablist">
			<button type="button" class="button button-secondary is-active" data-target="general">
				<?php esc_html_e( 'General', 'auction' ); ?>
			</button>
			<button type="button" class="button button-secondary" data-target="price">
				<?php esc_html_e( 'Price', 'auction' ); ?>
			</button>
			<button type="button" class="button button-secondary" data-target="extras">
				<?php esc_html_e( 'Extras', 'auction' ); ?>
			</button>
			<button type="button" class="button button-secondary" data-target="status">
				<?php esc_html_e( 'Status', 'auction' ); ?>
			</button>
		</div>

		<div class="auction-subtab-section is-active" data-section="general">
			<div class="options_group">
				<?php foreach ( $field_groups['general'] as $field_key => $config ) : ?>
					<?php echo $this->render_field( $field_key, $config, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="auction-subtab-section" data-section="price">
			<div class="options_group">
				<?php foreach ( $field_groups['price'] as $field_key => $config ) : ?>
					<?php echo $this->render_field( $field_key, $config, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="auction-subtab-section" data-section="extras">
			<p>
				<?php esc_html_e( 'Note: In this section, you can override the plugin\'s general settings and set specific settings for this auction product.', 'auction' ); ?>
			</p>
			<div class="options_group">
				<?php foreach ( $field_groups['extras'] as $field_key => $config ) : ?>
					<?php
					if ( 'automatic_increment_rules' === $field_key ) {
						echo $this->render_rules_field( $field_key, $config, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						continue;
					}
					echo $this->render_field( $field_key, $config, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="auction-subtab-section" data-section="status">
			<?php
			$enabled = ( $values['enabled'] ?? 'no' ) === 'yes';
			$status  = $enabled ? __( 'In progress - Reserve price not exceeded yet', 'auction' ) : __( 'Auction disabled for this product.', 'auction' );
			?>
			<p>
				<strong><?php esc_html_e( 'Auction status:', 'auction' ); ?></strong>
				<?php echo esc_html( $status ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Start time:', 'auction' ); ?></strong>
				<?php echo esc_html( $values['display_start'] ?? __( 'Not scheduled', 'auction' ) ); ?>
			</p>
            <p>
				<strong><?php esc_html_e( 'End time:', 'auction' ); ?></strong>
				<?php echo esc_html( $values['display_end'] ?? __( 'Not scheduled', 'auction' ) ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Highest bid:', 'auction' ); ?></strong>
				<?php
				if ( ! empty( $values['latest_bid']['amount'] ) ) {
					echo wp_kses_post( wc_price( $values['latest_bid']['amount'] ) );
				} else {
					esc_html_e( 'No bids captured yet.', 'auction' );
				}
				?>
			</p>
			<?php if ( ! empty( $values['latest_bid']['name'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Bidder:', 'auction' ); ?></strong>
					<?php echo esc_html( $values['latest_bid']['name'] ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $values['latest_bid']['time'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Bid time:', 'auction' ); ?></strong>
					<?php echo esc_html( $values['latest_bid']['time'] ); ?>
				</p>
			<?php endif; ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Bidder', 'auction' ); ?></th>
						<th><?php esc_html_e( 'Bid amount', 'auction' ); ?></th>
						<th><?php esc_html_e( 'Date', 'auction' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td colspan="3"><?php esc_html_e( 'Bids log will appear here once the bidding engine is connected.', 'auction' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script type="text/html" id="auction-rule-template">
	<tr>
		<td>
			<input type="number" step="0.01" min="0" name="<?php echo esc_attr( $meta_prefix . 'automatic_increment_rules' ); ?>[__index__][from]" />
		</td>
		<td>
			<input type="number" step="0.01" min="0" name="<?php echo esc_attr( $meta_prefix . 'automatic_increment_rules' ); ?>[__index__][to]" />
		</td>
		<td>
			<input type="number" step="0.01" min="0" name="<?php echo esc_attr( $meta_prefix . 'automatic_increment_rules' ); ?>[__index__][increment]" />
		</td>
		<td class="actions">
			<button type="button" class="button-link-delete auction-remove-rule">__add_rule__</button>
		</td>
	</tr>
</script>

