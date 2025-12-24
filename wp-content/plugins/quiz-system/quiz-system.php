<?php
/**
 * Plugin Name: Quiz System
 * Plugin URI: https://example.com/quiz-system
 * Description: A comprehensive quiz system for WordPress with custom post types, taxonomies, and frontend quiz functionality.
 * Version: 1.0.0
 * Author: Sangita Singh
 * Author URI: https://example.com
 * Text Domain: quiz-system
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'QUIZ_SYSTEM_VERSION', '1.0.0' );
define( 'QUIZ_SYSTEM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QUIZ_SYSTEM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QUIZ_SYSTEM_PLUGIN_FILE', __FILE__ );

/**
 * Main Quiz System Class
 */
class Quiz_System {
	
	/**
	 * Single instance of the class
	 *
	 * @var Quiz_System
	 */
	private static $instance = null;
	
	/**
	 * Get single instance
	 *
	 * @return Quiz_System
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
		$this->includes();
		$this->init_hooks();
	}
	
	/**
	 * Include required files
	 */
	private function includes() {
		require_once QUIZ_SYSTEM_PLUGIN_DIR . 'includes/class-quiz-post-types.php';
		require_once QUIZ_SYSTEM_PLUGIN_DIR . 'includes/class-quiz-admin.php';
		require_once QUIZ_SYSTEM_PLUGIN_DIR . 'includes/class-quiz-frontend.php';
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}
	
	/**
	 * Initialize plugin
	 */
	public function init() {
		// Initialize classes
		Quiz_Post_Types::instance();
		Quiz_Admin::instance();
		Quiz_Frontend::instance();
		
		// Load text domain
		load_plugin_textdomain( 'quiz-system', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
	
	/**
	 * Plugin activation
	 */
	public function activate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin
 */
function quiz_system() {
	return Quiz_System::instance();
}

// Start the plugin
quiz_system();

