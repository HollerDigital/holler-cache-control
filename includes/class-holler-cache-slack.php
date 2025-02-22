<?php
/**
 * Slack Integration for Holler Cache Control
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/includes
 */

namespace Holler\CacheControl\Admin;

use Holler\CacheControl\Admin\Tools;

class Slack {
    /**
     * The unique identifier of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     */
    private $version;

    /**
     * Initialize the class
     */
    public function __construct($plugin_name, $version = '1.0.0') {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Only register endpoints if Slack is enabled
        if (!$this->is_slack_disabled()) {
            add_action('rest_api_init', array($this, 'register_slack_endpoint'));
            add_action('holler_cache_task', array($this, 'process_task'));
        }
    }

    /**
     * Check if Slack integration is disabled
     */
    private function is_slack_disabled() {
        return get_option('holler_cache_slack_disabled', false);
    }

    /**
     * Initialize hooks and filters
     */
    public function init() {
        add_action('rest_api_init', array($this, 'register_slack_endpoint'));
        add_action('holler_cache_task', array($this, 'process_task'));
    }

    /**
     * Register the REST API endpoint for Slack
     */
    public function register_slack_endpoint() {
        // Don't register if disabled
        if ($this->is_slack_disabled()) {
            return;
        }

        register_rest_route('holler-cache/v1', '/slack', array(
            'methods' => \WP_REST_Server::ALLMETHODS,
            'callback' => array($this, 'handle_slack_command'),
            'permission_callback' => function() {
                return true; // We'll validate the request in the handler
            }
        ));
    }

    /**
     * Handle the Slack slash command
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response
     */
    public function handle_slack_command($request) {
        try {
            // Basic validation
            if ($request->get_param('type') === 'url_verification') {
                return new \WP_REST_Response(['challenge' => $request->get_param('challenge')], 200);
            }

            if ($request->get_param('command') !== '/holler-cache') {
                return new \WP_REST_Response([
                    'response_type' => 'ephemeral',
                    'text' => 'Invalid command'
                ], 200);
            }

            // Get target site
            $target_site = 'hollerdigital.com';
            $text = trim($request->get_param('text'));
            if (!empty($text)) {
                if ($text === 'help') {
                    return new \WP_REST_Response([
                        'response_type' => 'ephemeral',
                        'text' => "Use `/holler-cache [site]` to clear cache"
                    ], 200);
                }
                $target_site = preg_replace('#^https?://#', '', $text);
                $target_site = rtrim($target_site, '/');
            }

            // Store task info
            $task_id = uniqid('cache_');
            set_transient("holler_task_{$task_id}", [
                'site' => $target_site,
                'status' => 'scheduled',
                'time' => current_time('mysql'),
                'user' => $request->get_param('user_name'),
                'channel' => $request->get_param('channel_name'),
                'response_url' => $request->get_param('response_url') // Store Slack's response_url
            ], HOUR_IN_SECONDS);

            // Schedule immediate task
            wp_schedule_single_event(time() - 1, 'holler_cache_task', [$task_id]);
            spawn_cron(); // Run immediately

            // Respond to Slack
            return new \WP_REST_Response([
                'response_type' => 'in_channel',
                'text' => "✅ Task scheduled! Will clear cache for `{$target_site}`"
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'response_type' => 'ephemeral',
                'text' => "❌ Error: " . $e->getMessage()
            ], 200);
        }
    }

    /**
     * Process cache clear task
     */
    public function process_task($task_id) {
        try {
            // Get task info
            $task = get_transient("holler_task_{$task_id}");
            if (!$task) {
                throw new \Exception('Task not found');
            }

            $target_site = $task['site'];

            // Send "Starting" message
            wp_remote_post($task['response_url'], [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'response_type' => 'in_channel',
                    'text' => "🔄 Starting cache clear for `{$target_site}`\n" .
                             "_Task ID: {$task_id}_"
                ])
            ]);

