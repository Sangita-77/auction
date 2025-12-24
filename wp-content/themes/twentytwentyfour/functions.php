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



add_action('admin_menu', function () {
    add_menu_page(
        'Post Product Review',
        'Post Review',
        'manage_options',
        'post-review-admin',
        'render_review_admin_page',
        'dashicons-star-filled',
        26
    );
});


add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_post-review-admin') return;

    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
});

function render_review_admin_page()
{
    ?>
    <div class="wrap">
        <h1>Post Product Review</h1>

        <form method="POST"
              action="<?php echo admin_url('admin-post.php'); ?>"
              enctype="multipart/form-data">

            <input type="hidden" name="action" value="submit_review_custom">

            <table class="form-table">

                <!-- PRODUCT SELECT -->
                <tr>
                    <th>Product</th>
                    <td>
                        <select name="product_id" id="product_id" style="width:420px;" required>
                            <option value="">Search product…</option>
                            <?php
                            $products = wc_get_products([
                                'limit'  => -1,
                                'status' => 'publish',
                            ]);

                            foreach ($products as $product) {
                                $img_id  = $product->get_image_id();
                                $img     = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : wc_placeholder_img_src();
                                $price   = wp_strip_all_tags($product->get_price_html());
                                $sku     = $product->get_sku();

                                echo '<option value="' . esc_attr($product->get_id()) . '"
                                        data-image="' . esc_url($img) . '"
                                        data-price="' . esc_attr($price) . '"
                                        data-sku="' . esc_attr($sku) . '">'
                                        . esc_html($product->get_name()) .
                                     '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <!-- AUTHOR -->
                <tr>
                    <th>Name</th>
                    <td><input type="text" name="author" class="regular-text" required></td>
                </tr>

                <!-- EMAIL -->
                <tr>
                    <th>Email</th>
                    <td><input type="email" name="email" class="regular-text" required></td>
                </tr>

                <!-- REVIEW -->
                <tr>
                    <th>Review</th>
                    <td><textarea name="comment" rows="4" class="large-text" required></textarea></td>
                </tr>

                <!-- RATING -->
                <tr>
                    <th>Rating</th>
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

                <!-- GUIDE INFO (OPTIONAL) -->
                <tr>
                    <th>Guide Review</th>
                    <td><input type="text" name="guide_review" class="regular-text"></td>
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

                <!-- IMAGES -->
                <tr>
                    <th>Images</th>
                    <td><input type="file" name="reviewImages[]" multiple></td>
                </tr>

            </table>

            <?php submit_button('Submit Review'); ?>
        </form>
    </div>

    <!-- SELECT2 TEMPLATE -->
    <script>
    jQuery(function ($) {
        function formatProduct(item) {
            if (!item.id) return item.text;

            const el = $(item.element);
            return $(`
                <div style="display:flex; gap:10px; align-items:center;">
                    <img src="${el.data('image')}" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">
                    <div>
                        <strong>${item.text}</strong><br>
                        <small>SKU: ${el.data('sku') || '—'} | ${el.data('price') || ''}</small>
                    </div>
                </div>
            `);
        }

        $('#product_id').select2({
            templateResult: formatProduct,
            templateSelection: formatProduct,
            escapeMarkup: m => m
        });
    });
    </script>
    <?php
}

add_action('admin_post_submit_review_custom', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $product_id   = intval($_POST['product_id']);
    $author       = sanitize_text_field($_POST['author']);
    $email        = sanitize_email($_POST['email']);
    $comment      = sanitize_textarea_field($_POST['comment']);
    $rating       = intval($_POST['rating']);
    $guide_rating = intval($_POST['guide_rating'] ?? 0);
    $guide_review = sanitize_text_field($_POST['guide_review'] ?? '');

    // INSERT REVIEW
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
        wp_die('Failed to save review');
    }

    // REQUIRED FOR WOOCOMMERCE
    add_comment_meta($comment_id, 'rating', $rating);

    // OPTIONAL META
    if ($guide_rating) add_comment_meta($comment_id, 'guide_rating', $guide_rating);
    if ($guide_review) add_comment_meta($comment_id, 'guide_review', $guide_review);

    // IMAGE UPLOAD
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

    wc_update_product_rating($product_id);

    wp_redirect(admin_url('edit-comments.php?review=success'));
    exit;
});

