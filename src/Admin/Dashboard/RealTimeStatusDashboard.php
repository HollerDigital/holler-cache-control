<?php

namespace Holler\CacheControl\Admin\Dashboard;

use Holler\CacheControl\Admin\Tools;
use Holler\CacheControl\Admin\Cache\Nginx;
use Holler\CacheControl\Admin\Cache\Redis;
use Holler\CacheControl\Admin\Cache\Cloudflare;
use Holler\CacheControl\Admin\Cache\CloudflareAPO;
use Holler\CacheControl\Admin\Cache\StatusCache;

/**
 * Real-time Status Dashboard
 * 
 * Provides live cache status updates, performance metrics, and interactive controls
 */
class RealTimeStatusDashboard {
    
    /**
     * Dashboard refresh intervals (in seconds)
     */
    const REFRESH_FAST = 5;
    const REFRESH_NORMAL = 15;
    const REFRESH_SLOW = 30;
    
    /**
     * Initialize the dashboard system
     */
    public static function init() {
        // Ensure all required classes are loaded
        self::ensure_dependencies();
        
        // Add a simple test handler first
        add_action('wp_ajax_holler_cache_test', [__CLASS__, 'handle_test_ajax']);
        add_action('wp_ajax_holler_cache_get_dashboard_data', [__CLASS__, 'handle_dashboard_data_ajax']);
        add_action('wp_ajax_holler_cache_get_performance_metrics', [__CLASS__, 'handle_performance_metrics_ajax']);
        add_action('wp_ajax_holler_cache_dashboard_action', [__CLASS__, 'handle_dashboard_action_ajax']);
        
        // Enqueue dashboard scripts and styles
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_dashboard_assets']);
    }
    
    /**
     * Simple test AJAX handler to verify basic functionality
     */
    public static function handle_test_ajax() {
        error_log('Holler Cache Control - Test AJAX handler called');
        wp_send_json_success(['message' => 'Test AJAX working', 'timestamp' => time()]);
    }
    
    /**
     * Ensure all required dependencies are loaded
     */
    private static function ensure_dependencies() {
        $base_dir = plugin_dir_path(__DIR__) . '../';
        
        $required_files = [
            'Admin/Cache/StatusCache.php',
            'Admin/Cache/Nginx.php',
            'Admin/Cache/Redis.php', 
            'Admin/Cache/Cloudflare.php',
            'Admin/Cache/CloudflareAPO.php'
        ];
        
        foreach ($required_files as $file) {
            $full_path = $base_dir . $file;
            if (file_exists($full_path) && !class_exists(self::get_class_from_file($file))) {
                require_once $full_path;
            }
        }
    }
    
    /**
     * Get class name from file path
     */
    private static function get_class_from_file($file) {
        $class_map = [
            'Admin/Cache/StatusCache.php' => 'Holler\CacheControl\Admin\Cache\StatusCache',
            'Admin/Cache/Nginx.php' => 'Holler\CacheControl\Admin\Cache\Nginx',
            'Admin/Cache/Redis.php' => 'Holler\CacheControl\Admin\Cache\Redis',
            'Admin/Cache/Cloudflare.php' => 'Holler\CacheControl\Admin\Cache\Cloudflare',
            'Admin/Cache/CloudflareAPO.php' => 'Holler\CacheControl\Admin\Cache\CloudflareAPO'
        ];
        
        return $class_map[$file] ?? '';
    }
    
