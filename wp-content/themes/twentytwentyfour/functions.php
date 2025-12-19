<?php
/**
 * Twenty Twenty-Four functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Twenty Twenty-Four
 * @since Twenty Twenty-Four 1.0
 */

/**
 * Register block styles.
 */

if ( ! function_exists( 'twentytwentyfour_block_styles' ) ) :
	/**
	 * Register custom block styles
	 *
	 * @since Twenty Twenty-Four 1.0
	 * @return void
	 */
	function twentytwentyfour_block_styles() {

		register_block_style(
			'core/details',
			array(
				'name'         => 'arrow-icon-details',
				'label'        => __( 'Arrow icon', 'twentytwentyfour' ),
				/*
				 * Styles for the custom Arrow icon style of the Details block
				 */
				'inline_style' => '
				.is-style-arrow-icon-details {
					padding-top: var(--wp--preset--spacing--10);
					padding-bottom: var(--wp--preset--spacing--10);
				}

				.is-style-arrow-icon-details summary {
					list-style-type: "\2193\00a0\00a0\00a0";
				}

				.is-style-arrow-icon-details[open]>summary {
					list-style-type: "\2192\00a0\00a0\00a0";
				}',
			)
		);
		register_block_style(
			'core/post-terms',
			array(
				'name'         => 'pill',
				'label'        => __( 'Pill', 'twentytwentyfour' ),
				/*
				 * Styles variation for post terms
				 * https://github.com/WordPress/gutenberg/issues/24956
				 */
				'inline_style' => '
				.is-style-pill a,
				.is-style-pill span:not([class], [data-rich-text-placeholder]) {
					display: inline-block;
					background-color: var(--wp--preset--color--base-2);
					padding: 0.375rem 0.875rem;
					border-radius: var(--wp--preset--spacing--20);
				}

				.is-style-pill a:hover {
					background-color: var(--wp--preset--color--contrast-3);
				}',
			)
		);
		register_block_style(
			'core/list',
			array(
				'name'         => 'checkmark-list',
				'label'        => __( 'Checkmark', 'twentytwentyfour' ),
				/*
				 * Styles for the custom checkmark list block style
				 * https://github.com/WordPress/gutenberg/issues/51480
				 */
				'inline_style' => '
				ul.is-style-checkmark-list {
					list-style-type: "\2713";
				}

				ul.is-style-checkmark-list li {
					padding-inline-start: 1ch;
				}',
			)
		);
		register_block_style(
			'core/navigation-link',
			array(
				'name'         => 'arrow-link',
				'label'        => __( 'With arrow', 'twentytwentyfour' ),
				/*
				 * Styles for the custom arrow nav link block style
				 */
				'inline_style' => '
				.is-style-arrow-link .wp-block-navigation-item__label:after {
					content: "\2197";
					padding-inline-start: 0.25rem;
					vertical-align: middle;
					text-decoration: none;
					display: inline-block;
				}',
			)
		);
		register_block_style(
			'core/heading',
			array(
				'name'         => 'asterisk',
				'label'        => __( 'With asterisk', 'twentytwentyfour' ),
				'inline_style' => "
				.is-style-asterisk:before {
					content: '';
					width: 1.5rem;
					height: 3rem;
					background: var(--wp--preset--color--contrast-2, currentColor);
					clip-path: path('M11.93.684v8.039l5.633-5.633 1.216 1.23-5.66 5.66h8.04v1.737H13.2l5.701 5.701-1.23 1.23-5.742-5.742V21h-1.737v-8.094l-5.77 5.77-1.23-1.217 5.743-5.742H.842V9.98h8.162l-5.701-5.7 1.23-1.231 5.66 5.66V.684h1.737Z');
					display: block;
				}

				/* Hide the asterisk if the heading has no content, to avoid using empty headings to display the asterisk only, which is an A11Y issue */
				.is-style-asterisk:empty:before {
					content: none;
				}

				.is-style-asterisk:-moz-only-whitespace:before {
					content: none;
				}

				.is-style-asterisk.has-text-align-center:before {
					margin: 0 auto;
				}

				.is-style-asterisk.has-text-align-right:before {
					margin-left: auto;
				}

				.rtl .is-style-asterisk.has-text-align-left:before {
					margin-right: auto;
				}",
			)
		);
	}
