<?php
/**
 * Plugin Name: News X AI Generator
 * Plugin URI: https://github.com/elephormy/news-x-ai-generator
 * Description: Automatically generate professional news articles using Gemini AI API and high-quality images for ANY WordPress theme
 * Version: 1.0.0
 * Author: Younes EL ALAMI
 * Author URI: https://elmyns.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: news-x-ai-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NEWS_X_AI_VERSION', '1.0.0');
define('NEWS_X_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEWS_X_AI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NEWS_X_AI_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Main plugin class
class NewsX_AI_Generator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize admin immediately to register AJAX hooks
        if (is_admin()) {
            new NewsX_AI_Admin();
        }
        
        // Initialize hooks
        add_action('init', array($this, 'init_hooks'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    private function load_dependencies() {
        require_once NEWS_X_AI_PLUGIN_PATH . 'includes/class-news-x-ai-admin.php';
        require_once NEWS_X_AI_PLUGIN_PATH . 'includes/class-news-x-ai-generator.php';
        require_once NEWS_X_AI_PLUGIN_PATH . 'includes/class-news-x-ai-image-generator.php';
        require_once NEWS_X_AI_PLUGIN_PATH . 'includes/class-news-x-ai-scheduler.php';
    }
    
    public function init_hooks() {
        // Initialize generator
        new NewsX_AI_Generator_Core();
        
        // Initialize scheduler
        new NewsX_AI_Scheduler();
    }
    
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('news_x_ai_generate_posts');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'news_x_ai_logs';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function set_default_options() {
        $default_options = array(
            'gemini_api_key' => '',
            'auto_generate' => false,
            'generation_frequency' => 'daily',
            'posts_per_generation' => 3,
            'categories' => array(),
            'image_source' => 'unsplash',
            'image_quality' => 'high',
            'content_length' => 'medium',
            'include_quotes' => true,
            'include_statistics' => true,
            'auto_publish' => false,
            'post_status' => 'draft',
            'featured_image' => true,
            'seo_optimization' => true,
            'last_generation' => '',
            'total_posts_generated' => 0
        );
        
        foreach ($default_options as $key => $value) {
            if (get_option('news_x_ai_' . $key) === false) {
                update_option('news_x_ai_' . $key, $value);
            }
        }
    }
}

// Initialize the plugin
function news_x_ai_init() {
    return NewsX_AI_Generator::get_instance();
}

// Start the plugin
news_x_ai_init(); 