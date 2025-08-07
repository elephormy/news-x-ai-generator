<?php
/**
 * Uninstall script for News X AI Generator
 * 
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data from the database.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove all plugin options
$options_to_remove = array(
    'news_x_ai_gemini_api_key',
    'news_x_ai_auto_generate',
    'news_x_ai_generation_frequency',
    'news_x_ai_posts_per_generation',
    'news_x_ai_categories',
    'news_x_ai_image_source',
    'news_x_ai_image_quality',
    'news_x_ai_content_length',
    'news_x_ai_include_quotes',
    'news_x_ai_include_statistics',
    'news_x_ai_auto_publish',
    'news_x_ai_post_status',
    'news_x_ai_featured_image',
    'news_x_ai_seo_optimization',
    'news_x_ai_last_generation',
    'news_x_ai_total_posts_generated'
);

foreach ($options_to_remove as $option) {
    delete_option($option);
}

// Remove database table
global $wpdb;
$table_name = $wpdb->prefix . 'news_x_ai_logs';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear scheduled events
wp_clear_scheduled_hook('news_x_ai_generate_posts');

// Remove any transients
delete_transient('news_x_ai_api_test');
delete_transient('news_x_ai_generation_status');

// Clean up any uploaded images (optional - uncomment if you want to remove generated images)
/*
$args = array(
    'post_type' => 'attachment',
    'meta_query' => array(
        array(
            'key' => '_news_x_ai_generated',
            'value' => '1',
            'compare' => '='
        )
    ),
    'posts_per_page' => -1
);

$attachments = get_posts($args);

foreach ($attachments as $attachment) {
    wp_delete_attachment($attachment->ID, true);
}
*/

// Remove any custom post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_news_x_ai_%'");

// Clear any cached data
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Log the uninstall
error_log('News X AI Generator plugin uninstalled - all data removed'); 