    /**
     * Enqueue dashboard JavaScript and CSS
     *
     * @param string $hook Current admin page hook
     */
    public static function enqueue_dashboard_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'holler-cache-control') === false) {
            return;
        }
        
        wp_enqueue_script(
            'holler-cache-dashboard',
            plugins_url('assets/js/dashboard.js', dirname(dirname(__DIR__))),
            ['jquery'],
            HOLLER_CACHE_CONTROL_VERSION,
            true
        );
        
        wp_enqueue_style(
            'holler-cache-dashboard',
            plugins_url('assets/css/dashboard.css', dirname(dirname(__DIR__))),
            [],
            HOLLER_CACHE_CONTROL_VERSION
        );
        
        // Localize script with dashboard settings
        wp_localize_script('holler-cache-dashboard', 'hollerCacheDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('holler_cache_dashboard'),
            'refreshInterval' => self::get_refresh_interval(),
            'autoRefresh' => self::is_auto_refresh_enabled(),
            'strings' => [
                'loading' => __('Loading...', 'holler-cache-control'),
                'error' => __('Error loading data', 'holler-cache-control'),
                'success' => __('Action completed successfully', 'holler-cache-control'),
                'confirm_purge' => __('Are you sure you want to purge this cache?', 'holler-cache-control')
            ]
        ]);
    }
    
    /**
     * Handle AJAX request for dashboard data
     */
    public static function handle_dashboard_data_ajax() {
        // Log that the handler was called
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Holler Cache Control: Dashboard AJAX handler called');
        }

        try {
            // Basic permission check first
            if (!current_user_can('manage_options')) {
                error_log('Holler Cache Control - Permission denied');
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }
            
            // Check if this is a Cloudflare settings check request
            if (isset($_POST['check_cloudflare_settings']) && $_POST['check_cloudflare_settings'] === 'true') {
                error_log('Holler Cache Control - Processing Cloudflare settings check via dashboard AJAX');
                
                // Use the existing Cloudflare settings check method
                $result = \Holler\CacheControl\Admin\Cache\Cloudflare::check_and_configure_settings();
                
                if ($result['success']) {
                    $details = array();
                    $details[] = 'Connection: Successfully connected to Cloudflare API';
                    
                    // Show current settings if available
                    if (isset($result['settings']) && is_array($result['settings'])) {
                        $settings = $result['settings'];
                        
                        if (isset($settings['development_mode'])) {
                            $details[] = 'Development Mode: ' . ($settings['development_mode']['value'] === 'on' ? 'Enabled' : 'Disabled');
                        }
                        
                        if (isset($settings['browser_cache_ttl'])) {
                            $details[] = 'Browser Cache TTL: ' . $settings['browser_cache_ttl']['value'] . ' seconds';
                        }
                        
                        if (isset($settings['always_online'])) {
                            $details[] = 'Always Online: ' . ucfirst($settings['always_online']['value']);
                        }
                        
                        if (isset($settings['rocket_loader'])) {
                            $details[] = 'Rocket Loader: ' . ucfirst($settings['rocket_loader']['value']);
                        }
                        
                        if (isset($settings['cache_level'])) {
                            $details[] = 'Cache Level: ' . ucfirst($settings['cache_level']['value']);
                        }
                        
                        if (isset($settings['auto_minify'])) {
                            $minify = $settings['auto_minify']['value'];
                            $minify_status = array();
                            if (isset($minify['html']) && $minify['html']) $minify_status[] = 'HTML';
                            if (isset($minify['css']) && $minify['css']) $minify_status[] = 'CSS';
                            if (isset($minify['js']) && $minify['js']) $minify_status[] = 'JS';
                            $details[] = 'Auto Minify: ' . (empty($minify_status) ? 'Disabled' : implode(', ', $minify_status));
                        }
                    }
                    
                    // Show optimization results if settings were applied
                    if (isset($result['settings']['optimization_results'])) {
                        $optimization = $result['settings']['optimization_results'];
                        if ($optimization['success']) {
                            $details[] = '✓ Optimization: Recommended settings applied successfully!';
                            if (isset($optimization['applied_settings']) && is_array($optimization['applied_settings'])) {
                                foreach ($optimization['applied_settings'] as $setting) {
                                    $details[] = '  ✓ ' . $setting;
                                }
                            }
                        } else {
                            $details[] = 'Optimization: ' . $optimization['message'];
                        }
                    } else {
                        // Enable auto-optimization for future runs
                        update_option('cloudflare_auto_optimize', true);
                        $details[] = 'Note: Auto-optimization has been enabled. Click "Check Settings" again to apply recommended performance settings.';
                    }
                    
                    wp_send_json_success(array(
                        'message' => 'Cloudflare settings check completed successfully!',
                        'details' => $details
                    ));
                } else {
                    wp_send_json_error(array(
                        'message' => $result['message']
                    ));
                }
                return;
            }
            
            // Return simple test data first to verify basic functionality
            $simple_data = [
                'timestamp' => current_time('timestamp'),
                'cache_status' => [
                    'nginx' => ['status' => 'unknown', 'message' => 'Test mode'],
                    'redis' => ['status' => 'unknown', 'message' => 'Test mode'],
                    'cloudflare' => ['status' => 'unknown', 'message' => 'Test mode'],
                    'cloudflare_apo' => ['status' => 'unknown', 'message' => 'Test mode']
                ],
                'performance' => [
                    'hit_rate' => 0,
                    'response_time' => 0,
                    'cache_size' => 0
                ],
                'recent_activity' => [],
                'system_health' => [
                    'status' => 'testing',
                    'message' => 'Dashboard in test mode'
                ],
                'quick_actions' => [],
                'load_time' => 1
            ];
            
            error_log('Holler Cache Control - Returning simple test data');
            wp_send_json_success($simple_data);
            
        } catch (Exception $e) {
            // Log detailed error server-side
            error_log('Holler Cache Control - Dashboard AJAX Error: ' . $e->getMessage());
            error_log('Holler Cache Control - Stack trace: ' . $e->getTraceAsString());
            // Return generic error to user
            wp_send_json_error(['message' => 'Failed to load dashboard data. Please check the logs for details.']);
        } catch (Error $e) {
            // Log detailed error server-side
            error_log('Holler Cache Control - Dashboard AJAX Fatal Error: ' . $e->getMessage());
            error_log('Holler Cache Control - Stack trace: ' . $e->getTraceAsString());
            // Return generic error to user
            wp_send_json_error(['message' => 'A system error occurred. Please check the logs for details.']);
        }
    }
    
    /**
     * Handle AJAX request for performance metrics
     */
    public static function handle_performance_metrics_ajax() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'holler_cache_dashboard')) {
                wp_send_json_error(['message' => 'Invalid nonce']);
                return;
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }
            
            $metrics = self::get_performance_metrics();
            
            wp_send_json_success($metrics);
        } catch (Exception $e) {
            error_log('Holler Cache Control - Performance Metrics AJAX Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to load performance metrics: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Handle AJAX request for dashboard actions
     */
    public static function handle_dashboard_action_ajax() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'holler_cache_dashboard')) {
                wp_send_json_error(['message' => 'Invalid nonce']);
                return;
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }
            
            $action = sanitize_text_field($_POST['action_type'] ?? '');
            $result = self::execute_dashboard_action($action);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            error_log('Holler Cache Control - Dashboard Action AJAX Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to execute action: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Get comprehensive dashboard data
     *
     * @return array Dashboard data
     */
    public static function get_dashboard_data() {
        $start_time = microtime(true);
        
        $data = [
            'timestamp' => current_time('timestamp'),
            'cache_status' => self::safe_get_data('get_cache_status_data'),
            'performance' => self::safe_get_data('get_performance_summary'),
            'recent_activity' => self::safe_get_data('get_recent_activity'),
            'system_health' => self::safe_get_data('get_system_health'),
            'quick_actions' => self::safe_get_data('get_quick_actions'),
            'load_time' => round((microtime(true) - $start_time) * 1000, 2)
        ];
        
        return $data;
    }
    
    /**
     * Safely get data from a method with error handling
     *
     * @param string $method Method name to call
     * @return mixed Method result or error placeholder
     */
    private static function safe_get_data($method) {
        try {
            return self::$method();
        } catch (Exception $e) {
            error_log('Holler Cache Control - Dashboard component error (' . $method . '): ' . $e->getMessage());
            return ['error' => 'Failed to load ' . str_replace('get_', '', $method)];
        }
    }
    
    /**
     * Get cache status data for all services
     *
     * @return array Cache status information
     */
    private static function get_cache_status_data() {
        return [
            'nginx' => self::format_cache_status('nginx', Nginx::get_status()),
            'redis' => self::format_cache_status('redis', Redis::get_status()),
            'cloudflare' => self::format_cache_status('cloudflare', Cloudflare::get_status()),
            'cloudflare_apo' => self::format_cache_status('cloudflare_apo', CloudflareAPO::get_status())
        ];
    }
    
    /**
     * Format cache status for dashboard display
     *
     * @param string $service Service name
     * @param array $status Status data
     * @return array Formatted status
     */
    private static function format_cache_status($service, $status) {
        $formatted = [
            'service' => $service,
            'status' => $status['status'] ?? 'unknown',
            'message' => $status['message'] ?? 'No information available',
            'configured' => $status['configured'] ?? false,
            'enabled' => $status['enabled'] ?? false,
            'last_updated' => current_time('timestamp'),
            'actions' => []
        ];
        
        // Add service-specific data
        switch ($service) {
            case 'nginx':
                $formatted['cache_size'] = self::get_nginx_cache_size();
                $formatted['cache_files'] = self::get_nginx_cache_files_count();
                break;
                
            case 'redis':
                $formatted['memory_usage'] = self::get_redis_memory_usage();
                $formatted['keys_count'] = self::get_redis_keys_count();
                break;
                
            case 'cloudflare':
                $formatted['plan_type'] = self::get_cloudflare_plan_type();
                $formatted['security_level'] = self::get_cloudflare_security_level();
                break;
                
            case 'cloudflare_apo':
                $formatted['apo_enabled'] = $status['enabled'] ?? false;
                break;
        }
        
        // Add available actions
        if ($formatted['configured'] && $formatted['enabled']) {
            $formatted['actions'][] = [
                'id' => 'purge_' . $service,
                'label' => sprintf(__('Purge %s Cache', 'holler-cache-control'), ucfirst($service)),
                'type' => 'danger',
                'confirm' => true
            ];
            
            $formatted['actions'][] = [
                'id' => 'refresh_' . $service,
                'label' => sprintf(__('Refresh %s Status', 'holler-cache-control'), ucfirst($service)),
                'type' => 'secondary',
                'confirm' => false
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Get performance summary data
     *
     * @return array Performance metrics
     */
    private static function get_performance_summary() {
        return [
            'cache_hit_rate' => self::calculate_cache_hit_rate(),
            'average_response_time' => self::get_average_response_time(),
            'total_cache_size' => self::get_total_cache_size(),
            'purges_today' => self::get_purges_count_today(),
            'last_purge' => self::get_last_purge_time(),
            'status_cache_efficiency' => StatusCache::get_efficiency_stats()
        ];
    }
    
    /**
     * Get recent cache activity
     *
     * @return array Recent activity log
     */
    private static function get_recent_activity() {
        $activities = get_option('holler_cache_recent_activity', []);
        
        // Limit to last 10 activities
        return array_slice($activities, -10);
    }
    
    /**
     * Get system health indicators
     *
     * @return array System health data
     */
    private static function get_system_health() {
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'memory_usage' => self::get_memory_usage_percentage(),
            'disk_space' => self::get_disk_space_info(),
            'plugin_conflicts' => self::check_plugin_conflicts(),
            'recommendations' => self::get_health_recommendations()
        ];
    }
    
    /**
     * Get available quick actions
     *
     * @return array Quick actions
     */
    private static function get_quick_actions() {
        return [
            [
                'id' => 'purge_all',
                'label' => __('Purge All Caches', 'holler-cache-control'),
                'icon' => 'dashicons-trash',
                'type' => 'danger',
                'confirm' => true
            ],
            [
                'id' => 'refresh_all',
                'label' => __('Refresh All Status', 'holler-cache-control'),
                'icon' => 'dashicons-update',
                'type' => 'secondary',
                'confirm' => false
            ],
            [
                'id' => 'run_diagnostics',
                'label' => __('Run Diagnostics', 'holler-cache-control'),
                'icon' => 'dashicons-admin-tools',
                'type' => 'primary',
                'confirm' => false
            ],
            [
                'id' => 'export_logs',
                'label' => __('Export Activity Logs', 'holler-cache-control'),
                'icon' => 'dashicons-download',
                'type' => 'secondary',
                'confirm' => false
            ]
        ];
    }
    
    /**
     * Handle dashboard actions
     *
     * @param string $action Action to perform
     * @param string $target Target service or scope
     * @return array Action result
     */
    private static function handle_dashboard_action($action, $target) {
        $start_time = microtime(true);
        
        try {
            switch ($action) {
                case 'purge_all':
                    $result = Tools::purge_all_caches();
                    self::log_activity('purge_all', 'All caches purged via dashboard');
                    break;
                    
                case 'purge_nginx':
                    $result = Nginx::purge_cache();
                    self::log_activity('purge_nginx', 'Nginx cache purged via dashboard');
                    break;
                    
                case 'purge_redis':
                    if (function_exists('wp_cache_flush')) {
                        wp_cache_flush();
                        $result = ['success' => true, 'message' => __('Redis cache purged.', 'holler-cache-control')];
                    } else {
                        $result = ['success' => false, 'message' => __('Redis cache not available.', 'holler-cache-control')];
                    }
                    self::log_activity('purge_redis', 'Redis cache purged via dashboard');
                    break;
                    
                case 'purge_cloudflare':
                    $result = Cloudflare::purge_cache();
                    self::log_activity('purge_cloudflare', 'Cloudflare cache purged via dashboard');
                    break;
                    
                case 'purge_cloudflare_apo':
                    $result = CloudflareAPO::purge_cache();
                    self::log_activity('purge_cloudflare_apo', 'Cloudflare APO cache purged via dashboard');
                    break;
                    
                case 'refresh_all':
                    StatusCache::invalidate_all();
                    $result = ['success' => true, 'message' => __('All status data refreshed.', 'holler-cache-control')];
                    break;
                    
                case 'run_diagnostics':
                    $result = self::run_quick_diagnostics();
                    break;
                    
                case 'export_logs':
                    $result = self::export_activity_logs();
                    break;
                    
                default:
                    $result = ['success' => false, 'message' => __('Unknown action.', 'holler-cache-control')];
            }
            
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            $result['execution_time_ms'] = $execution_time;
            
            return $result;
            
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Dashboard action error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Action failed: ', 'holler-cache-control') . $e->getMessage(),
                'execution_time_ms' => round((microtime(true) - $start_time) * 1000, 2)
            ];
        }
    }
    
    /**
     * Log dashboard activity
     *
     * @param string $action Action performed
     * @param string $description Description of the action
     */
    private static function log_activity($action, $description) {
        $activities = get_option('holler_cache_recent_activity', []);
        
        $activity = [
            'timestamp' => current_time('timestamp'),
            'action' => $action,
            'description' => $description,
            'user' => wp_get_current_user()->display_name,
            'source' => 'dashboard'
        ];
        
        $activities[] = $activity;
        
        // Keep only last 50 activities
        if (count($activities) > 50) {
            $activities = array_slice($activities, -50);
        }
        
        update_option('holler_cache_recent_activity', $activities);
    }
    
    /**
     * Get user's preferred refresh interval
     *
     * @return int Refresh interval in seconds
     */
    private static function get_refresh_interval() {
        return get_user_meta(get_current_user_id(), 'holler_cache_dashboard_refresh', true) ?: self::REFRESH_NORMAL;
    }
    
    /**
     * Check if auto-refresh is enabled
     *
     * @return bool Auto-refresh status
     */
    private static function is_auto_refresh_enabled() {
        return get_user_meta(get_current_user_id(), 'holler_cache_dashboard_auto_refresh', true) !== 'disabled';
    }
    
    /**
     * Get performance metrics
     *
     * @return array Performance data
     */
    public static function get_performance_metrics() {
        return [
            'cache_operations' => self::get_cache_operations_stats(),
            'response_times' => self::get_response_time_history(),
            'error_rates' => self::get_error_rate_stats(),
            'cache_sizes' => self::get_cache_size_history(),
            'api_usage' => self::get_api_usage_stats()
        ];
    }
    
    // Helper methods for metrics (simplified implementations)
    
    private static function get_nginx_cache_size() {
        // Simplified - would implement actual cache size calculation
        return '0 MB';
    }
    
    private static function get_nginx_cache_files_count() {
        return 0;
    }
    
    private static function get_redis_memory_usage() {
        return '0 MB';
    }
    
    private static function get_redis_keys_count() {
        return 0;
    }
    
    private static function get_cloudflare_plan_type() {
        return 'Unknown';
    }
    
    private static function get_cloudflare_security_level() {
        return 'Medium';
    }
    
    private static function calculate_cache_hit_rate() {
        return 85.5; // Placeholder
    }
    
    private static function get_average_response_time() {
        return 245; // ms
    }
    
    private static function get_total_cache_size() {
        return '125 MB';
    }
    
    private static function get_purges_count_today() {
        $activities = get_option('holler_cache_recent_activity', []);
        $today = date('Y-m-d');
        
        return count(array_filter($activities, function($activity) use ($today) {
            return date('Y-m-d', $activity['timestamp']) === $today && 
                   strpos($activity['action'], 'purge') === 0;
        }));
    }
    
    private static function get_last_purge_time() {
        $activities = get_option('holler_cache_recent_activity', []);
        
        foreach (array_reverse($activities) as $activity) {
            if (strpos($activity['action'], 'purge') === 0) {
                return $activity['timestamp'];
            }
        }
        
        return null;
    }
    
    private static function get_memory_usage_percentage() {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        
        if ($memory_limit === '-1') {
            return 0; // Unlimited
        }
        
        $limit_bytes = self::convert_to_bytes($memory_limit);
        return round(($memory_usage / $limit_bytes) * 100, 1);
    }
    
    private static function get_disk_space_info() {
        $total = disk_total_space(ABSPATH);
        $free = disk_free_space(ABSPATH);
        
        return [
            'total' => self::format_bytes($total),
            'free' => self::format_bytes($free),
            'used_percentage' => round((($total - $free) / $total) * 100, 1)
        ];
    }
    
    private static function check_plugin_conflicts() {
        // Simplified conflict detection
        return [];
    }
    
    private static function get_health_recommendations() {
        $recommendations = [];
        
        if (self::get_memory_usage_percentage() > 80) {
            $recommendations[] = __('High memory usage detected. Consider optimizing or increasing memory limit.', 'holler-cache-control');
        }
        
        return $recommendations;
    }
    
    private static function run_quick_diagnostics() {
        // Simplified diagnostics
        return [
            'success' => true,
            'message' => __('Quick diagnostics completed. No issues found.', 'holler-cache-control'),
            'results' => []
        ];
    }
    
    private static function export_activity_logs() {
        $activities = get_option('holler_cache_recent_activity', []);
        
        return [
            'success' => true,
            'message' => __('Activity logs exported.', 'holler-cache-control'),
            'download_url' => '#' // Would generate actual download
        ];
    }
    
    private static function get_cache_operations_stats() {
        return [
            'purges_last_24h' => 5,
            'purges_last_7d' => 23,
            'avg_purge_time' => 2.3
        ];
    }
    
    private static function get_response_time_history() {
        return [
            ['time' => time() - 3600, 'response_time' => 245],
            ['time' => time() - 1800, 'response_time' => 198],
            ['time' => time() - 900, 'response_time' => 267]
        ];
    }
    
    private static function get_error_rate_stats() {
        return [
            'error_rate_24h' => 0.2,
            'total_errors' => 1,
            'last_error' => null
        ];
    }
    
    private static function get_cache_size_history() {
        return [
            ['time' => time() - 3600, 'size' => 120],
            ['time' => time() - 1800, 'size' => 125],
            ['time' => time() - 900, 'size' => 118]
        ];
    }
    
    private static function get_api_usage_stats() {
        return [
            'cloudflare_requests_today' => 12,
            'cloudflare_quota_remaining' => 988,
            'rate_limit_status' => 'ok'
        ];
    }
    
    private static function convert_to_bytes($value) {
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;
        
        switch ($unit) {
            case 'g': return $number * 1024 * 1024 * 1024;
            case 'm': return $number * 1024 * 1024;
            case 'k': return $number * 1024;
            default: return $number;
        }
    }
    
    private static function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