/////////////////////////////////////////////////////////////////////////////////
// QUIZ FUNCTIONALITY
/////////////////////////////////////////////////////////////////////////////////

// Register Quiz Branch Taxonomy
function register_quiz_branch_taxonomy() {
    $labels = array(
        'name'              => 'Quiz Branches',
        'singular_name'     => 'Quiz Branch',
        'search_items'      => 'Search Branches',
        'all_items'         => 'All Branches',
        'parent_item'       => 'Parent Branch',
        'parent_item_colon' => 'Parent Branch:',
        'edit_item'         => 'Edit Branch',
        'update_item'       => 'Update Branch',
        'add_new_item'      => 'Add New Branch',
        'new_item_name'     => 'New Branch Name',
        'menu_name'         => 'Quiz Branches',
    );

    register_taxonomy('quiz_branch', 'quiz_question', array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'quiz-branch'),
    ));
}
// add_action('init', 'register_quiz_branch_taxonomy');

// Register Quiz Question Custom Post Type
function register_quiz_question_post_type() {
    $labels = array(
        'name'               => 'Quiz Questions',
        'singular_name'      => 'Quiz Question',
        'menu_name'          => 'Quiz Questions',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Question',
        'edit_item'          => 'Edit Question',
        'new_item'           => 'New Question',
        'view_item'          => 'View Question',
        'search_items'       => 'Search Questions',
        'not_found'          => 'No questions found',
        'not_found_in_trash' => 'No questions found in trash',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 25,
        'menu_icon'          => 'dashicons-clipboard',
        'supports'           => array('title'),
    );

    register_post_type('quiz_question', $args);
}
// add_action('init', 'register_quiz_question_post_type');

// Register Quiz Attempt Custom Post Type (for storing who attended the quiz)
function register_quiz_attempt_post_type() {
    $labels = array(
        'name'               => 'Quiz Attempts',
        'singular_name'      => 'Quiz Attempt',
        'menu_name'          => 'Quiz Attempts',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Attempt',
        'edit_item'          => 'Edit Attempt',
        'new_item'           => 'New Attempt',
        'view_item'          => 'View Attempt',
        'search_items'       => 'Search Attempts',
        'not_found'          => 'No attempts found',
        'not_found_in_trash' => 'No attempts found in trash',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-yes-alt',
        'supports'           => array('title'),
    );

    register_post_type('quiz_attempt', $args);
}
// add_action('init', 'register_quiz_attempt_post_type');

// Lock down Quiz Attempts in admin: no manual add/edit, view only.
function quiz_attempt_admin_lockdown() {
    // Remove "Add New" submenu item.
    remove_submenu_page('edit.php?post_type=quiz_attempt', 'post-new.php?post_type=quiz_attempt');
}
// add_action('admin_menu', 'quiz_attempt_admin_lockdown', 999);

// Remove "New Quiz Attempt" from admin bar.
function quiz_attempt_admin_bar_lockdown($wp_admin_bar) {
    $wp_admin_bar->remove_node('new-quiz_attempt');
}
// add_action('admin_bar_menu', 'quiz_attempt_admin_bar_lockdown', 999);

// Block direct access to post-new.php for quiz_attempt.
function quiz_attempt_block_manual_create() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'quiz_attempt') {
        wp_die(__('Quiz attempts are created automatically from the quiz form and cannot be created manually.', 'twentytwentyfour'));
    }
}
// add_action('load-post-new.php', 'quiz_attempt_block_manual_create');

// Make quiz_attempt edit screen read-only (no editing, just view).
function quiz_attempt_make_read_only() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'quiz_attempt') {
        return;
    }
    ?>
    <style>
        /* Hide publish/update box and slug editor */
        #submitdiv,
        #edit-slug-box,
        .page-title-action {
            display: none !important;
        }
        /* Make title and meta boxes read-only */
        #titlediv input#title,
        #post-body input,
        #post-body textarea,
        #post-body select {
            pointer-events: none;
            background-color: #f7f7f7;
        }
        /* But keep our details table readable */
        #quiz-attempt-details table input,
        #quiz-attempt-details table textarea,
        #quiz-attempt-details table select {
            pointer-events: auto;
            background-color: transparent;
        }
    </style>
    <?php
}
// add_action('admin_head-post.php', 'quiz_attempt_make_read_only');

