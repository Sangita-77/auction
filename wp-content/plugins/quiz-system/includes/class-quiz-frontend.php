<?php
/**
 * Quiz Frontend Functionality
 *
 * @package Quiz_System
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Quiz_Frontend
 */
class Quiz_Frontend {
	
	/**
	 * Single instance of the class
	 *
	 * @var Quiz_Frontend
	 */
	private static $instance = null;
	
	/**
	 * Get single instance
	 *
	 * @return Quiz_Frontend
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'quiz', array( $this, 'quiz_shortcode' ) );
		add_action( 'wp_ajax_submit_quiz', array( $this, 'handle_quiz_submission' ) );
		add_action( 'wp_ajax_nopriv_submit_quiz', array( $this, 'handle_quiz_submission' ) );
	}
	
	/**
	 * Enqueue Quiz Scripts and Styles
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 
			'quiz-system-style', 
			QUIZ_SYSTEM_PLUGIN_URL . 'assets/css/quiz-style.css', 
			array(), 
			QUIZ_SYSTEM_VERSION 
		);
		wp_enqueue_script( 
			'quiz-system-script', 
			QUIZ_SYSTEM_PLUGIN_URL . 'assets/js/quiz-script.js', 
			array( 'jquery' ), 
			QUIZ_SYSTEM_VERSION, 
			true 
		);
		wp_localize_script( 'quiz-system-script', 'quizAjax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'quiz_submit_nonce' )
		) );
	}
	
	/**
	 * Quiz Shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function quiz_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'branch' => '',
		), $atts );
		
		$args = array(
			'post_type' => 'quiz_question',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'orderby' => 'menu_order',
			'order' => 'ASC',
		);
		
		if ( ! empty( $atts['branch'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'quiz_branch',
					'field' => 'slug',
					'terms' => $atts['branch'],
				),
			);
		}
		
		$questions = get_posts( $args );
		
		if ( empty( $questions ) ) {
			return '<p>' . esc_html__( 'No questions found.', 'quiz-system' ) . '</p>';
		}
		
		ob_start();
		?>
		<div class="quiz-container" data-quiz-id="<?php echo esc_attr( uniqid( 'quiz_' ) ); ?>">
			<form id="quiz-form" class="quiz-form">
				<?php foreach ( $questions as $index => $question ) : 
					$question_text = get_post_meta( $question->ID, '_question_text', true );
					$answer_type = get_post_meta( $question->ID, '_answer_type', true );
					$answer_options = get_post_meta( $question->ID, '_answer_options', true );
					$correct_answers = get_post_meta( $question->ID, '_correct_answers', true );
					
					if ( empty( $answer_options ) || ! is_array( $answer_options ) ) {
						continue;
					}
					
					$is_first = $index === 0;
				?>
					<div class="quiz-question" data-question-id="<?php echo $question->ID; ?>" <?php echo $is_first ? '' : 'style="display:none;"'; ?>>
						<h3 class="question-title"><?php echo esc_html( $question_text ); ?></h3>
						<div class="question-options">
							<?php if ( $answer_type === 'dropdown' ) : 
								$input_name = 'question_' . $question->ID;
								$input_id = 'q' . $question->ID . '_dropdown';
							?>
								<div class="option-wrapper">
									<select name="<?php echo $input_name; ?>" id="<?php echo $input_id; ?>" required>
										<option value=""><?php esc_html_e( 'Select an answer', 'quiz-system' ); ?></option>
										<?php foreach ( $answer_options as $opt_index => $option ) : ?>
											<option value="<?php echo $opt_index; ?>"><?php echo esc_html( $option ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php else : 
								foreach ( $answer_options as $opt_index => $option ) : 
									$input_name = 'question_' . $question->ID;
									$input_id = 'q' . $question->ID . '_opt' . $opt_index;
							?>
								<div class="option-wrapper">
									<?php if ( $answer_type === 'radio' ) : ?>
										<input type="radio" 
											   id="<?php echo $input_id; ?>" 
											   name="<?php echo $input_name; ?>" 
											   value="<?php echo $opt_index; ?>" 
											   required>
										<label for="<?php echo $input_id; ?>"><?php echo esc_html( $option ); ?></label>
									<?php elseif ( $answer_type === 'checkbox' ) : ?>
										<input type="checkbox" 
											   id="<?php echo $input_id; ?>" 
											   name="<?php echo $input_name; ?>[]" 
											   value="<?php echo $opt_index; ?>">
										<label for="<?php echo $input_id; ?>"><?php echo esc_html( $option ); ?></label>
									<?php endif; ?>
								</div>
							<?php 
								endforeach; 
							endif; ?>
						</div>
						<div class="quiz-nav">
							<button type="button" class="quiz-back-btn button button-secondary" <?php echo $is_first ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Back', 'quiz-system' ); ?></button>
							<button type="button" class="quiz-next-btn button" <?php echo $is_first ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Next Question', 'quiz-system' ); ?></button>
						</div>
					</div>
				<?php endforeach; ?>
				
				<div class="quiz-email-section" style="display:none;">
					<h3><?php esc_html_e( 'Quiz Completed!', 'quiz-system' ); ?></h3>
					<p><?php esc_html_e( 'Please enter your email to receive your results:', 'quiz-system' ); ?></p>
					<input type="email" name="quiz_email" id="quiz_email" class="regular-text" placeholder="<?php esc_attr_e( 'Enter your email', 'quiz-system' ); ?>" required>
					<button type="submit" class="quiz-submit-btn button"><?php esc_html_e( 'Submit Quiz', 'quiz-system' ); ?></button>
				</div>
			</form>
			
			<div class="quiz-results" style="display:none;"></div>

			<div class="quiz-progress">
				<div class="progress-bar">
					<div class="progress-fill" style="width: 0%;">
						<span class="progress-percentage">0%</span>
					</div>
				</div>
				<span class="progress-text"><?php esc_html_e( 'Question', 'quiz-system' ); ?> <span class="current-question">1</span> <?php esc_html_e( 'of', 'quiz-system' ); ?> <span class="total-questions"><?php echo count( $questions ); ?></span></span>
			</div>
			
			<!-- Quiz Modal -->
			<div class="quiz-modal" style="display: none;">
				<div class="quiz-modal-overlay"></div>
				<div class="quiz-modal-content">
					<div class="quiz-modal-header">
						<h3 class="quiz-modal-title"></h3>
						<button type="button" class="quiz-modal-close" aria-label="<?php esc_attr_e( 'Close', 'quiz-system' ); ?>">&times;</button>
					</div>
					<div class="quiz-modal-body">
						<p class="quiz-modal-message"></p>
					</div>
					<div class="quiz-modal-footer">
						<button type="button" class="quiz-modal-btn quiz-modal-close"><?php esc_html_e( 'OK', 'quiz-system' ); ?></button>
					</div>
				</div>
			</div>
			
		</div>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * Handle Quiz Submission via AJAX
	 */
	public function handle_quiz_submission() {
		check_ajax_referer( 'quiz_submit_nonce', 'nonce' );
		
		$email = sanitize_email( $_POST['email'] );
		$answers = isset( $_POST['answers'] ) ? $_POST['answers'] : array();
		
		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'quiz-system' ) ) );
		}
		
		$total_questions = 0;
		$correct_count = 0;
		$results = array();
		
		foreach ( $answers as $question_id => $user_answer ) {
			$question_id = intval( $question_id );
			$question = get_post( $question_id );
			
			if ( ! $question || $question->post_type !== 'quiz_question' ) {
				continue;
			}
			
			$total_questions++;
			$question_text = get_post_meta( $question_id, '_question_text', true );
			$answer_options = get_post_meta( $question_id, '_answer_options', true );
			$correct_answers = get_post_meta( $question_id, '_correct_answers', true );
			
			if ( ! is_array( $correct_answers ) ) {
				$correct_answers = array();
			}

			// Normalize user answers
			if ( is_array( $user_answer ) ) {
				// Multiple answers (checkboxes)
				$user_answer_array = array_map( 'intval', $user_answer );
			} else {
				// Single answer (radio/dropdown)
				if ( $user_answer === '' || $user_answer === null ) {
					$user_answer_array = array();
				} else {
					$user_answer_array = array( intval( $user_answer ) );
				}
			}

			// Normalize correct answers
			$correct_answers = array_map( 'intval', $correct_answers );
			
			sort( $user_answer_array );
			sort( $correct_answers );
			
			$is_correct = ( $user_answer_array === $correct_answers );
			
			if ( $is_correct ) {
				$correct_count++;
			}
			
			$user_answer_text = array();
			foreach ( $user_answer_array as $ans_index ) {
				if ( isset( $answer_options[ $ans_index ] ) ) {
					$user_answer_text[] = $answer_options[ $ans_index ];
				}
			}
			
			$correct_answer_text = array();
			foreach ( $correct_answers as $ans_index ) {
				if ( isset( $answer_options[ $ans_index ] ) ) {
					$correct_answer_text[] = $answer_options[ $ans_index ];
				}
			}
			
			$results[] = array(
				'question' => $question_text,
				'user_answer' => implode( ', ', $user_answer_text ),
				'correct_answer' => implode( ', ', $correct_answer_text ),
				'is_correct' => $is_correct,
			);
		}
		
		$score = $total_questions > 0 ? round( ( $correct_count / $total_questions ) * 100, 2 ) : 0;

		// Store quiz attempt in admin (Quiz Attempts CPT)
		$attempt_title = sprintf(
			__( 'Attempt by %s on %s', 'quiz-system' ),
			$email,
			current_time( 'mysql' )
		);

		$attempt_post_id = wp_insert_post( array(
			'post_type'   => 'quiz_attempt',
			'post_status' => 'publish',
			'post_title'  => $attempt_title,
		) );

		if ( $attempt_post_id && ! is_wp_error( $attempt_post_id ) ) {
			update_post_meta( $attempt_post_id, '_quiz_email', $email );
			update_post_meta( $attempt_post_id, '_quiz_score', $score );
			update_post_meta( $attempt_post_id, '_quiz_correct', $correct_count );
			update_post_meta( $attempt_post_id, '_quiz_total', $total_questions );
			update_post_meta( $attempt_post_id, '_quiz_results', $results );
		}

		// Send Email
		$subject = __( 'Your Quiz Results', 'quiz-system' );
		$message = __( 'Your Quiz Results', 'quiz-system' ) . "\n\n";
		$message .= sprintf( __( 'Score: %d out of %d (%s%%)', 'quiz-system' ), $correct_count, $total_questions, $score ) . "\n\n";
		$message .= __( 'Detailed Results:', 'quiz-system' ) . "\n";
		$message .= str_repeat( "=", 50 ) . "\n\n";
		
		foreach ( $results as $index => $result ) {
			$question_num = $index + 1;
			$status = $result['is_correct'] ? __( '✓ Correct', 'quiz-system' ) : __( '✗ Incorrect', 'quiz-system' );
			$message .= sprintf( __( 'Question %d: %s', 'quiz-system' ), $question_num, $result['question'] ) . "\n";
			$message .= sprintf( __( 'Your Answer: %s', 'quiz-system' ), $result['user_answer'] ) . "\n";
			$message .= sprintf( __( 'Correct Answer: %s', 'quiz-system' ), $result['correct_answer'] ) . "\n";
			$message .= sprintf( __( 'Status: %s', 'quiz-system' ), $status ) . "\n\n";
		}
		
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent = wp_mail( $email, $subject, $message, $headers );
		
		if ( $sent ) {
			wp_send_json_success( array(
				'message' => __( 'Results sent to your email!', 'quiz-system' ),
				'score' => $score,
				'correct' => $correct_count,
				'total' => $total_questions,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send email. Please try again.', 'quiz-system' ) ) );
		}
	}
}

