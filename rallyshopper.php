<?php
/**
 * Plugin Name: RallyShopper
 * Description: Recipe management with Kroger API integration for grocery shopping
 * Version: 1.0.0
 * Author: Radagast
 * Text Domain: rallyshopper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RALLYSHOPPER_VERSION', '1.0.0' );
define( 'RALLYSHOPPER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RALLYSHOPPER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RALLYSHOPPER_DB_VERSION', '1.0.0' );

// Autoloader
spl_autoload_register( function ( $class ) {
    $prefix = 'RallyShopper_';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }
    $file = RALLYSHOPPER_PLUGIN_DIR . 'includes/class-' . str_replace( '_', '-', strtolower( str_replace( $prefix, '', $class ) ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Activation hook
register_activation_hook( __FILE__, 'rallyshopper_activate' );
function rallyshopper_activate() {
    require_once RALLYSHOPPER_PLUGIN_DIR . 'includes/class-database.php';
    $database = new RallyShopper_Database();
    $database->install();
    
    // Schedule token refresh cron
    if ( ! wp_next_scheduled( 'rallyshopper_refresh_kroger_token' ) ) {
        wp_schedule_event( time(), 'hourly', 'rallyshopper_refresh_kroger_token' );
    }
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'rallyshopper_deactivate' );
function rallyshopper_deactivate() {
    wp_clear_scheduled_hook( 'rallyshopper_refresh_kroger_token' );
}

// Initialize plugin
add_action( 'plugins_loaded', 'rallyshopper_init' );
function rallyshopper_init() {
    RallyShopper::instance();
}

// Main plugin class
class RallyShopper {
    private static $instance = null;
    
    private function __construct() {
        $this->load_classes();
        $this->init_hooks();
    }
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function load_classes() {
        require_once RALLYSHOPPER_PLUGIN_DIR . 'includes/class-database.php';
        require_once RALLYSHOPPER_PLUGIN_DIR . 'includes/class-kroger-api.php';
        require_once RALLYSHOPPER_PLUGIN_DIR . 'includes/class-recipe.php';
        require_once RALLYSHOPPER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once RALLYSHOPPER_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once RALLYSHOPPER_PLUGIN_DIR . 'includes/class-auth.php';
    }
    
    private function init_hooks() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
        add_action( 'rallyshopper_refresh_kroger_token', array( 'RallyShopper_Auth', 'refresh_token' ) );
    }
    
    public function init() {
        // Initialize post type
        RallyShopper_Recipe::register_post_type();
        
        // Initialize AJAX
        new RallyShopper_AJAX();
        
        // Initialize Admin (registers menus and meta boxes)
        new RallyShopper_Admin();
    }
    
    public function enqueue_admin_assets( $hook ) {
        // Load on rallyshopper admin pages
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $is_rallyshopper_page = (strpos($hook, 'rallyshopper') !== false) || 
                              (strpos($page, 'rallyshopper') === 0) ||
                              (strpos($hook, 'toplevel_page_rallyshopper') !== false);
        
        // Also load on post editor for rallyshopper_recipe post type
        $is_rallyshopper_post = false;
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
            $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
            if ( $post_type === 'rallyshopper_recipe' ) {
                $is_rallyshopper_post = true;
            } elseif ( $post_id ) {
                $post = get_post($post_id);
                if ( $post && $post->post_type === 'rallyshopper_recipe' ) {
                    $is_rallyshopper_post = true;
                }
            }
        }
        
        if ( !$is_rallyshopper_page && !$is_rallyshopper_post ) {
            return;
        }
        
        wp_enqueue_style( 'rallyshopper-admin', RALLYSHOPPER_PLUGIN_URL . 'assets/css/admin.css', array(), RALLYSHOPPER_VERSION );
        wp_enqueue_script( 'rallyshopper-admin', RALLYSHOPPER_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), RALLYSHOPPER_VERSION, true );
        
        wp_localize_script( 'rallyshopper-admin', 'rallyshopper_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'rallyshopper_nonce' ),
        ) );
    }
    
    public function enqueue_public_assets() {
        // Frontend assets if needed
    }
    
    // Get database instance
    public function get_db() {
        return new RallyShopper_Database();
    }
    
    // Get Kroger API instance
    public function get_kroger() {
        return new RallyShopper_Kroger_API();
    }
    
    // Log helper
    public static function log( $message, $type = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[RallyShopper] ' . $type . ': ' . $message );
        }
    }
}
