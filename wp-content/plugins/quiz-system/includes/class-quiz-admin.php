<?php
/**
 * Quiz Admin Functionality
 *
 * @package Quiz_System
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Quiz_Admin
 */
class Quiz_Admin {
	
	/**
	 * Single instance of the class
	 *
	 * @var Quiz_Admin
	 */
	private static $instance = null;
	
	/**
	 * Get single instance
	 *
	 * @return Quiz_Admin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_quiz_question_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_quiz_question_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_quiz_attempt_meta_boxes' ) );
		add_filter( 'manage_quiz_question_posts_columns', array( $this, 'add_quiz_question_columns' ) );
		add_action( 'manage_quiz_question_posts_custom_column', array( $this, 'populate_quiz_question_columns' ), 10, 2 );
		add_filter( 'manage_quiz_attempt_posts_columns', array( $this, 'add_quiz_attempt_columns' ) );
		add_action( 'manage_quiz_attempt_posts_custom_column', array( $this, 'populate_quiz_attempt_columns' ), 10, 2 );
		add_action( 'admin_head-post.php', array( $this, 'quiz_attempt_make_read_only' ) );
		add_action( 'admin_head-edit.php', array( $this, 'quiz_attempt_hide_add_new_on_list' ) );
		add_filter( 'post_row_actions', array( $this, 'quiz_attempt_remove_row_actions' ), 10, 2 );
	}
	
	/**
	 * Add Meta Boxes for Quiz Questions
	 */
	public function add_quiz_question_meta_boxes() {
		add_meta_box(
			'quiz_question_details',
			__( 'Question Details', 'quiz-system' ),
			array( $this, 'render_quiz_question_meta_box' ),
			'quiz_question',
			'normal',
			'high'
		);
	}
	