endif;

add_action( 'init', 'twentytwentyfour_block_styles' );

/**
 * Enqueue block stylesheets.
 */

if ( ! function_exists( 'twentytwentyfour_block_stylesheets' ) ) :
	/**
	 * Enqueue custom block stylesheets
	 *
	 * @since Twenty Twenty-Four 1.0
	 * @return void
	 */
	function twentytwentyfour_block_stylesheets() {
		/**
		 * The wp_enqueue_block_style() function allows us to enqueue a stylesheet
		 * for a specific block. These will only get loaded when the block is rendered
		 * (both in the editor and on the front end), improving performance
		 * and reducing the amount of data requested by visitors.
		 *
		 * See https://make.wordpress.org/core/2021/12/15/using-multiple-stylesheets-per-block/ for more info.
		 */
		wp_enqueue_block_style(
			'core/button',
			array(
				'handle' => 'twentytwentyfour-button-style-outline',
				'src'    => get_parent_theme_file_uri( 'assets/css/button-outline.css' ),
				'ver'    => wp_get_theme( get_template() )->get( 'Version' ),
				'path'   => get_parent_theme_file_path( 'assets/css/button-outline.css' ),
			)
		);
	}
endif;

add_action( 'init', 'twentytwentyfour_block_stylesheets' );

/**
 * Register pattern categories.
 */

if ( ! function_exists( 'twentytwentyfour_pattern_categories' ) ) :
	/**
	 * Register pattern categories
	 *
	 * @since Twenty Twenty-Four 1.0
	 * @return void
	 */
	function twentytwentyfour_pattern_categories() {

		register_block_pattern_category(
			'twentytwentyfour_page',
			array(
				'label'       => _x( 'Pages', 'Block pattern category', 'twentytwentyfour' ),
				'description' => __( 'A collection of full page layouts.', 'twentytwentyfour' ),
			)
		);
	}
endif;

add_action( 'init', 'twentytwentyfour_pattern_categories' );


function mytheme_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('menus');
    register_nav_menus([
        'primary' => __('Primary Menu', 'jimenezmrdiscount'),
    ]);
}

add_action('after_setup_theme', 'mytheme_theme_setup');

/**
 * Hide Buy Now / add-to-cart buttons for auction products only inside the auction_products shortcode.
 *
 * @param string     $html    Original button HTML.
 * @param WC_Product $product Product object.
 *
 * @return string
 */
function mytheme_auction_shortcode_hide_buy_now_button( $html, $product ) {
	if ( ! $product instanceof WC_Product ) {
		return $html;
	}

	if ( class_exists( 'Auction_Product_Helper' ) && Auction_Product_Helper::is_auction_product( $product ) ) {
		// Inside the shortcode we don't want any Buy Now / add-to-cart buttons for auction items.
		return '';
	}

	return $html;
}

