<?php
/**
 * Quiz Post Types and Taxonomies
 *
 * @package Quiz_System
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Quiz_Post_Types
 */
class Quiz_Post_Types {
	
	/**
	 * Single instance of the class
	 *
	 * @var Quiz_Post_Types
	 */
	private static $instance = null;
	
	/**
	 * Get single instance
	 *
	 * @return Quiz_Post_Types
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
		add_action( 'init', array( $this, 'register_quiz_branch_taxonomy' ) );
		add_action( 'init', array( $this, 'register_quiz_question_post_type' ) );
		add_action( 'init', array( $this, 'register_quiz_attempt_post_type' ) );
		add_action( 'admin_menu', array( $this, 'quiz_attempt_admin_lockdown' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'quiz_attempt_admin_bar_lockdown' ), 999 );
		add_action( 'load-post-new.php', array( $this, 'quiz_attempt_block_manual_create' ) );
	}
	
	/**
	 * Register Quiz Branch Taxonomy
	 */
	public function register_quiz_branch_taxonomy() {
		$labels = array(
			'name'              => __( 'Quiz Branches', 'quiz-system' ),
			'singular_name'     => __( 'Quiz Branch', 'quiz-system' ),
			'search_items'      => __( 'Search Branches', 'quiz-system' ),
			'all_items'         => __( 'All Branches', 'quiz-system' ),
			'parent_item'       => __( 'Parent Branch', 'quiz-system' ),
			'parent_item_colon' => __( 'Parent Branch:', 'quiz-system' ),
			'edit_item'         => __( 'Edit Branch', 'quiz-system' ),
			'update_item'       => __( 'Update Branch', 'quiz-system' ),
			'add_new_item'      => __( 'Add New Branch', 'quiz-system' ),
			'new_item_name'     => __( 'New Branch Name', 'quiz-system' ),
			'menu_name'         => __( 'Quiz Branches', 'quiz-system' ),
		);

		register_taxonomy( 'quiz_branch', 'quiz_question', array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'quiz-branch' ),
		) );
	}
	
	/**
	 * Register Quiz Question Custom Post Type
	 */
	public function register_quiz_question_post_type() {
		$labels = array(
			'name'               => __( 'Quiz Questions', 'quiz-system' ),
			'singular_name'      => __( 'Quiz Question', 'quiz-system' ),
			'menu_name'          => __( 'Quiz Questions', 'quiz-system' ),
			'add_new'            => __( 'Add New', 'quiz-system' ),
			'add_new_item'       => __( 'Add New Question', 'quiz-system' ),
			'edit_item'          => __( 'Edit Question', 'quiz-system' ),
			'new_item'           => __( 'New Question', 'quiz-system' ),
			'view_item'          => __( 'View Question', 'quiz-system' ),
			'search_items'       => __( 'Search Questions', 'quiz-system' ),
			'not_found'          => __( 'No questions found', 'quiz-system' ),
			'not_found_in_trash' => __( 'No questions found in trash', 'quiz-system' ),
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
			'supports'           => array( 'title' ),
		);

		register_post_type( 'quiz_question', $args );
	}
	
	/**
	 * Register Quiz Attempt Custom Post Type
	 */
	public function register_quiz_attempt_post_type() {
		$labels = array(
			'name'               => __( 'Quiz Attempts', 'quiz-system' ),
			'singular_name'      => __( 'Quiz Attempt', 'quiz-system' ),
			'menu_name'          => __( 'Quiz Attempts', 'quiz-system' ),
			'add_new'            => __( 'Add New', 'quiz-system' ),
			'add_new_item'       => __( 'Add New Attempt', 'quiz-system' ),
			'edit_item'          => __( 'Edit Attempt', 'quiz-system' ),
			'new_item'           => __( 'New Attempt', 'quiz-system' ),
			'view_item'          => __( 'View Attempt', 'quiz-system' ),
			'search_items'       => __( 'Search Attempts', 'quiz-system' ),
			'not_found'          => __( 'No attempts found', 'quiz-system' ),
			'not_found_in_trash' => __( 'No attempts found in trash', 'quiz-system' ),
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
			'supports'           => array( 'title' ),
		);

		register_post_type( 'quiz_attempt', $args );
	}
	
	/**
	 * Lock down Quiz Attempts in admin: no manual add/edit, view only
	 */
	public function quiz_attempt_admin_lockdown() {
		// Remove "Add New" submenu item
		remove_submenu_page( 'edit.php?post_type=quiz_attempt', 'post-new.php?post_type=quiz_attempt' );
	}
	
	/**
	 * Remove "New Quiz Attempt" from admin bar
	 */
	public function quiz_attempt_admin_bar_lockdown( $wp_admin_bar ) {
		$wp_admin_bar->remove_node( 'new-quiz_attempt' );
	}
	
	/**
	 * Block direct access to post-new.php for quiz_attempt
	 */
	public function quiz_attempt_block_manual_create() {
		$screen = get_current_screen();
		if ( $screen && $screen->post_type === 'quiz_attempt' ) {
			wp_die( __( 'Quiz attempts are created automatically from the quiz form and cannot be created manually.', 'quiz-system' ) );
		}
	}
}