	/**
	 * Render Quiz Question Meta Box
	 */
	public function render_quiz_question_meta_box( $post ) {
		wp_nonce_field( 'save_quiz_question_meta', 'quiz_question_meta_nonce' );
		
		$question_text = get_post_meta( $post->ID, '_question_text', true );
		$answer_type = get_post_meta( $post->ID, '_answer_type', true );
		$answer_options = get_post_meta( $post->ID, '_answer_options', true );
		$correct_answers = get_post_meta( $post->ID, '_correct_answers', true );
		
		if ( ! is_array( $answer_options ) ) {
			$answer_options = array();
		}
		if ( ! is_array( $correct_answers ) ) {
			$correct_answers = array();
		}
		
		?>
		<table class="form-table">
			<tr>
				<th><label for="question_text"><?php esc_html_e( 'Question Text', 'quiz-system' ); ?></label></th>
				<td>
					<textarea id="question_text" name="question_text" rows="3" class="large-text" required><?php echo esc_textarea( $question_text ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th><label for="answer_type"><?php esc_html_e( 'Answer Type', 'quiz-system' ); ?></label></th>
				<td>
					<select id="answer_type" name="answer_type" required>
						<option value=""><?php esc_html_e( 'Select Type', 'quiz-system' ); ?></option>
						<option value="radio" <?php selected( $answer_type, 'radio' ); ?>><?php esc_html_e( 'Radio Button', 'quiz-system' ); ?></option>
						<option value="checkbox" <?php selected( $answer_type, 'checkbox' ); ?>><?php esc_html_e( 'Checkbox', 'quiz-system' ); ?></option>
						<option value="dropdown" <?php selected( $answer_type, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'quiz-system' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Answer Options', 'quiz-system' ); ?></label></th>
				<td>
					<div id="answer_options_container">
						<?php
						if ( ! empty( $answer_options ) ) {
							foreach ( $answer_options as $index => $option ) {
								$checked = in_array( $index, $correct_answers ) ? 'checked' : '';
								?>
								<div class="answer-option-row" style="margin-bottom: 10px;">
									<input type="text" name="answer_options[]" value="<?php echo esc_attr( $option ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Answer option', 'quiz-system' ); ?>">
									<label style="margin-left: 10px;">
										<input type="<?php echo $answer_type === 'checkbox' ? 'checkbox' : 'radio'; ?>" 
											   name="correct_answers[]" 
											   value="<?php echo $index; ?>" 
											   <?php echo $checked; ?>>
										<?php esc_html_e( 'Correct Answer', 'quiz-system' ); ?>
									</label>
									<button type="button" class="button remove-option" style="margin-left: 10px;"><?php esc_html_e( 'Remove', 'quiz-system' ); ?></button>
								</div>
								<?php
							}
						} else {
							?>
							<div class="answer-option-row" style="margin-bottom: 10px;">
								<input type="text" name="answer_options[]" class="regular-text" placeholder="<?php esc_attr_e( 'Answer option', 'quiz-system' ); ?>">
								<label style="margin-left: 10px;">
									<input type="radio" name="correct_answers[]" value="0">
									<?php esc_html_e( 'Correct Answer', 'quiz-system' ); ?>
								</label>
								<button type="button" class="button remove-option" style="margin-left: 10px;"><?php esc_html_e( 'Remove', 'quiz-system' ); ?></button>
							</div>
							<?php
						}
						?>
					</div>
					<button type="button" id="add_answer_option" class="button"><?php esc_html_e( 'Add Answer Option', 'quiz-system' ); ?></button>
				</td>
			</tr>
		</table>
		
		<script>
		jQuery(document).ready(function($) {
			var optionIndex = <?php echo count( $answer_options ); ?>;
			
			$('#add_answer_option').on('click', function() {
				var answerType = $('#answer_type').val() || 'radio';
				var inputType = answerType === 'checkbox' ? 'checkbox' : 'radio';
				var row = $('<div class="answer-option-row" style="margin-bottom: 10px;">' +
					'<input type="text" name="answer_options[]" class="regular-text" placeholder="<?php esc_attr_e( 'Answer option', 'quiz-system' ); ?>">' +
					'<label style="margin-left: 10px;">' +
					'<input type="' + inputType + '" name="correct_answers[]" value="' + optionIndex + '">' +
					' <?php esc_html_e( 'Correct Answer', 'quiz-system' ); ?>' +
					'</label>' +
					'<button type="button" class="button remove-option" style="margin-left: 10px;"><?php esc_html_e( 'Remove', 'quiz-system' ); ?></button>' +
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
	
	/**
	 * Save Quiz Question Meta Data
	 */
	public function save_quiz_question_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( ! isset( $_POST['quiz_question_meta_nonce'] ) || ! wp_verify_nonce( $_POST['quiz_question_meta_nonce'], 'save_quiz_question_meta' ) ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		if ( get_post_type( $post_id ) !== 'quiz_question' ) {
			return;
		}
		
		if ( isset( $_POST['question_text'] ) ) {
			update_post_meta( $post_id, '_question_text', sanitize_textarea_field( $_POST['question_text'] ) );
		}
		
		if ( isset( $_POST['answer_type'] ) ) {
			update_post_meta( $post_id, '_answer_type', sanitize_text_field( $_POST['answer_type'] ) );
		}
		
		if ( isset( $_POST['answer_options'] ) ) {
			$options = array_map( 'sanitize_text_field', $_POST['answer_options'] );
			update_post_meta( $post_id, '_answer_options', $options );
		} else {
			delete_post_meta( $post_id, '_answer_options' );
		}
		
		if ( isset( $_POST['correct_answers'] ) ) {
			$correct = array_map( 'intval', $_POST['correct_answers'] );
			update_post_meta( $post_id, '_correct_answers', $correct );
		} else {
			delete_post_meta( $post_id, '_correct_answers' );
		}
	}
	
	/**
	 * Add custom columns to Quiz Questions list
	 */
	public function add_quiz_question_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];
		$new_columns['question_text'] = __( 'Question Text', 'quiz-system' );
		$new_columns['answer_type'] = __( 'Answer Type', 'quiz-system' );
		$new_columns['quiz_branch'] = __( 'Branch', 'quiz-system' );
		$new_columns['date'] = $columns['date'];
		return $new_columns;
	}
	
	/**
	 * Populate custom columns for Quiz Questions
	 */
	public function populate_quiz_question_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'question_text':
				$text = get_post_meta( $post_id, '_question_text', true );
				echo esc_html( wp_trim_words( $text, 10 ) );
				break;
			case 'answer_type':
				$type = get_post_meta( $post_id, '_answer_type', true );
				if ( $type ) {
					$types = array(
						'radio' => __( 'Radio Button', 'quiz-system' ),
						'checkbox' => __( 'Checkbox', 'quiz-system' ),
						'dropdown' => __( 'Dropdown', 'quiz-system' )
					);
					echo esc_html( isset( $types[ $type ] ) ? $types[ $type ] : ucfirst( $type ) );
				} else {
					echo '—';
				}
				break;
			case 'quiz_branch':
				$terms = get_the_terms( $post_id, 'quiz_branch' );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$term_names = array();
					foreach ( $terms as $term ) {
						$term_names[] = $term->name;
					}
					echo esc_html( implode( ', ', $term_names ) );
				} else {
					echo '—';
				}
				break;
		}
	}
	
	/**
	 * Add custom columns to Quiz Attempts list
	 */
	public function add_quiz_attempt_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb']    = isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />';
		$new_columns['title'] = __( 'Attempt ID', 'quiz-system' );
		$new_columns['quiz_email']  = __( 'Email', 'quiz-system' );
		$new_columns['quiz_score']  = __( 'Score', 'quiz-system' );
		$new_columns['quiz_date']   = __( 'Date', 'quiz-system' );
		return $new_columns;
	}
	
	/**
	 * Populate custom columns for Quiz Attempts
	 */
	public function populate_quiz_attempt_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'quiz_email':
				$email = get_post_meta( $post_id, '_quiz_email', true );
				echo $email ? esc_html( $email ) : '—';
				break;
			case 'quiz_score':
				$correct = (int) get_post_meta( $post_id, '_quiz_correct', true );
				$total   = (int) get_post_meta( $post_id, '_quiz_total', true );
				if ( $total > 0 ) {
					$percent = round( ( $correct / $total ) * 100, 2 );
					printf( '%d / %d (%s%%)', $correct, $total, $percent );
				} else {
					echo '—';
				}
				break;
			case 'quiz_date':
				$post = get_post( $post_id );
				if ( $post ) {
					echo esc_html( get_the_date( '', $post ) );
				} else {
					echo '—';
				}
				break;
		}
	}
	