/**
 * Auction Products Shortcode
 * Display auction products similar to auction page
 *
 * Usage: [auction_products limit="12" columns="4"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function auction_products_shortcode( $atts ) {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		return '<p>' . esc_html__( 'WooCommerce is required for this shortcode.', 'twentytwentyfour' ) . '</p>';
	}

	// Check if Auction plugin is active
	if ( ! class_exists( 'Auction_Product_Helper' ) || ! class_exists( 'Auction_Bid_Manager' ) || ! class_exists( 'Auction_Settings' ) ) {
		return '<p>' . esc_html__( 'Auction plugin is required for this shortcode.', 'twentytwentyfour' ) . '</p>';
	}

	// Parse shortcode attributes
	$atts = shortcode_atts( array(
		'limit'   => 12,
		'columns' => 4,
		'orderby' => 'date',
		'order'   => 'DESC',
	), $atts, 'auction_products' );

	$limit   = absint( $atts['limit'] );
	$columns = absint( $atts['columns'] );
	$orderby = sanitize_text_field( $atts['orderby'] );
	$order   = sanitize_text_field( $atts['order'] );

	// Query auction products
	$args = array(
		'post_type'      => 'product',
		'posts_per_page' => $limit,
		'orderby'        => $orderby,
		'order'          => $order,
		'meta_query'     => array(
			array(
				'key'   => '_auction_enabled',
				'value' => 'yes',
			),
		),
	);

	// Filter out ended auctions if setting is enabled
	if ( method_exists( 'Auction_Settings', 'is_enabled' ) && Auction_Settings::is_enabled( 'hide_ended' ) ) {
		$current_time = current_time( 'mysql' );
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_auction_end_time',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_auction_end_time',
				'value'   => $current_time,
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		);
	}

	// Filter out future auctions if setting is enabled
	if ( method_exists( 'Auction_Settings', 'is_enabled' ) && Auction_Settings::is_enabled( 'hide_future' ) ) {
		$current_time = current_time( 'mysql' );
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_auction_start_time',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_auction_start_time',
				'value'   => $current_time,
				'compare' => '<=',
				'type'    => 'DATETIME',
			),
		);
	}

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return '<p>' . esc_html__( 'No auction products found.', 'twentytwentyfour' ) . '</p>';
	}

	// Set WooCommerce columns
	$woocommerce_loop = array(
		'columns' => $columns,
	);

	// Enqueue WooCommerce styles if not already enqueued
	if ( ! wp_style_is( 'woocommerce-general', 'enqueued' ) ) {
		wp_enqueue_style( 'woocommerce-general' );
	}

	// Enqueue auction frontend assets if available
	if ( wp_style_is( 'auction-frontend', 'registered' ) ) {
		wp_enqueue_style( 'auction-frontend' );
	}
	if ( wp_script_is( 'auction-frontend', 'registered' ) ) {
		wp_enqueue_script( 'auction-frontend' );
	}

	// Temporarily remove the auction loop badge hook to prevent duplication
	$auction_frontend = null;
	if ( class_exists( 'Auction_Frontend' ) ) {
		$auction_frontend = Auction_Frontend::instance();
		remove_action( 'woocommerce_after_shop_loop_item', array( $auction_frontend, 'render_loop_badge' ), 20 );
	}

	ob_start();

	// Start output
	echo '<div class="woocommerce auction-products-shortcode">';
	echo '<ul class="products columns-' . esc_attr( $columns ) . '">';

	// Loop through products
	while ( $query->have_posts() ) {
		$query->the_post();
		global $product;

		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		// Check if it's an auction product
		if ( ! method_exists( 'Auction_Product_Helper', 'is_auction_product' ) || ! Auction_Product_Helper::is_auction_product( $product ) ) {
			continue;
		}

		// Get auction configuration
		$config          = Auction_Product_Helper::get_config( $product );
		$status          = Auction_Product_Helper::get_auction_status( $config );
		$start_timestamp = $config['start_timestamp'] ?: 0;
		$end_timestamp   = $config['end_timestamp'] ?: 0;

		// Get bid information.
		$state       = Auction_Product_Helper::get_runtime_state( $product );
		$current_bid = $state['winning_bid_id'] ? $state['current_bid'] : Auction_Product_Helper::get_start_price( $config );
		$current_bid = $current_bid > 0 ? $current_bid : 0;

		// Get bid increment and compute next bid.
		$bid_increment = Auction_Product_Helper::get_manual_increment( $config );
		$next_bid      = $state['winning_bid_id'] ? ( $current_bid + $bid_increment ) : max( $current_bid, $bid_increment );

		// Get product URL.
		$product_url = get_permalink( $product->get_id() );

		?>
		<li <?php wc_product_class( '', $product ); ?>>
			<?php
			/**
			 * Hook: woocommerce_before_shop_loop_item.
			 */
			do_action( 'woocommerce_before_shop_loop_item' );
			?>

			<?php
			/**
			 * Hook: woocommerce_before_shop_loop_item_title.
			 */
			do_action( 'woocommerce_before_shop_loop_item_title' );
			?>

			<?php
			/**
			 * Hook: woocommerce_shop_loop_item_title.
			 */
			do_action( 'woocommerce_shop_loop_item_title' );
			?>

			<?php
			// Hide price for auction products
			add_filter( 'woocommerce_get_price_html', '__return_empty_string', 999 );
			/**
			 * Hook: woocommerce_after_shop_loop_item_title.
			 */
			do_action( 'woocommerce_after_shop_loop_item_title' );
			remove_filter( 'woocommerce_get_price_html', '__return_empty_string', 999 );
			?>

			<?php
			/**
			 * Hook: woocommerce_after_shop_loop_item.
			 * Temporarily hide Buy Now / add-to-cart buttons for auction products inside this shortcode only.
			 */
			add_filter( 'woocommerce_loop_add_to_cart_link', 'mytheme_auction_shortcode_hide_buy_now_button', 999, 2 );
			do_action( 'woocommerce_after_shop_loop_item' );
			remove_filter( 'woocommerce_loop_add_to_cart_link', 'mytheme_auction_shortcode_hide_buy_now_button', 999 );
			?>

			<!-- Auction Information (below image and title) -->
			<div class="auction-loop-meta"
				data-auction-product="<?php echo esc_attr( $product->get_id() ); ?>"
				data-auction-status="<?php echo esc_attr( $status ); ?>"
				<?php echo $end_timestamp ? 'data-auction-end="' . esc_attr( $end_timestamp ) . '"' : ''; ?>
			>
				<div class="auction-loop-info">

					<div class="auction-info-row">
						<strong><?php esc_html_e( 'High Bid:', 'auction' ); ?></strong>
						<span class="auction-current-bid"><?php echo wp_kses_post( wc_price( $current_bid ) ); ?></span>
					</div>

					<?php if ( $end_timestamp ) : ?>
						<div class="auction-info-row">
							<strong><?php esc_html_e( 'Time left:', 'auction' ); ?></strong>
							<?php
							// PHP-side fallback so the countdown is never blank even if JS fails.
							$now      = current_time( 'timestamp' );
							$diff     = max( 0, $end_timestamp - $now );
							$days     = (int) floor( $diff / DAY_IN_SECONDS );
							$hours    = (int) floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
							$minutes  = (int) floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
							$time_str = sprintf( '%02dd %02dh %02dm', $days, $hours, $minutes );

							$start_attr = $start_timestamp ? ' data-countdown-start="' . esc_attr( $start_timestamp ) . '"' : '';
							?>
							<span
								class="auction-countdown"
								data-countdown-target="<?php echo esc_attr( $end_timestamp ); ?>"
								<?php echo $start_attr; ?>
							>
								<?php echo esc_html( $time_str ); ?>
							</span>
						</div>
					<?php endif; ?>

					<div class="auction-info-row auction-lot-details">
						<a href="<?php echo esc_url( $product_url ); ?>" class="button auction-bid-button">
							<?php
							/* translators: %s: next bid amount */
							printf(
								esc_html__( 'BID %s', 'auction' ),
								wp_strip_all_tags( wc_price( $next_bid ) )
							);
							?>
						</a>
					</div>

				</div>

			</div>
		</li>
		<?php
	}

	echo '</ul>';
	echo '</div>';

	wp_reset_postdata();

	// Restore the auction loop badge hook
	if ( $auction_frontend ) {
		add_action( 'woocommerce_after_shop_loop_item', array( $auction_frontend, 'render_loop_badge' ), 20 );
	}

	return ob_get_clean();
}
add_shortcode( 'auction_products', 'auction_products_shortcode' );

