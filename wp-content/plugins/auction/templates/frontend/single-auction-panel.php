<?php
/**
 * Single product auction panel.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * @var WC_Product $product
 * @var array      $config
 * @var array      $state
 * @var float      $current_bid
 * @var float      $next_bid
 * @var float      $manual_increment
 * @var array|null $leading_bid
 * @var bool       $is_watchlisted
 * @var string     $watchlist_nonce
 */

$auction_status = $status;
$start_timestamp = $config['start_timestamp'] ?: 0;
$end_timestamp   = $config['end_timestamp'] ?: 0;
$automatic_on    = $config['automatic_bidding'];

$highest_bidder_name = '';

if ( $leading_bid ) {
	if ( $leading_bid['user_id'] ) {
		$user = get_user_by( 'id', $leading_bid['user_id'] );
		if ( $user ) {
			$highest_bidder_name = Auction_Settings::get( 'bid_username_display', 'masked' );
			if ( 'full' === $highest_bidder_name ) {
				$highest_bidder_name = $user->display_name;
			} else {
				$username = $user->display_name ?: $user->user_login;
				$highest_bidder_name = substr( $username, 0, 1 ) . '****' . substr( $username, -1 );
			}
		}
	} elseif ( $leading_bid['session_id'] ) {
		$highest_bidder_name = __( 'Guest bidder', 'auction' );
	}
}

?>

<section
	class="auction-single-panel"
	data-auction-product="<?php echo esc_attr( $product->get_id() ); ?>"
	data-requires-login="<?php echo esc_attr( $requires_login ? '1' : '0' ); ?>"
	data-enable-register-modal="<?php echo esc_attr( $register_modal ? '1' : '0' ); ?>"
	data-register-url="<?php echo esc_url( $register_page_url ); ?>"
>
	<header class="auction-header">
		<h2><?php esc_html_e( 'Auction Details', 'auction' ); ?></h2>
		<div class="auction-status" data-auction-status="<?php echo esc_attr( $auction_status ); ?>">
			<?php
			switch ( $auction_status ) {
				case 'active':
					esc_html_e( 'Auction in progress', 'auction' );
					break;
				case 'scheduled':
					esc_html_e( 'Auction scheduled', 'auction' );
					break;
				default:
					esc_html_e( 'Auction ended', 'auction' );
					break;
			}
			?>
		</div>
	</header>

	<div class="auction-meta">
		<?php if ( $start_timestamp ) : ?>
			<p>
				<strong><?php esc_html_e( 'Start time:', 'auction' ); ?></strong>
				<span><?php echo esc_html( wc_format_datetime( wc_string_to_datetime( gmdate( 'Y-m-d H:i:s', $start_timestamp ) ) ) ); ?></span>
			</p>
		<?php endif; ?>

		<?php if ( $end_timestamp ) : ?>
			<p>
				<strong><?php esc_html_e( 'End time:', 'auction' ); ?></strong>
				<span class="auction-countdown" data-countdown-target="<?php echo esc_attr( $end_timestamp ); ?>">
					<?php echo esc_html( wc_format_datetime( wc_string_to_datetime( gmdate( 'Y-m-d H:i:s', $end_timestamp ) ) ) ); ?>
				</span>
			</p>
		<?php endif; ?>

		<p>
			<strong><?php esc_html_e( 'Current bid:', 'auction' ); ?></strong>
			<span class="auction-current-bid"><?php echo wp_kses_post( wc_price( $current_bid ) ); ?></span>
		</p>

		<p>
			<strong><?php esc_html_e( 'Next minimum bid:', 'auction' ); ?></strong>
			<span class="auction-next-bid"><?php echo wp_kses_post( wc_price( $next_bid ) ); ?></span>
		</p>

		<?php if ( ! $config['sealed'] && $highest_bidder_name ) : ?>
			<p>
				<strong><?php esc_html_e( 'Highest bidder:', 'auction' ); ?></strong>
				<span class="auction-highest-bidder"><?php echo esc_html( $highest_bidder_name ); ?></span>
			</p>
		<?php endif; ?>
	</div>