            // Update status
            $task['status'] = 'running';
            $task['start_time'] = current_time('mysql');
            set_transient("holler_task_{$task_id}", $task, HOUR_IN_SECONDS);

            // Try each cache type separately to get detailed errors
            $errors = [];

            // 1. PHP OPcache
            $result = \Holler\CacheControl\Admin\Cache\CacheManager::purge_opcache();
            if (!$result['success']) {
                $errors[] = "PHP OPcache: " . $result['message'];
            }
            sleep(5); // Wait 5 seconds
            
            // 2. Redis Object Cache
            $result = \Holler\CacheControl\Admin\Cache\Redis::purge_cache();
            if (!$result['success']) {
                $errors[] = "Redis Object Cache: " . $result['message'];
            }
            sleep(5); // Wait 5 seconds
            
            // 3. Redis Page Cache (Nginx)
            $result = \Holler\CacheControl\Admin\Cache\Nginx::purge_cache();
            if (!$result['success']) {
                $errors[] = "Page Cache: " . $result['message'];
            }
            sleep(5); // Wait 5 seconds
            
            // 4. Cloudflare
            $result = \Holler\CacheControl\Admin\Cache\Cloudflare::purge_cache();
            if (!$result['success']) {
                $errors[] = "Cloudflare: " . $result['message'];
            }
            sleep(5); // Wait 5 seconds

            // 5. Cloudflare APO
            $result = \Holler\CacheControl\Admin\Cache\CloudflareAPO::purge_cache();
            if (!$result['success']) {
                $errors[] = "Cloudflare APO: " . $result['message'];
            }

            // Check for errors
            if (!empty($errors)) {
                throw new \Exception(implode("\n", $errors));
            }

            // Update status and notify completion
            $task['status'] = 'completed';
            $task['end_time'] = current_time('mysql');
            set_transient("holler_task_{$task_id}", $task, HOUR_IN_SECONDS);