/////////////////////////////////////////////////////////////////////////////////



add_action('admin_menu', 'register_review_admin_page');

function register_review_admin_page()
{
    add_menu_page(
        'Post Review',
        'Post Review',
        'manage_options',
        'post-review-admin',
        'render_review_admin_page',
        'dashicons-star-filled',
        26
    );
}

function render_review_admin_page()
{
    ?>
    <div class="wrap">
        <h1>Post a Review</h1>

        <form method="POST"
              action="<?php echo admin_url('admin-post.php'); ?>"
              enctype="multipart/form-data">

            <input type="hidden" name="action" value="submit_review_custom">
            <input type="hidden" name="submit_review_custom" value="1">

            <!-- PRODUCT ID -->
            <table class="form-table">
                <tr>
                    <th>Product ID</th>
                    <td>
					<select name="product_id" id="product_id" required>
						<option value="">Select Product</option>
						<?php
						$products = wc_get_products([
							'limit'  => -1,
							'status' => 'publish',
						]);

						foreach ($products as $product) {
							$image_id  = $product->get_image_id();
							$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();

							echo '<option value="' . esc_attr($product->get_id()) . '" 
										data-image="' . esc_url($image_url) . '">'
								. esc_html($product->get_name()) .
								'</option>';
						}
						?>
					</select>
					<div id="product_image_preview" style="margin-top:10px; display:none;">
						<img id="product_image" src="" style="max-width:120px; border:1px solid #ccc; padding:5px;">
					</div>



                    </td>
                </tr>

                <tr>
                    <th>Name</th>
                    <td>
                        <input type="text" name="author" class="regular-text" required>
                    </td>
                </tr>

                <tr>
                    <th>Email</th>
                    <td>
                        <input type="email" name="email" class="regular-text" required>
                    </td>
                </tr>

                <tr>
                    <th>Tour Review</th>
                    <td>
                        <textarea name="comment" rows="4" class="large-text" required></textarea>
                    </td>
                </tr>

                <tr>
                    <th>Tour Rating</th>
                    <td>
                        <select name="rating" required>
                            <option value="">Select</option>
                            <option value="1">1 ⭐</option>
                            <option value="2">2 ⭐</option>
                            <option value="3">3 ⭐</option>
                            <option value="4">4 ⭐</option>
                            <option value="5">5 ⭐</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Guide Review</th>
                    <td>
                        <input type="text" name="reviewTitle" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th>Guide Rating</th>
                    <td>
                        <select name="guide_rating">
                            <option value="">Select</option>
                            <option value="1">1 ⭐</option>
                            <option value="2">2 ⭐</option>
                            <option value="3">3 ⭐</option>
                            <option value="4">4 ⭐</option>
                            <option value="5">5 ⭐</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Upload Images</th>
                    <td>
                        <input type="file" name="reviewImages[]" multiple>
                    </td>
                </tr>
            </table>

            <?php submit_button('Submit Review'); ?>
        </form>
    </div>
	<script>
		document.getElementById('product_id').addEventListener('change', function () {
			const selected = this.options[this.selectedIndex];
			const imageUrl = selected.getAttribute('data-image');

			const preview = document.getElementById('product_image_preview');
			const img = document.getElementById('product_image');

			if (imageUrl) {
				img.src = imageUrl;
				preview.style.display = 'block';
			} else {
				preview.style.display = 'none';
			}
		});
	</script>

    <?php
}