// Hide "Add New" button on the Quiz Attempts list screen.
function quiz_attempt_hide_add_new_on_list() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'quiz_attempt') {
        return;
    }
    ?>
    <style>
        .page-title-action {
            display: none !important;
        }
    </style>
    <?php
}
// add_action('admin_head-edit.php', 'quiz_attempt_hide_add_new_on_list');

// Remove Edit and Quick Edit row actions for Quiz Attempts.
function quiz_attempt_remove_row_actions($actions, $post) {
    if ($post->post_type === 'quiz_attempt') {
        if (isset($actions['edit'])) {
            unset($actions['edit']);
        }
        // Quick Edit action key is 'inline hide-if-no-js'.
        if (isset($actions['inline hide-if-no-js'])) {
            unset($actions['inline hide-if-no-js']);
        }
    }
    return $actions;
}
add_filter('post_row_actions', 'quiz_attempt_remove_row_actions', 10, 2);

// Add Meta Boxes for Quiz Questions
function add_quiz_question_meta_boxes() {
    add_meta_box(
        'quiz_question_details',
        'Question Details',
        'render_quiz_question_meta_box',
        'quiz_question',
        'normal',
        'high'
    );
}
// add_action('add_meta_boxes', 'add_quiz_question_meta_boxes');

// Render Quiz Question Meta Box
function render_quiz_question_meta_box($post) {
    wp_nonce_field('save_quiz_question_meta', 'quiz_question_meta_nonce');
    
    $question_text = get_post_meta($post->ID, '_question_text', true);
    $answer_type = get_post_meta($post->ID, '_answer_type', true);
    $answer_options = get_post_meta($post->ID, '_answer_options', true);
    $correct_answers = get_post_meta($post->ID, '_correct_answers', true);
    
    if (!is_array($answer_options)) {
        $answer_options = array();
    }
    if (!is_array($correct_answers)) {
        $correct_answers = array();
    }
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="question_text">Question Text</label></th>
            <td>
                <textarea id="question_text" name="question_text" rows="3" class="large-text" required><?php echo esc_textarea($question_text); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="answer_type">Answer Type</label></th>
            <td>
                <select id="answer_type" name="answer_type" required>
                    <option value="">Select Type</option>
                    <option value="radio" <?php selected($answer_type, 'radio'); ?>>Radio Button</option>
                    <option value="checkbox" <?php selected($answer_type, 'checkbox'); ?>>Checkbox</option>
                    <option value="dropdown" <?php selected($answer_type, 'dropdown'); ?>>Dropdown</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label>Answer Options</label></th>
            <td>
                <div id="answer_options_container">
                    <?php
                    if (!empty($answer_options)) {
                        foreach ($answer_options as $index => $option) {
                            $checked = in_array($index, $correct_answers) ? 'checked' : '';
                            ?>
                            <div class="answer-option-row" style="margin-bottom: 10px;">
                                <input type="text" name="answer_options[]" value="<?php echo esc_attr($option); ?>" class="regular-text" placeholder="Answer option">
                                <label style="margin-left: 10px;">
                                    <input type="<?php echo $answer_type === 'checkbox' ? 'checkbox' : 'radio'; ?>" 
                                           name="correct_answers[]" 
                                           value="<?php echo $index; ?>" 
                                           <?php echo $checked; ?>>
                                    Correct Answer
                                </label>
                                <button type="button" class="button remove-option" style="margin-left: 10px;">Remove</button>
                            </div>
                            <?php
                        }
                    } else {
                        ?>
                        <div class="answer-option-row" style="margin-bottom: 10px;">
                            <input type="text" name="answer_options[]" class="regular-text" placeholder="Answer option">
                            <label style="margin-left: 10px;">
                                <input type="radio" name="correct_answers[]" value="0">
                                Correct Answer
                            </label>
                            <button type="button" class="button remove-option" style="margin-left: 10px;">Remove</button>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <button type="button" id="add_answer_option" class="button">Add Answer Option</button>
            </td>
        </tr>
    </table>
    
    <script>
    jQuery(document).ready(function($) {
        var optionIndex = <?php echo count($answer_options); ?>;
        
        $('#add_answer_option').on('click', function() {
            var answerType = $('#answer_type').val() || 'radio';
            var inputType = answerType === 'checkbox' ? 'checkbox' : 'radio';
            var row = $('<div class="answer-option-row" style="margin-bottom: 10px;">' +
                '<input type="text" name="answer_options[]" class="regular-text" placeholder="Answer option">' +
                '<label style="margin-left: 10px;">' +
                '<input type="' + inputType + '" name="correct_answers[]" value="' + optionIndex + '">' +
                ' Correct Answer' +
                '</label>' +
                '<button type="button" class="button remove-option" style="margin-left: 10px;">Remove</button>' +
                '</div>');
            $('#answer_options_container').append(row);
            optionIndex++;
        });
        
        $(document).on('click', '.remove-option', function() {
            $(this).closest('.answer-option-row').remove();
        });
        
        $('#answer_type').on('change', function() {
            var answerType = $(this).val();
            var inputType = answerType === 'checkbox' ? 'checkbox' : 'radio';
            $('input[name="correct_answers[]"]').attr('type', inputType);
        });
    });
    </script>
    <?php
}

// Save Quiz Question Meta Data
function save_quiz_question_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!isset($_POST['quiz_question_meta_nonce']) || !wp_verify_nonce($_POST['quiz_question_meta_nonce'], 'save_quiz_question_meta')) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (get_post_type($post_id) !== 'quiz_question') {
        return;
    }
    
    if (isset($_POST['question_text'])) {
        update_post_meta($post_id, '_question_text', sanitize_textarea_field($_POST['question_text']));
    }
    
    if (isset($_POST['answer_type'])) {
        update_post_meta($post_id, '_answer_type', sanitize_text_field($_POST['answer_type']));
    }
    
    if (isset($_POST['answer_options'])) {
        $options = array_map('sanitize_text_field', $_POST['answer_options']);
        update_post_meta($post_id, '_answer_options', $options);
    } else {
        delete_post_meta($post_id, '_answer_options');
    }
    
    if (isset($_POST['correct_answers'])) {
        $correct = array_map('intval', $_POST['correct_answers']);
        update_post_meta($post_id, '_correct_answers', $correct);
    } else {
        delete_post_meta($post_id, '_correct_answers');
    }
}
// add_action('save_post', 'save_quiz_question_meta');

