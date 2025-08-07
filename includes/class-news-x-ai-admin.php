<?php
/**
 * Admin class for News X AI Generator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class NewsX_AI_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_news_x_ai_generate_posts', array($this, 'ajax_generate_posts'));
        add_action('wp_ajax_news_x_ai_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_news_x_ai_debug', array($this, 'ajax_debug'));
        add_action('wp_ajax_news_x_ai_list_models', array($this, 'ajax_list_models'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        add_action('wp_head', array($this, 'enqueue_frontend_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'News X AI Generator',
            'News X AI',
            'manage_options',
            'news-x-ai-generator',
            array($this, 'admin_page'),
            'dashicons-admin-generic',
            30
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('news_x_ai_options', 'news_x_ai_gemini_api_key');
        register_setting('news_x_ai_options', 'news_x_ai_posts_per_generation', array('default' => 3));
        register_setting('news_x_ai_options', 'news_x_ai_content_length', array('default' => 'medium'));
        register_setting('news_x_ai_options', 'news_x_ai_image_source', array('default' => 'gemini'));
        register_setting('news_x_ai_options', 'news_x_ai_post_status', array('default' => 'publish'));
        register_setting('news_x_ai_options', 'news_x_ai_auto_schedule', array('default' => false));
        register_setting('news_x_ai_options', 'news_x_ai_schedule_interval', array('default' => 'daily'));
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>News X AI Generator</h1>
            
            <div class="news-x-ai-admin-container">
                <!-- Settings Section -->
                <div class="news-x-ai-section">
                    <h2>Settings</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('news_x_ai_options'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Gemini API Key</th>
                                <td>
                                    <input type="password" name="news_x_ai_gemini_api_key" value="<?php echo esc_attr(get_option('news_x_ai_gemini_api_key')); ?>" class="regular-text" />
                                    <p class="description">Enter your Google Gemini API key. <a href="https://aistudio.google.com/app/apikey" target="_blank">Get one here</a></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Posts per Generation</th>
                                <td>
                                    <select name="news_x_ai_posts_per_generation">
                                        <?php
                                        $current = get_option('news_x_ai_posts_per_generation', 3);
                                        for ($i = 1; $i <= 10; $i++) {
                                            echo '<option value="' . $i . '"' . selected($current, $i, false) . '>' . $i . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Content Length</th>
                                <td>
                                    <select name="news_x_ai_content_length">
                                        <option value="short" <?php selected(get_option('news_x_ai_content_length', 'medium'), 'short'); ?>>Short (400-600 words)</option>
                                        <option value="medium" <?php selected(get_option('news_x_ai_content_length', 'medium'), 'medium'); ?>>Medium (800-1200 words)</option>
                                        <option value="long" <?php selected(get_option('news_x_ai_content_length', 'medium'), 'long'); ?>>Long (1200-1800 words)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Image Source</th>
                                <td>
                                    <select name="news_x_ai_image_source">
                                        <option value="gemini" <?php selected(get_option('news_x_ai_image_source', 'gemini'), 'gemini'); ?>>Free AI Image Generators (Unlimited)</option>
                                        <option value="unsplash" <?php selected(get_option('news_x_ai_image_source', 'gemini'), 'unsplash'); ?>>Unsplash</option>
                                        <option value="pexels" <?php selected(get_option('news_x_ai_image_source', 'gemini'), 'pexels'); ?>>Pexels</option>
                                    </select>
                                    <p class="description">Free AI Image Generators use multiple AI services for high-quality images</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Post Status</th>
                                <td>
                                    <select name="news_x_ai_post_status">
                                        <option value="publish" <?php selected(get_option('news_x_ai_post_status', 'publish'), 'publish'); ?>>Publish Immediately</option>
                                        <option value="draft" <?php selected(get_option('news_x_ai_post_status', 'publish'), 'draft'); ?>>Save as Draft</option>
                                    </select>
                                    <p class="description">Choose whether to publish posts immediately or save them as drafts for review</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Auto Schedule</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="news_x_ai_auto_schedule" value="1" <?php checked(get_option('news_x_ai_auto_schedule', false), true); ?> />
                                        Enable automatic post generation
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Schedule Interval</th>
                                <td>
                                    <select name="news_x_ai_schedule_interval">
                                        <option value="hourly" <?php selected(get_option('news_x_ai_schedule_interval', 'daily'), 'hourly'); ?>>Hourly</option>
                                        <option value="twicedaily" <?php selected(get_option('news_x_ai_schedule_interval', 'daily'), 'twicedaily'); ?>>Twice Daily</option>
                                        <option value="daily" <?php selected(get_option('news_x_ai_schedule_interval', 'daily'), 'daily'); ?>>Daily</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button('Save Settings'); ?>
                    </form>
                </div>
                
                <!-- Generation Section -->
                <div class="news-x-ai-section">
                    <h2>Generate Posts</h2>
                    <div class="news-x-ai-controls">
                        <label for="generate_count">Number of posts to generate:</label>
                        <select id="generate_count" name="generate_count">
                            <?php
                            $current_count = get_option('news_x_ai_posts_per_generation', 3);
                            for ($i = 1; $i <= 10; $i++) {
                                echo '<option value="' . $i . '"' . selected($current_count, $i, false) . '>' . $i . '</option>';
                            }
                            ?>
                        </select>
                        <button type="button" id="generate-posts-btn" class="button button-primary">Generate Posts</button>
                        <button type="button" id="test-api-btn" class="button button-secondary">Test API</button>
                        <button type="button" id="debug-btn" class="button button-secondary">Debug</button>
                        <button type="button" id="list-models-btn" class="button button-secondary">List Models</button>
                    </div>
                    
                    <div id="generation-progress" class="progress-container">
                        <div class="progress-fill"></div>
                    </div>
                    <div id="progress-text" style="text-align: center; margin: 10px 0; font-weight: bold;"></div>
                    
                    <div id="generation-results" class="news-x-ai-results"></div>
                </div>
                
                <!-- Debug Section -->
                <div class="news-x-ai-section">
                    <h2>Debug Information</h2>
                    <div id="debug-info" class="news-x-ai-debug">
                        <p><strong>Plugin Version:</strong> <?php echo NEWS_X_AI_VERSION; ?></p>
                        <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
                        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                        <p><strong>API Key Status:</strong> 
                            <?php 
                            $api_key = get_option('news_x_ai_gemini_api_key');
                            echo $api_key ? '✓ Configured (' . strlen($api_key) . ' characters)' : '✗ Not configured';
                            ?>
                        </p>
                    </div>
                    <div id="debug-output"></div>
                </div>
            </div>
        </div>
        

        <?php
    }
    
    /**
     * AJAX handler for generating posts
     */
    public function ajax_generate_posts() {
        try {
            check_ajax_referer('news_x_ai_generate_posts', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Insufficient permissions'));
                return;
            }
            
            $generator = new NewsX_AI_Generator_Core();
            $post_status = get_option('news_x_ai_post_status', 'publish');
            
            // Get count from request or use default setting
            $count = isset($_POST['count']) ? intval($_POST['count']) : get_option('news_x_ai_posts_per_generation', 3);
            
            // Validate count
            if ($count < 1 || $count > 10) {
                wp_send_json_error(array('message' => 'Invalid count. Must be between 1 and 10.'));
                return;
            }
            
            $result = $generator->generate_posts($count, array(), $post_status);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error(array('message' => isset($result['message']) ? $result['message'] : 'Failed to generate posts'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error generating posts: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for testing API
     */
    public function ajax_test_api() {
        try {
            check_ajax_referer('news_x_ai_test_api', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Insufficient permissions'));
                return;
            }
            
            $generator = new NewsX_AI_Generator_Core();
            $result = $generator->test_api_connection();
            
            if ($result['success']) {
                wp_send_json_success(array('message' => $result['message']));
            } else {
                wp_send_json_error(array('message' => isset($result['message']) ? $result['message'] : 'API test failed'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error testing API: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for debug
     */
    public function ajax_debug() {
        try {
            check_ajax_referer('news_x_ai_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Insufficient permissions'));
                return;
            }
            
            $debug_info = "=== News X AI Generator Debug Info ===\n\n";
            
            // Plugin info
            $debug_info .= "Plugin Version: " . NEWS_X_AI_VERSION . "\n";
            $debug_info .= "WordPress Version: " . get_bloginfo('version') . "\n";
            $debug_info .= "PHP Version: " . PHP_VERSION . "\n\n";
            
            // API key status
            $api_key = get_option('news_x_ai_gemini_api_key');
            $debug_info .= "API Key Status: " . ($api_key ? 'Configured (' . strlen($api_key) . ' characters)' : 'Not configured') . "\n\n";
            
            // Settings
            $debug_info .= "Settings:\n";
            $debug_info .= "- Posts per generation: " . get_option('news_x_ai_posts_per_generation', 3) . "\n";
            $debug_info .= "- Content length: " . get_option('news_x_ai_content_length', 'medium') . "\n";
            $debug_info .= "- Image source: " . get_option('news_x_ai_image_source', 'gemini') . "\n";
            $debug_info .= "- Post status: " . get_option('news_x_ai_post_status', 'publish') . "\n";
            $debug_info .= "- Auto schedule: " . (get_option('news_x_ai_auto_schedule', false) ? 'Yes' : 'No') . "\n\n";
            
            // Test API connection
            if ($api_key) {
                $generator = new NewsX_AI_Generator_Core();
                $api_result = $generator->test_api_connection();
                $debug_info .= "API Connection Test: " . ($api_result['success'] ? 'Success' : 'Failed') . "\n";
                if (!$api_result['success']) {
                    $debug_info .= "Error: " . (isset($api_result['message']) ? $api_result['message'] : 'Unknown error') . "\n";
                }
            }
            
            wp_send_json_success($debug_info);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error during debug: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for listing models
     */
    public function ajax_list_models() {
        try {
            check_ajax_referer('news_x_ai_list_models', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Insufficient permissions'));
                return;
            }
            
            $generator = new NewsX_AI_Generator_Core();
            $result = $generator->list_available_models();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error(array('message' => isset($result['message']) ? $result['message'] : 'Failed to list models'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error listing models: ' . $e->getMessage()));
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin page
        if ($hook != 'toplevel_page_news-x-ai-generator') {
            return;
        }
        
        // Enqueue admin JavaScript
        wp_enqueue_script(
            'news-x-ai-admin',
            NEWS_X_AI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            NEWS_X_AI_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('news-x-ai-admin', 'news_x_ai_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('news_x_ai_generate_posts'),
            'strings' => array(
                'generating' => __('Generating posts...', 'news-x-ai-generator'),
                'success' => __('Success!', 'news-x-ai-generator'),
                'error' => __('Error', 'news-x-ai-generator'),
                'testing' => __('Testing API...', 'news-x-ai-generator'),
                'api_success' => __('API connection successful!', 'news-x-ai-generator'),
                'api_error' => __('API connection failed', 'news-x-ai-generator')
            )
        ));
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'news-x-ai-admin',
            NEWS_X_AI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            NEWS_X_AI_VERSION
        );
    }
    
    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        // Enqueue with high priority to override theme styles
        wp_enqueue_style(
            'news-x-ai-newspaper-style',
            NEWS_X_AI_PLUGIN_URL . 'assets/css/newspaper-style.css',
            array(),
            NEWS_X_AI_VERSION,
            'all'
        );
        
        // Enqueue frontend JavaScript for enhanced heading detection
        wp_enqueue_script(
            'news-x-ai-frontend',
            NEWS_X_AI_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            NEWS_X_AI_VERSION,
            true
        );
        
        // Add inline styles as backup for critical formatting
        $inline_css = "
        .newspaper-article {
            font-family: 'Times New Roman', Georgia, serif !important;
            line-height: 1.6 !important;
            color: #333 !important;
            max-width: 800px !important;
            margin: 0 auto !important;
            padding: 20px !important;
            background: #fff !important;
            font-size: 16px !important;
        }
        .newspaper-article p {
            display: block !important;
            font-size: 1.05em !important;
            line-height: 1.65 !important;
            margin-bottom: 18px !important;
            margin-top: 0 !important;
            padding: 0 !important;
            text-align: justify !important;
            text-indent: 0 !important;
            font-family: 'Times New Roman', Georgia, serif !important;
            color: #333 !important;
            clear: both !important;
        }
        .newspaper-article .section-heading {
            display: block !important;
            font-family: 'Arial', sans-serif !important;
            font-size: 1.3em !important;
            font-weight: 700 !important;
            color: #2c3e50 !important;
            margin: 30px 0 15px 0 !important;
            padding-bottom: 8px !important;
            border-bottom: 2px solid #3498db !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            clear: both !important;
            line-height: 1.2 !important;
        }
        .newspaper-article h3 { /* Added for direct h3 tags */
            display: block !important;
            font-family: 'Arial', sans-serif !important;
            font-size: 1.3em !important;
            font-weight: 700 !important;
            color: #2c3e50 !important;
            margin: 30px 0 15px 0 !important;
            padding-bottom: 8px !important;
            border-bottom: 2px solid #3498db !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            clear: both !important;
            line-height: 1.2 !important;
        }
        .newspaper-article .heading-style { /* Added for dynamically detected headings */
            display: block !important;
            font-family: 'Arial', sans-serif !important;
            font-size: 1.3em !important;
            font-weight: 700 !important;
            color: #2c3e50 !important;
            margin: 30px 0 15px 0 !important;
            padding-bottom: 8px !important;
            border-bottom: 2px solid #3498db !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            clear: both !important;
            line-height: 1.2 !important;
        }
        .newspaper-article ul, .newspaper-article ol {
            margin: 20px 0 !important;
            padding-left: 30px !important;
            list-style-type: disc !important;
        }
        .newspaper-article li {
            margin-bottom: 12px !important;
            line-height: 1.6 !important;
            font-size: 1.05em !important;
            font-family: 'Times New Roman', Georgia, serif !important;
            color: #333 !important;
        }
        ";
        wp_add_inline_style('news-x-ai-newspaper-style', $inline_css);
    }
} 