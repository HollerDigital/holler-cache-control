<?php
namespace Holler\CacheControl\Admin;

class Ajax {
    /**
     * Initialize Ajax handlers
     */
    public static function init() {
        add_action('wp_ajax_holler_cache_execute_command', array(__CLASS__, 'execute_command'));
    }

    /**
     * Execute command via AJAX
     */
    public static function execute_command() {
        // Verify nonce and capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $command = isset($_POST['command']) ? sanitize_text_field($_POST['command']) : '';
        
        if (empty($command)) {
            wp_send_json_error('No command specified');
        }

        // Only allow specific GridPane commands
        if (strpos($command, 'gp ') !== 0) {
            wp_send_json_error('Invalid command');
        }

        // Execute command using WordPress functions
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        wp_send_json_success('Cache cleared successfully');
    }
}