<?php if ( 'ended' !== $auction_status ) : ?>
	<?php if ( is_user_logged_in() ) : ?>
			<form
				class="auction-bid-form"
				data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
				data-manual-increment="<?php echo esc_attr( $manual_increment ); ?>"
				data-current-bid="<?php echo esc_attr( $current_bid ); ?>"
				data-next-bid="<?php echo esc_attr( $next_bid ); ?>"
			>
				<?php wp_nonce_field( 'auction_bid_nonce', 'auction_bid_nonce_field' ); ?>

				<p class="form-row form-row-wide">
					<label for="auction-bid-amount"><?php esc_html_e( 'Your bid amount', 'auction' ); ?></label>
					<input
						type="number"
						step="0.01"
						min="<?php echo esc_attr( $next_bid ); ?>"
						name="bid_amount"
						id="auction-bid-amount"
						required
						value="<?php echo esc_attr( $next_bid ); ?>"
					/>
					<small><?php esc_html_e( 'Enter the amount you are willing to bid.', 'auction' ); ?></small>
				</p>

				<?php if ( $automatic_on ) : ?>
					<p class="form-row form-row-wide">
						<label>
							<input type="checkbox" name="is_auto" value="1" />
							<?php esc_html_e( 'Enable automatic bidding', 'auction' ); ?>
						</label>
					</p>

					<p class="form-row form-row-wide auction-auto-max-field hidden">
						<label for="auction-max-auto"><?php esc_html_e( 'Maximum automatic bid', 'auction' ); ?></label>
						<input
							type="number"
							step="0.01"
							min="<?php echo esc_attr( $next_bid ); ?>"
							name="max_auto_amount"
							id="auction-max-auto"
						/>
						<small><?php esc_html_e( 'Set the maximum amount you are willing to bid automatically.', 'auction' ); ?></small>
					</p>
				<?php endif; ?>

				<?php if ( $config['sealed'] ) : ?>
					<p class="auction-note">
						<?php esc_html_e( 'This is a sealed auction. Bids will remain hidden until the auction ends.', 'auction' ); ?>
					</p>
				<?php endif; ?>

				<p class="form-row">
					<button type="submit" class="button auction-submit-bid">
						<?php esc_html_e( 'Place bid', 'auction' ); ?>
					</button>
				</p>

				<div class="auction-bid-feedback" role="status" aria-live="polite"></div>
			</form>

			<?php if ( is_user_logged_in() && Auction_Settings::is_enabled( 'enable_watchlist' ) ) : ?>
				<button
					type="button"
					class="button auction-watchlist-toggle<?php echo $is_watchlisted ? ' is-watchlisted' : ''; ?>"
					data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
					data-nonce="<?php echo esc_attr( $watchlist_nonce ); ?>"
				>
					<?php echo $is_watchlisted ? esc_html__( 'Remove from watchlist', 'auction' ) : esc_html__( 'Add to watchlist', 'auction' ); ?>
				</button>
			<?php endif; ?>
		<?php else : ?>
			<div class="auction-login-prompt">
				<p>
					<?php esc_html_e( 'You must be logged in to place a bid.', 'auction' ); ?>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
						<?php esc_html_e( 'Log in to your account', 'auction' ); ?>
					</a>
				</p>
				<div class="auction-register-inline">
					<h4><?php esc_html_e( 'Need an account? Register below.', 'auction' ); ?></h4>
					<?php echo do_shortcode( '[auction_register_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<p class="auction-ended-message">
			<?php esc_html_e( 'This auction has ended. Thank you for your interest.', 'auction' ); ?>
		</p>
	<?php endif; ?>

	<div class="auction-bid-confirmation" aria-hidden="true" role="dialog">
		<div class="auction-bid-confirmation__dialog">
			<h3><?php esc_html_e( 'Confirm your bid', 'auction' ); ?></h3>
			<p class="auction-bid-confirmation__message">
				<?php
				printf(
					/* translators: %s: bid amount */
					esc_html__( 'You are about to place a bid of %s.', 'auction' ),
					'<strong class="auction-bid-confirmation__amount"></strong>'
				);
				?>
			</p>
			<p><?php esc_html_e( 'Do you want to continue?', 'auction' ); ?></p>
			<p class="auction-bid-confirmation__auto-note" hidden></p>
			<div class="auction-bid-confirmation__actions">
				<button type="button" class="button button-primary auction-confirm-bid">
					<?php esc_html_e( 'Yes, I want to bid', 'auction' ); ?>
				</button>
				<button type="button" class="button auction-cancel-bid">
					<?php esc_html_e( 'Cancel', 'auction' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div class="auction-login-modal" aria-hidden="true" role="dialog">
		<div class="auction-login-modal__dialog">
			<button type="button" class="auction-login-modal__close" aria-label="<?php esc_attr_e( 'Close', 'auction' ); ?>">&times;</button>
			<h3><?php esc_html_e( 'Log in to bid', 'auction' ); ?></h3>
			<div class="auction-login-modal__content">
				<?php
				wp_login_form(
					array(
						'redirect'       => get_permalink( $product->get_id() ),
						'label_username' => __( 'Email Address', 'auction' ),
						'label_log_in'   => __( 'Log In', 'auction' ),
					)
				);
				?>
				<p class="auction-login-modal__register">
					<?php esc_html_e( 'Need an account?', 'auction' ); ?>
					<a
						href="<?php echo esc_url( $register_page_url ); ?>"
						class="auction-login-modal__register-link"
						<?php echo $register_modal ? 'data-open-register-modal="1"' : ''; ?>
					>
						<?php esc_html_e( 'Register now', 'auction' ); ?>
					</a>
				</p>
			</div>
		</div>
	</div>

	<?php if ( $register_modal ) : ?>
		<div class="auction-register-modal" aria-hidden="true" role="dialog">
			<div class="auction-register-modal__dialog">
				<button type="button" class="auction-register-modal__close" aria-label="<?php esc_attr_e( 'Close', 'auction' ); ?>">&times;</button>
				<h3><?php esc_html_e( 'Register an account', 'auction' ); ?></h3>
				<div class="auction-register-modal__content">
					<?php echo do_shortcode( '[auction_register_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
	<?php endif; ?>
</section>