add_action('admin_post_submit_review_custom', 'handle_admin_review_submission');

function handle_admin_review_submission()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $product_id   = intval($_POST['product_id']);
    $author       = sanitize_text_field($_POST['author']);
    $email        = sanitize_email($_POST['email']);
    $comment      = sanitize_textarea_field($_POST['comment']);
    $rating       = intval($_POST['rating']);
    $guide_rating = intval($_POST['guide_rating']);
    $guide_review = sanitize_text_field($_POST['reviewTitle']);

    $comment_id = wp_insert_comment([
        'comment_post_ID'      => $product_id,
        'comment_author'       => $author,
        'comment_author_email' => $email,
        'comment_content'      => $comment,
        'comment_type'         => 'review',
        'comment_approved'     => 1,
        'user_id'              => get_current_user_id(),
    ]);

    if (!$comment_id) {
        wp_die('Failed to insert review');
    }

	// WooCommerce REQUIRED rating key
	add_comment_meta($comment_id, 'rating', $rating);

	// Optional custom fields
	add_comment_meta($comment_id, 'guide_rating', $guide_rating);
	add_comment_meta($comment_id, 'guide_review', $guide_review);


    // Image Upload
    if (!empty($_FILES['reviewImages']['name'][0])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $image_ids = [];

        foreach ($_FILES['reviewImages']['name'] as $key => $value) {
            $_FILES['file'] = [
                'name'     => $_FILES['reviewImages']['name'][$key],
                'type'     => $_FILES['reviewImages']['type'][$key],
                'tmp_name' => $_FILES['reviewImages']['tmp_name'][$key],
                'error'    => $_FILES['reviewImages']['error'][$key],
                'size'     => $_FILES['reviewImages']['size'][$key],
            ];

            $attach_id = media_handle_upload('file', $product_id);

            if (!is_wp_error($attach_id)) {
                $image_ids[] = $attach_id;
            }
        }

        add_comment_meta($comment_id, 'review_images', $image_ids);
    }

    wp_redirect(admin_url('edit-comments.php?review=success'));
    exit;
}