// Add custom columns to Quiz Questions list
function add_quiz_question_columns($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['question_text'] = 'Question Text';
    $new_columns['answer_type'] = 'Answer Type';
    $new_columns['quiz_branch'] = 'Branch';
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}
add_filter('manage_quiz_question_posts_columns', 'add_quiz_question_columns');

function populate_quiz_question_columns($column, $post_id) {
    switch ($column) {
        case 'question_text':
            $text = get_post_meta($post_id, '_question_text', true);
            echo esc_html(wp_trim_words($text, 10));
            break;
        case 'answer_type':
            $type = get_post_meta($post_id, '_answer_type', true);
            if ($type) {
                $types = array(
                    'radio' => 'Radio Button',
                    'checkbox' => 'Checkbox',
                    'dropdown' => 'Dropdown'
                );
                echo esc_html(isset($types[$type]) ? $types[$type] : ucfirst($type));
            } else {
                echo '—';
            }
            break;
        case 'quiz_branch':
            $terms = get_the_terms($post_id, 'quiz_branch');
            if ($terms && !is_wp_error($terms)) {
                $term_names = array();
                foreach ($terms as $term) {
                    $term_names[] = $term->name;
                }
                echo esc_html(implode(', ', $term_names));
            } else {
                echo '—';
            }
            break;
    }
}
// add_action('manage_quiz_question_posts_custom_column', 'populate_quiz_question_columns', 10, 2);

// Add custom columns to Quiz Attempts list
function add_quiz_attempt_columns($columns) {
    $new_columns = array();
    $new_columns['cb']    = isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />';
    $new_columns['title'] = __('Attempt ID', 'twentytwentyfour');
    $new_columns['quiz_email']  = __('Email', 'twentytwentyfour');
    $new_columns['quiz_score']  = __('Score', 'twentytwentyfour');
    $new_columns['quiz_date']   = __('Date', 'twentytwentyfour');
    return $new_columns;
}
add_filter('manage_quiz_attempt_posts_columns', 'add_quiz_attempt_columns');

