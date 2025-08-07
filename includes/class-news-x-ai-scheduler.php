<?php
/**
 * Scheduler class for News X AI Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsX_AI_Scheduler {
    
    public function __construct() {
        add_action('init', array($this, 'init_scheduler'));
        add_action('news_x_ai_generate_posts', array($this, 'auto_generate_posts'));
        add_action('admin_init', array($this, 'maybe_schedule_generation'));
    }
    
    /**
     * Initialize scheduler
     */
    public function init_scheduler() {
        // Register cron interval
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['hourly'] = array(
            'interval' => 3600,
            'display' => __('Every Hour', 'news-x-ai-generator')
        );
        
        $schedules['daily'] = array(
            'interval' => 86400,
            'display' => __('Daily', 'news-x-ai-generator')
        );
        
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display' => __('Weekly', 'news-x-ai-generator')
        );
        
        return $schedules;
    }
    
    /**
     * Maybe schedule generation based on settings
     */
    public function maybe_schedule_generation() {
        $auto_generate = get_option('news_x_ai_auto_generate', false);
        $frequency = get_option('news_x_ai_generation_frequency', 'daily');
        
        if ($auto_generate) {
            $this->schedule_generation($frequency);
        } else {
            $this->unschedule_generation();
        }
    }
    
    /**
     * Schedule automatic generation
     */
    public function schedule_generation($frequency = 'daily') {
        $hook = 'news_x_ai_generate_posts';
        
        // Clear existing schedule
        wp_clear_scheduled_hook($hook);
        
        // Schedule new event
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $frequency, $hook);
        }
    }
    
    /**
     * Unschedule automatic generation
     */
    public function unschedule_generation() {
        wp_clear_scheduled_hook('news_x_ai_generate_posts');
    }
    
    /**
     * Auto generate posts (called by cron)
     */
    public function auto_generate_posts() {
        // Check if auto generation is still enabled
        if (!get_option('news_x_ai_auto_generate', false)) {
            return;
        }
        
        // Check if API key is configured
        $api_key = get_option('news_x_ai_gemini_api_key');
        if (empty($api_key)) {
            $this->log_error('Auto generation failed: API key not configured');
            return;
        }
        
        // Get generation settings
        $posts_count = get_option('news_x_ai_posts_per_generation', 3);
        $categories = get_option('news_x_ai_categories', array());
        
        // Generate posts
        $generator = new NewsX_AI_Generator_Core();
        $result = $generator->generate_posts($posts_count, $categories);
        
        if ($result['success']) {
            $this->log_success('Auto generation completed: ' . $result['total_generated'] . ' posts generated');
        } else {
            $this->log_error('Auto generation failed: ' . implode(', ', $result['errors']));
        }
    }
    
    /**
     * Get next scheduled generation time
     */
    public function get_next_generation_time() {
        $next_scheduled = wp_next_scheduled('news_x_ai_generate_posts');
        
        if ($next_scheduled) {
            return date('Y-m-d H:i:s', $next_scheduled);
        }
        
        return false;
    }
    
    /**
     * Get generation schedule info
     */
    public function get_schedule_info() {
        $auto_generate = get_option('news_x_ai_auto_generate', false);
        $frequency = get_option('news_x_ai_generation_frequency', 'daily');
        $next_generation = $this->get_next_generation_time();
        
        return array(
            'enabled' => $auto_generate,
            'frequency' => $frequency,
            'next_generation' => $next_generation,
            'posts_per_generation' => get_option('news_x_ai_posts_per_generation', 3)
        );
    }
    
    /**
     * Test the scheduler
     */
    public function test_scheduler() {
        $result = array(
            'success' => false,
            'message' => '',
            'next_scheduled' => null
        );
        
        // Check if cron is working
        if (!wp_next_scheduled('news_x_ai_generate_posts')) {
            $result['message'] = __('No scheduled generation found', 'news-x-ai-generator');
            return $result;
        }
        
        $next_scheduled = wp_next_scheduled('news_x_ai_generate_posts');
        
        if ($next_scheduled && $next_scheduled > time()) {
            $result['success'] = true;
            $result['message'] = __('Scheduler is working correctly', 'news-x-ai-generator');
            $result['next_scheduled'] = date('Y-m-d H:i:s', $next_scheduled);
        } else {
            $result['message'] = __('Scheduler issue detected', 'news-x-ai-generator');
        }
        
        return $result;
    }
    
    /**
     * Log success message
     */
    private function log_success($message) {
        error_log('[News X AI Generator] SUCCESS: ' . $message);
    }
    
    /**
     * Log error message
     */
    private function log_error($message) {
        error_log('[News X AI Generator] ERROR: ' . $message);
    }
    
    /**
     * Get cron status
     */
    public function get_cron_status() {
        $status = array(
            'wp_cron_enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'next_scheduled' => $this->get_next_generation_time(),
            'auto_generate_enabled' => get_option('news_x_ai_auto_generate', false),
            'frequency' => get_option('news_x_ai_generation_frequency', 'daily')
        );
        
        return $status;
    }
    
    /**
     * Manually trigger generation (for testing)
     */
    public function manual_trigger() {
        if (!current_user_can('manage_options')) {
            return array(
                'success' => false,
                'message' => __('Unauthorized', 'news-x-ai-generator')
            );
        }
        
        $this->auto_generate_posts();
        
        return array(
            'success' => true,
            'message' => __('Manual generation triggered', 'news-x-ai-generator')
        );
    }
    
    /**
     * Update schedule when settings change
     */
    public function update_schedule($auto_generate, $frequency) {
        if ($auto_generate) {
            $this->schedule_generation($frequency);
        } else {
            $this->unschedule_generation();
        }
    }
    
    /**
     * Get frequency options
     */
    public function get_frequency_options() {
        return array(
            'hourly' => __('Every Hour', 'news-x-ai-generator'),
            'daily' => __('Daily', 'news-x-ai-generator'),
            'weekly' => __('Weekly', 'news-x-ai-generator')
        );
    }
    
    /**
     * Get frequency interval in seconds
     */
    public function get_frequency_interval($frequency) {
        $intervals = array(
            'hourly' => 3600,
            'daily' => 86400,
            'weekly' => 604800
        );
        
        return isset($intervals[$frequency]) ? $intervals[$frequency] : 86400;
    }
    
    /**
     * Check if generation is due
     */
    public function is_generation_due() {
        $next_scheduled = wp_next_scheduled('news_x_ai_generate_posts');
        
        if (!$next_scheduled) {
            return false;
        }
        
        return $next_scheduled <= time();
    }
    
    /**
     * Get generation statistics
     */
    public function get_generation_stats() {
        return array(
            'total_generated' => get_option('news_x_ai_total_posts_generated', 0),
            'last_generation' => get_option('news_x_ai_last_generation', ''),
            'next_generation' => $this->get_next_generation_time(),
            'auto_generate_enabled' => get_option('news_x_ai_auto_generate', false)
        );
    }
} 