	/**
	 * Add Meta Boxes for Quiz Attempts
	 */
	public function add_quiz_attempt_meta_boxes() {
		add_meta_box(
			'quiz_attempt_details',
			__( 'Attempt Details', 'quiz-system' ),
			array( $this, 'render_quiz_attempt_meta_box' ),
			'quiz_attempt',
			'normal',
			'high'
		);
	}
	
	/**
	 * Render Quiz Attempt Meta Box
	 */
	public function render_quiz_attempt_meta_box( $post ) {
		$email   = get_post_meta( $post->ID, '_quiz_email', true );
		$score   = get_post_meta( $post->ID, '_quiz_score', true );
		$correct = (int) get_post_meta( $post->ID, '_quiz_correct', true );
		$total   = (int) get_post_meta( $post->ID, '_quiz_total', true );
		$results = get_post_meta( $post->ID, '_quiz_results', true );

		if ( ! is_array( $results ) ) {
			$results = array();
		}

		?>
		<div id="quiz-attempt-details">
			<p><strong><?php esc_html_e( 'Email:', 'quiz-system' ); ?></strong> <?php echo esc_html( $email ); ?></p>
			<p>
				<strong><?php esc_html_e( 'Score:', 'quiz-system' ); ?></strong>
				<?php
				if ( $total > 0 ) {
					$percent = round( ( $correct / $total ) * 100, 2 );
					printf(
						'%d / %d (%s%%)',
						$correct,
						$total,
						esc_html( $percent )
					);
				} else {
					echo '—';
				}
				?>
			</p>

			<?php if ( ! empty( $results ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:50px;"><?php esc_html_e( '#', 'quiz-system' ); ?></th>
							<th><?php esc_html_e( 'Question', 'quiz-system' ); ?></th>
							<th><?php esc_html_e( 'Attended Answer', 'quiz-system' ); ?></th>
							<th><?php esc_html_e( 'Correct Answer', 'quiz-system' ); ?></th>
							<th style="width:120px;"><?php esc_html_e( 'Status', 'quiz-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $results as $index => $row ) : ?>
							<tr>
								<td><?php echo intval( $index + 1 ); ?></td>
								<td><?php echo isset( $row['question'] ) ? esc_html( $row['question'] ) : ''; ?></td>
								<td><?php echo isset( $row['user_answer'] ) ? esc_html( $row['user_answer'] ) : ''; ?></td>
								<td><?php echo isset( $row['correct_answer'] ) ? esc_html( $row['correct_answer'] ) : ''; ?></td>
								<td>
									<?php if ( ! empty( $row['is_correct'] ) ) : ?>
										<span style="color:#008000;font-weight:bold;"><?php esc_html_e( 'Correct', 'quiz-system' ); ?></span>
									<?php else : ?>
										<span style="color:#cc0000;font-weight:bold;"><?php esc_html_e( 'Incorrect', 'quiz-system' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No detailed results found for this attempt.', 'quiz-system' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
	
	/**
	 * Make quiz_attempt edit screen read-only
	 */
	public function quiz_attempt_make_read_only() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'quiz_attempt' ) {
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
	
	/**
	 * Hide "Add New" button on the Quiz Attempts list screen
	 */
	public function quiz_attempt_hide_add_new_on_list() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'quiz_attempt' ) {
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
	
	/**
	 * Remove Edit and Quick Edit row actions for Quiz Attempts
	 */
	public function quiz_attempt_remove_row_actions( $actions, $post ) {
		if ( $post->post_type === 'quiz_attempt' ) {
			if ( isset( $actions['edit'] ) ) {
				unset( $actions['edit'] );
			}
			// Quick Edit action key is 'inline hide-if-no-js'
			if ( isset( $actions['inline hide-if-no-js'] ) ) {
				unset( $actions['inline hide-if-no-js'] );
			}
		}
		return $actions;
	}
}