function populate_quiz_attempt_columns($column, $post_id) {
    switch ($column) {
        case 'quiz_email':
            $email = get_post_meta($post_id, '_quiz_email', true);
            echo $email ? esc_html($email) : '—';
            break;
        case 'quiz_score':
            $correct = (int) get_post_meta($post_id, '_quiz_correct', true);
            $total   = (int) get_post_meta($post_id, '_quiz_total', true);
            if ($total > 0) {
                $percent = round(($correct / $total) * 100, 2);
                printf('%d / %d (%s%%)', $correct, $total, $percent);
            } else {
                echo '—';
            }
            break;
        case 'quiz_date':
            $post = get_post($post_id);
            if ($post) {
                echo esc_html(get_the_date('', $post));
            } else {
                echo '—';
            }
            break;
    }
}
// add_action('manage_quiz_attempt_posts_custom_column', 'populate_quiz_attempt_columns', 10, 2);

// Show full attempt details (questions and answers) on the single Quiz Attempt screen.
function add_quiz_attempt_meta_boxes() {
    add_meta_box(
        'quiz_attempt_details',
        __('Attempt Details', 'twentytwentyfour'),
        'render_quiz_attempt_meta_box',
        'quiz_attempt',
        'normal',
        'high'
    );
}
// add_action('add_meta_boxes', 'add_quiz_attempt_meta_boxes');