            // Send completion message
            wp_remote_post($task['response_url'], [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'response_type' => 'in_channel',
                    'text' => "✅ Cache cleared successfully for `{$target_site}`!\n" .
                             "_Task ID: {$task_id}_\n" .
                             "Started: " . date('g:i:s A T', strtotime($task['start_time'])) . "\n" .
                             "Completed: " . date('g:i:s A T', strtotime($task['end_time']))
                ])
            ]);

        } catch (\Exception $e) {
            // Update status
            if ($task) {
                $task['status'] = 'failed';
                $task['error'] = $e->getMessage();
                $task['end_time'] = current_time('mysql');
                set_transient("holler_task_{$task_id}", $task, HOUR_IN_SECONDS);
            }

            // Send error message
            if (isset($task['response_url'])) {
                wp_remote_post($task['response_url'], [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode([
                        'response_type' => 'in_channel',
                        'text' => "❌ Failed to clear cache for `{$target_site}`\n" .
                                "_Task ID: {$task_id}_\n" .
                                "Error: " . $e->getMessage()
                    ])
                ]);
            }
        }
    }

    /**
     * Send help message
     */
    private function send_help_message() {
        return new \WP_REST_Response(array(
            'response_type' => 'ephemeral',
            'text' => "🔍 *Holler Cache Control Help*\n\n" .
                     "*Commands:*\n" .
                     "• `/holler-cache list` - Show all available sites\n" .
                     "• `/holler-cache status` - Show recent cache clears\n" .
                     "• `/holler-cache [site]` - Clear all caches for a site\n" .
                     "• `/holler-cache [site] [type]` - Clear specific cache type\n\n" .
                     "*Cache Types:*\n" .
                     "• `all` - Clear all caches (default)\n" .
                     "• `nginx` - Clear Nginx cache only\n" .
                     "• `redis` - Clear Redis cache only\n" .
                     "• `cloudflare` - Clear Cloudflare cache only\n" .
                     "• `apo` - Clear Cloudflare APO cache only\n\n" .
                     "*Examples:*\n" .
                     "• `/holler-cache` - Clear all caches on hollerdigital.com\n" .
                     "• `/holler-cache dev.hollerdigital.com nginx` - Clear Nginx cache on dev site\n" .
                     "• `/holler-cache hollerdigital.com apo` - Clear Cloudflare APO cache"
        ), 200);
    }

    /**
     * Send list of available sites
     */
    private function send_sites_list($allowed_sites) {
        if (empty($allowed_sites)) {
            return new \WP_REST_Response(array(
                'response_type' => 'ephemeral',
                'text' => "❌ No sites are configured for cache clearing."
            ), 200);
        }

        return new \WP_REST_Response(array(
            'response_type' => 'ephemeral',
            'text' => sprintf("🌐 *Available Sites:*\n\n%s", 
                implode("\n", array_map(function($site) { 
                    return "• `$site`"; 
                }, $allowed_sites))
            )
        ), 200);
    }

    /**
     * Send status message with recent cache clears
     */
    private function send_status_message() {
        $history = get_transient('holler_cache_clear_history');
        $status_text = '';

        // Check for any running background jobs
        $transients = array_filter(
            array_map(function($key) {
                if (strpos($key, 'holler_cache_clear_status_') === 0) {
                    return str_replace('holler_cache_clear_status_', '', $key);
                }
                return null;
            }, get_option('_transient_keys', array())),
            function($key) { return !empty($key); }
        );

        if (!empty($transients)) {
            $status_text .= "🔄 *Active Cache Clear Jobs:*\n\n";
            foreach ($transients as $site) {
                $job_status = get_transient('holler_cache_clear_status_' . $site);
                if (!empty($job_status)) {
                    switch ($job_status['status']) {
                        case 'running':
                            $status_text .= "• `{$site}`: Starting... ⏳\n";
                            break;
                        case 'processing':
                            $status_text .= "• `{$site}`: In Progress... 🔄\n";
                            break;
                        case 'completed':
                            $status_text .= "• `{$site}`: Completed ✅ at " . wp_date('g:i A T', strtotime($job_status['completed_time'])) . "\n";
                            break;
                        case 'failed':
                            $status_text .= "• `{$site}`: Failed ❌ - " . $job_status['error'] . "\n";
                            break;
                    }
                }
            }
            $status_text .= "\n";
        }
        
        if (empty($history)) {
            if (empty($status_text)) {
                return new \WP_REST_Response(array(
                    'response_type' => 'ephemeral',
                    'text' => "📝 No recent cache clears found. Try clearing a cache first with:\n" .
                             "• `/holler-cache hollerdigital.com`\n" .
                             "• `/holler-cache help` - for more options"
                ), 200);
            }
            return new \WP_REST_Response(array(
                'response_type' => 'ephemeral',
                'text' => $status_text
            ), 200);
        }

        $status_text .= "📝 *Recent Cache Clears:*\n\n";
        foreach ($history as $entry) {
            // Get emoji for source
            $source_emoji = $this->get_source_emoji($entry['source']);
            
            $status_text .= sprintf(
                "• %s: %s Cleared %s cache on `%s` via %s\n",
                wp_date('M j, g:i A T', strtotime($entry['time'])),
                $source_emoji,
                $entry['type'],
                $entry['site'],
                ucfirst($entry['source'])
            );
        }

        return new \WP_REST_Response(array(
            'response_type' => 'ephemeral',
            'text' => $status_text
        ), 200);
    }

    /**
     * Get emoji for cache clear source
     */
    private function get_source_emoji($source) {
        switch ($source) {
            case 'slack':
                return '💬'; // Chat bubble for Slack
            case 'cli':
                return '💻'; // Computer for CLI
            case 'ajax':
                return '🖱️'; // Mouse for AJAX/UI clicks
            case 'slack_scheduled':
                return '🕒'; // Clock for scheduled Slack
            default:
                return '🔄'; // Generic refresh
        }
    }

    /**
     * Register direct cache clear request endpoint
     */
    public function register_clear_endpoint() {
        // Don't register if disabled
        if ($this->is_slack_disabled()) {
            return;
        }

        register_rest_route('holler-cache/v1', '/clear', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_clear_request'),
            'permission_callback' => function() {
                return current_user_can('manage_options') || 
                       wp_verify_nonce($_POST['token'], 'holler_cache_control_clear');
            }
        ));
    }

    /**
     * Handle the cache clear request
     */
    public function handle_clear_request($request) {
        try {
            // Get site from text or default to hollerdigital.com
            $target_site = 'hollerdigital.com';
            if (!empty($request->get_param('text'))) {
                $text = trim($request->get_param('text'));
                $target_site = preg_replace('#^https?://#', '', $text);
                $target_site = rtrim($target_site, '/');
            }

            // Validate site is allowed
            $allowed_sites = get_option('slack_allowed_sites', array());
            if (!empty($allowed_sites) && !in_array($target_site, $allowed_sites)) {
                return new \WP_REST_Response(array(
                    'response_type' => 'ephemeral',
                    'text' => sprintf(__('Site %s is not in the allowed list.', 'holler-cache-control'), $target_site)
                ), 200);
            }

            // Forward the request to the target site
            $target_url = "https://{$target_site}/wp-json/holler-cache/v1/clear";
            
            // Set a longer timeout and add error handling
            $response = wp_remote_post($target_url, array(
                'timeout' => 60, // Increase timeout to 60 seconds
                'body' => array(
                    'source' => 'slack',
                    'token' => wp_create_nonce('holler_cache_control_clear'),
                    'cache_type' => 'all'
                ),
                'sslverify' => false, // Skip SSL verification if needed
                'blocking' => true,    // Make sure we wait for the response
                'headers' => array(    // Add headers to prevent caching
                    'Cache-Control' => 'no-cache',
                    'X-Holler-Cache-Control' => 'slack-command',
                    'X-Holler-Cache-Timeout' => '129'
                )
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();
                
                // Handle timeout specifically
                if ($error_code === 'http_request_failed' && (
                    strpos($error_message, 'Operation timed out') !== false ||
                    strpos($error_message, 'Connection timed out') !== false ||
                    strpos($error_message, 'timeout') !== false
                )) {
                    // Start background process for cache clearing
                    $this->schedule_background_cache_clear($target_site);
                    
                    return new \WP_REST_Response(array(
                        'response_type' => 'in_channel',
                        'text' => "🕒 Cache clear operation started for {$target_site}. This may take a few minutes. Use `/holler-cache status` to check progress."
                    ), 200);
                }
                
                throw new \Exception($error_message);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code >= 400) {
                throw new \Exception('Server returned error code: ' . $response_code);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            if (!empty($data['success'])) {
                // Track the successful cache clear
                $this->track_cache_clear($target_site, 'all', 'slack');
                
                return new \WP_REST_Response(array(
                    'response_type' => 'in_channel',
                    'text' => "🧹 Cache cleared successfully on {$target_site}!"
                ), 200);
            } else {
                throw new \Exception($data['message'] ?? 'Cache clear failed without specific error');
            }
        } catch (\Exception $e) {
            
            return new \WP_REST_Response(array(
                'response_type' => 'ephemeral',
                'text' => "❌ Error: " . $e->getMessage()
            ), 200);
        }
    }

    /**
     * Schedule a background cache clear operation
     */
    private function schedule_background_cache_clear($target_site) {
        // Store the status first
        set_transient('holler_cache_clear_status_' . $target_site, array(
            'status' => 'running',
            'start_time' => current_time('mysql'),
            'target_site' => $target_site
        ), 3600); // Store for 1 hour

        // Schedule the event to run immediately
        if (!wp_next_scheduled('holler_cache_clear_background', array($target_site))) {
            wp_schedule_single_event(time() - 1, 'holler_cache_clear_background', array($target_site));
            spawn_cron(); // Force WordPress to run scheduled tasks immediately
        }
    }

    /**
     * Handle background cache clear
     */
    public function handle_background_cache_clear($target_site) {
        try {
            // Get the status
            $status = get_transient('holler_cache_clear_status_' . $target_site);
            if (empty($status) || $status['status'] !== 'running') {
                return;
            }

            // Update status to processing
            $status['status'] = 'processing';
            set_transient('holler_cache_clear_status_' . $target_site, $status, 3600);

            // Send initial notification to Slack
            $notification = new SlackNotification($this->plugin_name, $this->version);
            $notification->send_notification(
                "🔄 Starting cache clear for `{$target_site}`\n" .
                "_Started at: " . wp_date('g:i:s A T', strtotime($status['start_time'])) . "_"
            );

            // Forward the request to the target site without waiting for response
            $target_url = "https://{$target_site}/wp-json/holler-cache/v1/clear";
            wp_remote_post($target_url, array(
                'timeout' => 0.01, // Minimal timeout - we don't care about the response
                'blocking' => false, // Don't wait for response
                'body' => array(
                    'source' => 'slack_scheduled',
                    'token' => wp_create_nonce('holler_cache_control_clear'),
                    'cache_type' => 'all'
                ),
                'sslverify' => false,
                'headers' => array(
                    'Cache-Control' => 'no-cache',
                    'X-Holler-Cache-Control' => 'slack-background',
                    'X-Holler-Cache-Timeout' => '0'
                )
            ));

            // Update status to triggered
            $status['status'] = 'triggered';
            $status['triggered_time'] = current_time('mysql');
            set_transient('holler_cache_clear_status_' . $target_site, $status, 3600);

            // Send notification that request was triggered
            $notification->send_notification(
                "📤 Cache clear request sent to `{$target_site}`\n" .
                "_Started at: " . wp_date('g:i:s A T', strtotime($status['start_time'])) . "_\n" .
                "_Request sent at: " . wp_date('g:i:s A T', strtotime($status['triggered_time'])) . "_\n" .
                "_Note: Cache clear is running in the background, no completion status will be available._"
            );

        } catch (\Exception $e) {
            // Update status to failed
            if (!empty($status)) {
                $status['status'] = 'failed';
                $status['error'] = $e->getMessage();
                $status['failed_time'] = current_time('mysql');
                set_transient('holler_cache_clear_status_' . $target_site, $status, 3600);

                // Send failure notification to Slack
                if (isset($notification)) {
                    $notification->send_notification(
                        "❌ Failed to send cache clear request to `{$target_site}`\n" .
                        "_Error: " . $e->getMessage() . "_\n" .
                        "_Started at: " . wp_date('g:i:s A T', strtotime($status['start_time'])) . "_\n" .
                        "_Failed at: " . wp_date('g:i:s A T', strtotime($status['failed_time'])) . "_"
                    );
                }
            }
            error_log('Holler Cache Control - Background cache clear error: ' . $e->getMessage());
        }
    }
}

namespace Holler\CacheControl;

/**
 * Slack Notification Handler
 */
class SlackNotification {
    /**
     * The unique identifier of this plugin.
     */
    private $plugin_name;

    /**
     * The current version of the plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Send Slack notification
     */
    public function send_notification($message, $channel = null) {
        try {
            $webhook_url = get_option('holler_cache_control_slack_webhook');
            if (empty($webhook_url)) {
                return false;
            }

            $data = array(
                'text' => $message
            );

            if (!empty($channel)) {
                $data['channel'] = $channel;
            }

            $response = wp_remote_post($webhook_url, array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode($data)
            ));

            if (is_wp_error($response)) {
                error_log('Holler Cache Control - Slack notification error: ' . $response->get_error_message());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Slack notification error: ' . $e->getMessage());
            return false;
        }
    }
}