function render_quiz_attempt_meta_box($post) {
    $email   = get_post_meta($post->ID, '_quiz_email', true);
    $score   = get_post_meta($post->ID, '_quiz_score', true);
    $correct = (int) get_post_meta($post->ID, '_quiz_correct', true);
    $total   = (int) get_post_meta($post->ID, '_quiz_total', true);
    $results = get_post_meta($post->ID, '_quiz_results', true);

    if (!is_array($results)) {
        $results = array();
    }

    ?>
    <div id="quiz-attempt-details">
        <p><strong><?php esc_html_e('Email:', 'twentytwentyfour'); ?></strong> <?php echo esc_html($email); ?></p>
        <p>
            <strong><?php esc_html_e('Score:', 'twentytwentyfour'); ?></strong>
            <?php
            if ($total > 0) {
                $percent = round(($correct / $total) * 100, 2);
                printf(
                    '%d / %d (%s%%)',
                    $correct,
                    $total,
                    esc_html($percent)
                );
            } else {
                echo '—';
            }
            ?>
        </p>

        <?php if (!empty($results)) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:50px;"><?php esc_html_e('#', 'twentytwentyfour'); ?></th>
                        <th><?php esc_html_e('Question', 'twentytwentyfour'); ?></th>
                        <th><?php esc_html_e('Attended Answer', 'twentytwentyfour'); ?></th>
                        <th><?php esc_html_e('Correct Answer', 'twentytwentyfour'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('Status', 'twentytwentyfour'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $index => $row) : ?>
                        <tr>
                            <td><?php echo intval($index + 1); ?></td>
                            <td><?php echo isset($row['question']) ? esc_html($row['question']) : ''; ?></td>
                            <td><?php echo isset($row['user_answer']) ? esc_html($row['user_answer']) : ''; ?></td>
                            <td><?php echo isset($row['correct_answer']) ? esc_html($row['correct_answer']) : ''; ?></td>
                            <td>
                                <?php if (!empty($row['is_correct'])) : ?>
                                    <span style="color:#008000;font-weight:bold;"><?php esc_html_e('Correct', 'twentytwentyfour'); ?></span>
                                <?php else : ?>
                                    <span style="color:#cc0000;font-weight:bold;"><?php esc_html_e('Incorrect', 'twentytwentyfour'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No detailed results found for this attempt.', 'twentytwentyfour'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

// Enqueue Quiz Scripts and Styles
function enqueue_quiz_assets() {
    wp_enqueue_style('quiz-style', get_template_directory_uri() . '/quiz-style.css', array(), '1.0.0');
    wp_enqueue_script('quiz-script', get_template_directory_uri() . '/quiz-script.js', array('jquery'), '1.0.0', true);
    wp_localize_script('quiz-script', 'quizAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('quiz_submit_nonce')
    ));
}
// add_action('wp_enqueue_scripts', 'enqueue_quiz_assets');

// Quiz Shortcode
function quiz_shortcode($atts) {
    $atts = shortcode_atts(array(
        'branch' => '',
    ), $atts);
    
    $args = array(
        'post_type' => 'quiz_question',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'menu_order',
        'order' => 'ASC',
    );
    
    if (!empty($atts['branch'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'quiz_branch',
                'field' => 'slug',
                'terms' => $atts['branch'],
            ),
        );
    }
    
    $questions = get_posts($args);
    
    if (empty($questions)) {
        return '<p>No questions found.</p>';
    }
    
    ob_start();
    ?>
    <div class="quiz-container" data-quiz-id="<?php echo esc_attr(uniqid('quiz_')); ?>">

        
        <form id="quiz-form" class="quiz-form">
            <?php foreach ($questions as $index => $question) : 
                $question_text = get_post_meta($question->ID, '_question_text', true);
                $answer_type = get_post_meta($question->ID, '_answer_type', true);
                $answer_options = get_post_meta($question->ID, '_answer_options', true);
                $correct_answers = get_post_meta($question->ID, '_correct_answers', true);
                
                if (empty($answer_options) || !is_array($answer_options)) {
                    continue;
                }
                
                $is_first = $index === 0;
            ?>
                <div class="quiz-question" data-question-id="<?php echo $question->ID; ?>" <?php echo $is_first ? '' : 'style="display:none;"'; ?>>
                    <h3 class="question-title"><?php echo esc_html($question_text); ?></h3>
                    <div class="question-options">
                        <?php if ($answer_type === 'dropdown') : 
                            $input_name = 'question_' . $question->ID;
                            $input_id = 'q' . $question->ID . '_dropdown';
                        ?>
                            <div class="option-wrapper">
                                <select name="<?php echo $input_name; ?>" id="<?php echo $input_id; ?>" required>
                                    <option value="">Select an answer</option>
                                    <?php foreach ($answer_options as $opt_index => $option) : ?>
                                        <option value="<?php echo $opt_index; ?>"><?php echo esc_html($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else : 
                            foreach ($answer_options as $opt_index => $option) : 
                                $input_name = 'question_' . $question->ID;
                                $input_id = 'q' . $question->ID . '_opt' . $opt_index;
                        ?>
                            <div class="option-wrapper">
                                <?php if ($answer_type === 'radio') : ?>
                                    <input type="radio" 
                                           id="<?php echo $input_id; ?>" 
                                           name="<?php echo $input_name; ?>" 
                                           value="<?php echo $opt_index; ?>" 
                                           required>
                                    <label for="<?php echo $input_id; ?>"><?php echo esc_html($option); ?></label>
                                <?php elseif ($answer_type === 'checkbox') : ?>
                                    <input type="checkbox" 
                                           id="<?php echo $input_id; ?>" 
                                           name="<?php echo $input_name; ?>[]" 
                                           value="<?php echo $opt_index; ?>">
                                    <label for="<?php echo $input_id; ?>"><?php echo esc_html($option); ?></label>
                                <?php endif; ?>
                            </div>
                        <?php 
                            endforeach; 
                        endif; ?>
                    </div>
                    <div class="quiz-nav">
                        <button type="button" class="quiz-back-btn button button-secondary" <?php echo $is_first ? 'style="display:none;"' : ''; ?>>Back</button>
                        <button type="button" class="quiz-next-btn button" <?php echo $is_first ? '' : 'style="display:none;"'; ?>>Next Question</button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="quiz-email-section" style="display:none;">
                <h3>Quiz Completed!</h3>
                <p>Please enter your email to receive your results:</p>
                <input type="email" name="quiz_email" id="quiz_email" class="regular-text" placeholder="Enter your email" required>
                <button type="submit" class="quiz-submit-btn button">Submit Quiz</button>
            </div>
        </form>
		<div class="quiz-progress">
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%;">
                    <span class="progress-percentage">0%</span>
                </div>
            </div>
            <span class="progress-text">Question <span class="current-question">1</span> of <span class="total-questions"><?php echo count($questions); ?></span></span>
        </div>
        
        <div class="quiz-results" style="display:none;"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('quiz', 'quiz_shortcode');

// Handle Quiz Submission via AJAX
function handle_quiz_submission() {
    check_ajax_referer('quiz_submit_nonce', 'nonce');
    
    $email = sanitize_email($_POST['email']);
    $answers = isset($_POST['answers']) ? $_POST['answers'] : array();
    
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array('message' => 'Invalid email address.'));
    }
    
    $total_questions = 0;
    $correct_count = 0;
    $results = array();
    
    foreach ($answers as $question_id => $user_answer) {
        $question_id = intval($question_id);
        $question = get_post($question_id);
        
        if (!$question || $question->post_type !== 'quiz_question') {
            continue;
        }
        
        $total_questions++;
        $question_text = get_post_meta($question_id, '_question_text', true);
        $answer_options = get_post_meta($question_id, '_answer_options', true);
        $correct_answers = get_post_meta($question_id, '_correct_answers', true);
        
        if (!is_array($correct_answers)) {
            $correct_answers = array();
        }

        // Normalize user answers.
        if (is_array($user_answer)) {
            // Multiple answers (checkboxes).
            $user_answer_array = array_map('intval', $user_answer);
        } else {
            // Single answer (radio/dropdown).
            if ($user_answer === '' || $user_answer === null) {
                $user_answer_array = array();
            } else {
                $user_answer_array = array(intval($user_answer));
            }
        }

        // Normalize correct answers.
        $correct_answers = array_map('intval', $correct_answers);
        
        sort($user_answer_array);
        sort($correct_answers);
        
        $is_correct = ($user_answer_array === $correct_answers);
        
        if ($is_correct) {
            $correct_count++;
        }
        
        $user_answer_text = array();
        foreach ($user_answer_array as $ans_index) {
            if (isset($answer_options[$ans_index])) {
                $user_answer_text[] = $answer_options[$ans_index];
            }
        }
        
        $correct_answer_text = array();
        foreach ($correct_answers as $ans_index) {
            if (isset($answer_options[$ans_index])) {
                $correct_answer_text[] = $answer_options[$ans_index];
            }
        }
        
        $results[] = array(
            'question' => $question_text,
            'user_answer' => implode(', ', $user_answer_text),
            'correct_answer' => implode(', ', $correct_answer_text),
            'is_correct' => $is_correct,
        );
    }
    
    $score = $total_questions > 0 ? round(($correct_count / $total_questions) * 100, 2) : 0;

    // Store quiz attempt in admin (Quiz Attempts CPT)
    $attempt_title = sprintf(
        'Attempt by %s on %s',
        $email,
        current_time('mysql')
    );

    $attempt_post_id = wp_insert_post(array(
        'post_type'   => 'quiz_attempt',
        'post_status' => 'publish',
        'post_title'  => $attempt_title,
    ));

    if ($attempt_post_id && !is_wp_error($attempt_post_id)) {
        update_post_meta($attempt_post_id, '_quiz_email', $email);
        update_post_meta($attempt_post_id, '_quiz_score', $score);
        update_post_meta($attempt_post_id, '_quiz_correct', $correct_count);
        update_post_meta($attempt_post_id, '_quiz_total', $total_questions);
        update_post_meta($attempt_post_id, '_quiz_results', $results);
    }

    // Send Email
    $subject = 'Your Quiz Results';
    $message = "Your Quiz Results\n\n";
    $message .= "Score: {$correct_count} out of {$total_questions} ({$score}%)\n\n";
    $message .= "Detailed Results:\n";
    $message .= str_repeat("=", 50) . "\n\n";
    
    foreach ($results as $index => $result) {
        $question_num = $index + 1;
        $status = $result['is_correct'] ? '✓ Correct' : '✗ Incorrect';
        $message .= "Question {$question_num}: {$result['question']}\n";
        $message .= "Your Answer: {$result['user_answer']}\n";
        $message .= "Correct Answer: {$result['correct_answer']}\n";
        $message .= "Status: {$status}\n\n";
    }
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    $sent = wp_mail($email, $subject, $message, $headers);
    
    if ($sent) {
        wp_send_json_success(array(
            'message' => 'Results sent to your email!',
            'score' => $score,
            'correct' => $correct_count,
            'total' => $total_questions,
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to send email. Please try again.'));
    }
}
// add_action('wp_ajax_submit_quiz', 'handle_quiz_submission');
// add_action('wp_ajax_nopriv_submit_quiz', 'handle_quiz_submission');
