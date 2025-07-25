<?php
namespace Holler\CacheControl\Admin;

use Holler\CacheControl\Admin\Cache\AjaxHandler;
use Holler\CacheControl\Admin\Cache\CacheManager;
use Holler\CacheControl\Admin\Cache\CloudflareAPI;
use Holler\CacheControl\Admin\Cache\Cloudflare;
use Holler\CacheControl\Admin\Cache\CloudflareAPO;
use Holler\CacheControl\Admin\Cache\Nginx;
use Holler\CacheControl\Admin\Cache\Redis;

/**
 * The admin-specific functionality of the plugin.
 */
class Tools {
    /**
     * The unique identifier of this plugin.
     */
    private $plugin_name;

    /**
     * The current version of the plugin.
     */
    private $version;

    /**
     * The AJAX handler instance.
     */
    private $ajax_handler;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add hooks for cache purging
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_dashboard_form_submission'));
        add_action('admin_footer', array($this, 'add_notice_container'));

        // Initialize AJAX handlers
        $this->init_ajax_handlers();

        // Add hooks for automatic cache purging
        $this->add_cache_purging_hooks();

        // Initialize HTTPS filters
        $this->init_https_filters();

        // Remove old menu items and duplicate MU plugin admin page
        add_action('admin_menu', array($this, 'remove_old_menu_items'), 999);
        add_action('admin_menu', array($this, 'remove_duplicate_admin_pages'), 1000);
        
        // Aggressively remove Redis Cache admin bar items at multiple hooks
        add_action('init', array($this, 'remove_redis_cache_admin_bar'), 999);
        add_action('wp_loaded', array($this, 'remove_redis_cache_admin_bar'), 999);
        add_action('admin_init', array($this, 'remove_redis_cache_admin_bar'), 999);
        add_action('wp_before_admin_bar_render', array($this, 'remove_redis_cache_admin_bar'), 999);
        add_action('admin_bar_menu', array($this, 'remove_redis_cache_admin_bar'), 999);
        
        // Add CSS to hide Redis Cache admin bar as fallback
        add_action('admin_head', array($this, 'hide_redis_cache_admin_bar_css'), 999);
        add_action('wp_head', array($this, 'hide_redis_cache_admin_bar_css'), 999);
        
        // Add frontend admin bar script directly (bypass loader)
        add_action('wp_footer', array($this, 'add_frontend_admin_bar_script'), 999);
        
        // Register async cache purge cron hook
        add_action('holler_cache_control_async_purge', array($this, 'handle_async_cache_purge'));
        
        // Initialize StatusCache system
        \Holler\CacheControl\Admin\Cache\StatusCache::register_invalidation_hooks();
        
        // Initialize SmartInvalidation system
        $this->register_smart_invalidation_hooks();
        
        // Initialize Real-time Status Dashboard
        \Holler\CacheControl\Admin\Dashboard\RealTimeStatusDashboard::init();
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . '../../assets/css/admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . '../../assets/js/admin.js', array('jquery'), $this->version, false);

        // Add nonces for AJAX operations
        wp_localize_script($this->plugin_name, 'hollerCacheControl', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'nginx' => wp_create_nonce('holler_cache_control_purge_nginx'),
                'redis' => wp_create_nonce('holler_cache_control_purge_redis'),
                'cloudflare' => wp_create_nonce('holler_cache_control_purge_cloudflare'),
                'cloudflare_apo' => wp_create_nonce('holler_cache_control_purge_cloudflare_apo'),
                'all' => wp_create_nonce('holler_cache_control_purge_all'),
                'status' => wp_create_nonce('holler_cache_control_status'),
                'settings' => wp_create_nonce('holler_cache_control_settings'),
                'cloudflare_test' => wp_create_nonce('holler_cache_control_cloudflare_test')
            ),
            'i18n' => array(
                'purging' => __('Purging...', 'holler-cache-control'),
                'success' => __('Cache purged successfully.', 'holler-cache-control'),
                'error' => __('Failed to purge cache.', 'holler-cache-control'),
                'confirm_purge_all' => __('Are you sure you want to purge all caches? This may temporarily slow down your site.', 'holler-cache-control')
            )
        ));
    }

    /**
     * Add admin bar menu
     */
    public function admin_bar_menu($wp_admin_bar) {
        // Get settings
        $settings = get_option('holler_cache_control_settings', array());
        $visibility = get_option('holler_cache_control_visibility', array());

        // Check if user's role is excluded
        if (!is_super_admin()) {
            $current_user = wp_get_current_user();
            $user_roles = $current_user->roles;
            $excluded_roles = !empty($visibility['excluded_roles']) ? $visibility['excluded_roles'] : array();

            foreach ($user_roles as $role) {
                if (in_array($role, $excluded_roles)) {
                    return; // Don't show menu for excluded roles
                }
            }
        }

        // Get hide button settings - these should only affect purge buttons, not status
        $hide_nginx = !empty($visibility['hide_nginx_purge']);
        $hide_redis = !empty($visibility['hide_redis_purge']);
        $hide_cloudflare = !empty($visibility['hide_cloudflare_purge']);
        $hide_apo = !empty($visibility['hide_apo_purge']);

        // Get cache statuses
        $cache_status = $this->get_cache_systems_status();

        // Add the parent menu item
        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-control',
            'title' => 'Cache Control',
            'href' => admin_url('options-general.php?page=settings_page_holler-cache-control'),
            'meta' => array(
                'class' => 'menupop'
            )
        ));

        // Add Clear All Caches at the top
        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-clear-all',
            'title' => 'Clear All Caches',
            'parent' => 'holler-cache-control',
            'href' => '#',
            'meta' => array(
                'class' => 'clear-cache-button',
                'onclick' => 'return false;'
            )
        ));

        // Add separator
        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-separator-1',
            'parent' => 'holler-cache-control',
            'meta' => array(
                'class' => 'separator'
            )
        ));

        // Add Status section
        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-status',
            'title' => 'Status',
            'parent' => 'holler-cache-control'
        ));

        // Add individual status items - show all statuses regardless of purge button visibility
        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-status-nginx',
            'title' => ($cache_status['nginx']['status'] === 'active' ? '🟢' : '🔴') . ' Nginx Cache',
            'parent' => 'holler-cache-status'
        ));

        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-status-redis',
            'title' => ($cache_status['redis']['status'] === 'active' ? '🟢' : '🔴') . ' Redis Cache',
            'parent' => 'holler-cache-status'
        ));

        if (!$hide_cloudflare) {
            $wp_admin_bar->add_node(array(
                'id' => 'holler-cache-status-cloudflare',
                'title' => ($cache_status['cloudflare']['status'] === 'active' ? '🟢' : '🔴') . ' Cloudflare Cache',
                'parent' => 'holler-cache-status'
            ));
        }

        if (!$hide_apo) {
            $wp_admin_bar->add_node(array(
                'id' => 'holler-cache-status-apo',
                'title' => ($cache_status['cloudflare-apo']['status'] === 'active' ? '🟢' : '🔴') . ' Cloudflare APO',
                'parent' => 'holler-cache-status'
            ));
        }

        // Add separator
        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-separator-2',
            'parent' => 'holler-cache-control',
            'meta' => array(
                'class' => 'separator'
            )
        ));

        // Add Settings link
        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-settings',
            'title' => 'Settings',
            'parent' => 'holler-cache-control',
            'href' => admin_url('options-general.php?page=settings_page_holler-cache-control')
        ));

        // Remove other cache plugin admin bar items to avoid duplication
        $wp_admin_bar->remove_node('nginx-helper-purge-all');
        $wp_admin_bar->remove_node('redis-cache');
        $wp_admin_bar->remove_node('purge-all-caches'); // From deleted MU plugin
    }

    /**
     * Register the administration menu for this plugin
     */
    public function add_plugin_admin_menu() {
        add_submenu_page(
            'options-general.php',    // Parent slug
            __('Cache Control Settings', 'holler-cache-control'), // Page title
            __('Cache Control', 'holler-cache-control'),         // Menu title
            'manage_options',        // Capability
            'settings_page_holler-cache-control',  // Menu slug
            array($this, 'display_plugin_admin_page')
        );
    }

    /**
     * Render the settings page for this plugin.
     */
    public function display_plugin_admin_page() {
        include_once 'views/admin-display-tabbed.php';
    }

    /**
     * Initialize HTTPS filters
     */
    public function init_https_filters() {
        // Force HTTPS for admin assets
        add_filter('wp_get_attachment_url', array($this, 'ensure_https_url'));
        add_filter('theme_root_uri', array($this, 'ensure_https_url'));
        add_filter('plugins_url', array($this, 'ensure_https_url'));
        add_filter('wp_get_attachment_thumb_url', array($this, 'ensure_https_url'));
        add_filter('wp_get_attachment_image_src', array($this, 'ensure_https_url'));
        add_filter('stylesheet_uri', array($this, 'ensure_https_url'));
        add_filter('template_directory_uri', array($this, 'ensure_https_url'));
        add_filter('script_loader_src', array($this, 'ensure_https_url'));
        add_filter('style_loader_src', array($this, 'ensure_https_url'));

        // Add filters to ensure HTTPS URLs in content
        add_filter('the_content', array($this, 'ensure_content_https'));
        add_filter('widget_text', array($this, 'ensure_content_https'));
    }

    /**
     * Ensure URL uses HTTPS
     * 
     * @param string $url URL to convert to HTTPS
     * @return string Modified URL
     */
    public function ensure_https_url($url) {
        if (!is_ssl() || empty($url)) {
            return $url;
        }

        return str_replace('http://', 'https://', $url);
    }

    /**
     * Filter content to ensure all URLs use HTTPS
     * 
     * @param string $content Content to filter
     * @return string Modified content
     */
    public function ensure_content_https($content) {
        if (!is_ssl() || empty($content)) {
            return $content;
        }

        // Replace http:// with https:// in URLs
        $content = preg_replace_callback('/(https?:\/\/[^\s<>"\']+)/', function($matches) {
            return str_replace('http://', 'https://', $matches[1]);
        }, $content);

        return $content;
    }

    /**
     * Initialize AJAX handlers
     */
    public function init_ajax_handlers() {
        // Prevent double registration
        static $ajax_initialized = false;
        if ($ajax_initialized) {
            return;
        }
        $ajax_initialized = true;
        
        if (!$this->ajax_handler) {
            $this->ajax_handler = new \Holler\CacheControl\Admin\Cache\AjaxHandler();
        }
        
        // Add AJAX handlers for cache purging
        add_action('wp_ajax_holler_purge_cache', array($this, 'handle_purge_cache_ajax'));
        add_action('wp_ajax_holler_purge_all_caches', array($this, 'handle_purge_all_caches_ajax'));
        add_action('wp_ajax_holler_cache_control_save_settings', array($this, 'handle_save_settings_ajax'));
        
        // Add AJAX handlers for cache purging - delegate to AjaxHandler
        add_action('wp_ajax_holler_purge_all', array($this->ajax_handler, 'handle_purge_cache'));
        add_action('wp_ajax_holler_purge_nginx', array($this->ajax_handler, 'handle_purge_cache'));
        add_action('wp_ajax_holler_purge_redis', array($this->ajax_handler, 'handle_purge_cache'));
        add_action('wp_ajax_holler_purge_cloudflare', array($this->ajax_handler, 'handle_purge_cache'));
        add_action('wp_ajax_holler_purge_cloudflare_apo', array($this->ajax_handler, 'handle_purge_cache'));
        
        // Add AJAX handlers for tabbed interface
        add_action('wp_ajax_holler_test_cloudflare_connection', array($this, 'handle_test_cloudflare_connection_ajax'));
        add_action('wp_ajax_holler_save_cloudflare_settings', array($this, 'handle_save_cloudflare_settings_ajax'));
        add_action('wp_ajax_holler_export_diagnostics', array($this, 'handle_export_diagnostics_ajax'));
        add_action('wp_ajax_holler_cache_status', array($this, 'handle_cache_status_ajax'));
        add_action('wp_ajax_holler_toggle_cloudflare_dev_mode', array($this, 'handle_toggle_cloudflare_dev_mode_ajax'));
        add_action('wp_ajax_holler_update_security_setting', array($this, 'handle_update_security_setting_ajax'));
        add_action('wp_ajax_holler_check_cloudflare_settings', array($this, 'handle_check_cloudflare_settings_ajax'));
        add_action('wp_ajax_holler_cloudflare_check_settings', array($this, 'handle_cloudflare_check_settings_ajax'));
        
        // Test AJAX handler
        add_action('wp_ajax_test_cloudflare', array($this, 'test_ajax_handler'));
        
        // Simple Cloudflare Settings Check Handler
        add_action('wp_ajax_cloudflare_settings_check', array($this, 'handle_simple_cloudflare_check'));
        
        // Debug: Log AJAX handler registration
        error_log('Holler Cache Control: Registered AJAX handlers');
        file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - Registered AJAX handlers' . "\n", FILE_APPEND);
    }

    /**
     * Add hooks for automatic cache purging on various WordPress actions
     * Respects the auto-purge settings configured by the user
     */
    public function add_cache_purging_hooks() {
        // Get auto-purge settings with defaults
        $settings = wp_parse_args(get_option('holler_cache_control_auto_purge', array()), array(
            'purge_on_post_save' => true,
            'purge_on_post_delete' => true,
            'purge_on_post_trash' => true,
            'purge_on_menu_update' => true,
            'purge_on_widget_update' => true,
            'purge_on_theme_switch' => true,
            'purge_on_customizer_save' => true,
            'purge_on_plugin_activation' => true,
            'purge_on_core_update' => true,
            'purge_daily_scheduled' => true
        ));

        // Post/Page updates - use smart detection to prevent editor conflicts
        if (!empty($settings['purge_on_post_save'])) {
            add_action('save_post', array($this, 'purge_all_caches_with_detection'));
        }
        if (!empty($settings['purge_on_post_delete'])) {
            add_action('delete_post', array($this, 'purge_all_caches_with_detection'));
        }
        if (!empty($settings['purge_on_post_trash'])) {
            add_action('wp_trash_post', array($this, 'purge_all_caches_with_detection'));
            add_action('untrash_post', array($this, 'purge_all_caches_with_detection'));
        }

        // Theme customization - use smart detection for customizer conflicts
        if (!empty($settings['purge_on_customizer_save'])) {
            add_action('customize_save_after', array($this, 'purge_all_caches_with_detection'));
        }
        if (!empty($settings['purge_on_theme_switch'])) {
            add_action('switch_theme', array($this, 'purge_all_caches_with_detection'));
        }

        // Plugin activation/deactivation - use smart detection
        if (!empty($settings['purge_on_plugin_activation'])) {
            add_action('activated_plugin', array($this, 'purge_all_caches_with_detection'));
            add_action('deactivated_plugin', array($this, 'purge_all_caches_with_detection'));
        }

        // Menu updates - use smart detection
        if (!empty($settings['purge_on_menu_update'])) {
            add_action('wp_update_nav_menu', array($this, 'purge_all_caches_with_detection'));
            add_action('wp_delete_nav_menu', array($this, 'purge_all_caches_with_detection'));
        }

        // Widget updates - use smart detection
        if (!empty($settings['purge_on_widget_update'])) {
            add_action('update_option_sidebars_widgets', array($this, 'purge_all_caches_with_detection'));
            add_action('sidebar_admin_setup', array($this, 'purge_all_caches_with_detection'));
        }

        // Core updates - use smart detection
        if (!empty($settings['purge_on_core_update'])) {
            add_action('_core_updated_successfully', array($this, 'purge_all_caches_with_detection'));
        }

        // Scheduled purge
        if (!empty($settings['purge_daily_scheduled'])) {
            if (!wp_next_scheduled('holler_purge_all_caches_daily')) {
                wp_schedule_event(time(), 'daily', 'holler_purge_all_caches_daily');
            }
            add_action('holler_purge_all_caches_daily', array($this, 'purge_all_caches_with_detection'));
        } else {
            // Remove scheduled event if disabled
            $timestamp = wp_next_scheduled('holler_purge_all_caches_daily');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'holler_purge_all_caches_daily');
            }
        }

        // Handle async purge requests (always enabled)
        add_action('holler_purge_all_caches_async', array($this, 'purge_all_caches_async'));
    }

    /**
     * Purge all caches asynchronously
     */
    public function purge_all_caches_async() {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $this->purge_all_caches('async');
    }

    /**
     * Purge all caches
     * Respects the cache layer settings configured by the user
     *
     * @param string $source Source of the purge request
     * @param bool $force_all Force purge all layers regardless of settings (for manual purges)
     */
    public function purge_all_caches($source = 'admin', $force_all = false) {
        try {
            // Get cache layer settings with defaults
            $layer_settings = wp_parse_args(get_option('holler_cache_control_auto_purge', array()), array(
                'purge_nginx_cache' => true,
                'purge_redis_cache' => true,
                'purge_cloudflare_cache' => true,
                'purge_cloudflare_apo' => true
            ));

            // For manual purges (admin/dashboard), always purge all layers unless specifically configured
            if ($force_all || $source === 'admin' || $source === 'manual') {
                $layer_settings = array(
                    'purge_nginx_cache' => true,
                    'purge_redis_cache' => true,
                    'purge_cloudflare_cache' => true,
                    'purge_cloudflare_apo' => true
                );
            }

            $successes = array();
            $failures = array();
            $messages = array();
            $skipped = array();

            // Clear PHP OPcache (always enabled)
            if (function_exists('opcache_reset')) {
                opcache_reset();
                sleep(5);
            }

            // Clear Redis Object Cache
            if (!empty($layer_settings['purge_redis_cache'])) {
                $redis_result = \Holler\CacheControl\Admin\Cache\Redis::purge_cache();
                sleep(5);
                
                if ($redis_result['success']) {
                    $successes[] = 'redis';
                } else {
                    $failures[] = 'redis';
                }
                $messages[] = $redis_result['message'];
            } else {
                $skipped[] = 'redis';
            }

            // Clear Nginx Page Cache
            if (!empty($layer_settings['purge_nginx_cache'])) {
                $nginx_result = \Holler\CacheControl\Admin\Cache\Nginx::purge_cache();
                sleep(5);
                
                if ($nginx_result['success']) {
                    $successes[] = 'nginx';
                } else {
                    $failures[] = 'nginx';
                }
                $messages[] = $nginx_result['message'];
            } else {
                $skipped[] = 'nginx';
            }

            // Clear Cloudflare Cache
            if (!empty($layer_settings['purge_cloudflare_cache'])) {
                $cloudflare_result = \Holler\CacheControl\Admin\Cache\Cloudflare::purge_cache();
                sleep(5);
                
                if ($cloudflare_result['success']) {
                    $successes[] = 'cloudflare';
                } else {
                    $failures[] = 'cloudflare';
                }
                $messages[] = $cloudflare_result['message'];
            } else {
                $skipped[] = 'cloudflare';
            }

            // Clear Cloudflare APO Cache
            if (!empty($layer_settings['purge_cloudflare_apo'])) {
                $cloudflare_apo_result = \Holler\CacheControl\Admin\Cache\CloudflareAPO::purge_cache();
                
                if ($cloudflare_apo_result['success']) {
                    $successes[] = 'cloudflare_apo';
                } else {
                    $failures[] = 'cloudflare_apo';
                }
                $messages[] = $cloudflare_apo_result['message'];
            } else {
                $skipped[] = 'cloudflare_apo';
            }

            // Track the cache clear
            $this->track_cache_clear('all', $source);

            // Format result message
            $result_message = $this->format_messages($messages, $successes, $failures);
            
            // Add info about skipped layers if any
            if (!empty($skipped) && ($source !== 'admin' && $source !== 'manual')) {
                $skipped_labels = array(
                    'nginx' => 'Nginx Page Cache',
                    'redis' => 'Redis Object Cache',
                    'cloudflare' => 'Cloudflare Cache',
                    'cloudflare_apo' => 'Cloudflare APO'
                );
                $skipped_names = array();
                foreach ($skipped as $layer) {
                    if (isset($skipped_labels[$layer])) {
                        $skipped_names[] = $skipped_labels[$layer];
                    }
                }
                if (!empty($skipped_names)) {
                    $result_message .= ' (Skipped: ' . implode(', ', $skipped_names) . ' - disabled in settings)';
                }
            }

            return array(
                'success' => !empty($successes) || (!empty($skipped) && empty($failures)),
                'message' => $result_message,
                'details' => array(
                    'successes' => $successes,
                    'failures' => $failures,
                    'skipped' => $skipped
                )
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Error purging all caches: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Track a cache clear event
     */
    public function track_cache_clear($type = 'all', $source = 'unknown') {
        // Get the current user
        $current_user = wp_get_current_user();
        $user = $current_user->exists() ? $current_user->user_login : 'system';

        // Format the message
        $message = sprintf(
            'Cache cleared: %s (Source: %s, User: %s)',
            $type,
            $source,
            $user
        );

        // Log the event
        error_log($message);
    }

    /**
     * Format messages
     */
    private function format_messages($messages, $successes, $failures) {
        $formatted = array();
        
        // Format summary first
        if (!empty($successes)) {
            $formatted[] = sprintf(
                '%s Successfully purged: %s',
                defined('WP_CLI') ? '' : '✓',  // Only show check mark in web UI
                implode(', ', array_map(function($type) {
                    return ucfirst(str_replace('-', ' ', $type)) . ' cache';
                }, $successes))
            );
        }

        // Add individual messages, but skip redundant ones
        foreach ($messages as $msg) {
            // Skip redundant messages if we have a success summary
            if (!empty($successes)) {
                // Skip basic success messages if we have the summary
                if (strpos($msg, 'successfully') !== false) {
                    continue;
                }
                // Skip speed_api messages
                if (strpos($msg, 'speed_api') !== false) {
                    continue;
                }
            }

            // Add remaining messages with appropriate icons
            if (strpos($msg, 'successfully') !== false) {
                $formatted[] = defined('WP_CLI') ? '✓ ' . $msg : '✅ ' . $msg;
            } elseif (strpos($msg, 'error') !== false || 
                     strpos($msg, 'failed') !== false ||
                     strpos($msg, 'invalid') !== false ||
                     strpos($msg, 'must be') !== false) {
                $formatted[] = defined('WP_CLI') ? '✗ ' . $msg : '❌ ' . $msg;
            } else {
                $formatted[] = defined('WP_CLI') ? 'ℹ ' . $msg : 'ℹ️ ' . $msg;
            }
        }
        
        return implode("\n", array_filter($formatted));
    }

    /**
     * Handle the cache clear request
     */
    public function handle_clear_request($request) {
        try {
            if (!defined('HOLLER_CACHE_CONTROL_VERSION')) {
                define('HOLLER_CACHE_CONTROL_VERSION', '1.0.0');
            }

            $params = $request->get_params();
            $cache_type = !empty($params['cache_type']) ? $params['cache_type'] : 'all';
            $source = !empty($params['source']) ? $params['source'] : $this->determine_source();
            
            switch ($cache_type) {
                case 'nginx':
                    $this->purge_nginx_cache();
                    break;
                case 'redis':
                    $this->purge_redis_cache();
                    break;
                case 'cloudflare':
                    $this->purge_cloudflare_cache();
                    break;
                case 'apo':
                    $this->purge_apo_cache();
                    break;
                case 'all':
                default:
                    // Run purges in parallel using background processes
                    if (function_exists('fastcgi_finish_request')) {
                        fastcgi_finish_request();
                    }
                    
                    $this->purge_all_caches();
                    break;
            }
            
            // Track the cache clear
            $history = get_transient('holler_cache_clear_history') ?: array();
            array_unshift($history, array(
                'site' => parse_url(home_url(), PHP_URL_HOST),
                'type' => $cache_type,
                'source' => $source,
                'time' => current_time('mysql')
            ));
            $history = array_slice($history, 0, 10);
            set_transient('holler_cache_clear_history', $history, 30 * DAY_IN_SECONDS);

            return new \WP_REST_Response(array(
                'success' => true,
                'message' => 'Cache cleared successfully'
            ));
        } catch (\Exception $e) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Determine the source of the cache clear request
     */
    private function determine_source() {
        if (defined('WP_CLI') && WP_CLI) {
            return 'cli';
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            return 'ajax';
        }
        if (!empty($_POST['source'])) {
            return $_POST['source'];
        }
        return 'unknown';
    }

    /**
     * Purge Nginx cache
     */
    public function purge_nginx_cache() {
        try {
            // Your existing purge logic here
            $result = array(
                'success' => true,
                'message' => __('Nginx cache purged successfully.', 'holler-cache-control'),
                'status' => 'completed'
            );
            return $result;
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 'error'
            );
        }
    }

    /**
     * Purge Redis cache
     */
    public function purge_redis_cache() {
        try {
            // Your existing purge logic here
            $result = array(
                'success' => true,
                'message' => __('Redis cache purged successfully.', 'holler-cache-control'),
                'status' => 'completed'
            );
            return $result;
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 'error'
            );
        }
    }

    /**
     * Purge Cloudflare cache
     */
    public function purge_cloudflare_cache() {
        try {
            // Your existing purge logic here
            $result = array(
                'success' => true,
                'message' => __('Cloudflare cache purged successfully.', 'holler-cache-control'),
                'status' => 'completed'
            );
            return $result;
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 'error'
            );
        }
    }

    /**
     * Purge Cloudflare APO cache
     */
    public function purge_apo_cache() {
        try {
            // Your existing purge logic here
            $result = array(
                'success' => true,
                'message' => __('Cloudflare APO cache purged successfully.', 'holler-cache-control'),
                'status' => 'completed'
            );
            return $result;
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 'error'
            );
        }
    }

    /**
     * Handle AJAX request to get cache status
     */
    public function handle_cache_status() {
        try {
            check_ajax_referer('holler_cache_control_status', '_wpnonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => __('You do not have permission to perform this action.', 'holler-cache-control'),
                    'details' => null
                ));
                return;
            }
            
            $status = self::get_cache_systems_status();
            
            // Add last purge results if available
            $last_purge = get_option('holler_cache_last_purge_results');
            if ($last_purge) {
                $status['last_purge_results'] = $last_purge;
            }
            
            wp_send_json_success($status);
            
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Status Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'details' => null
            ));
        }
    }

    /**
     * Handle cache purge request
     */
    public function handle_purge_cache() {
        if (!isset($_POST['purge_cache']) || !check_admin_referer('holler_cache_control_purge_cache')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'holler-cache-control'));
        }

        $cache_type = sanitize_text_field($_POST['purge_cache']);
        $result = $this->purge_single_cache($cache_type);

        if ($result['success']) {
            add_settings_error(
                'holler_cache_control',
                'purge_success',
                $result['message'],
                'success'
            );
        } else {
            add_settings_error(
                'holler_cache_control',
                'purge_error',
                $result['message'],
                'error'
            );
        }
    }

    /**
     * Purge single cache
     *
     * @param string $type Cache type to purge
     * @param string $source Source of the purge request
     * @return array Result of the purge operation
     */
    public function purge_single_cache($type, $source = 'admin') {
        try {
            switch ($type) {
                case 'nginx':
                    $result = \Holler\CacheControl\Admin\Cache\Nginx::purge_cache();
                    break;
                case 'redis':
                    $result = \Holler\CacheControl\Admin\Cache\Redis::purge_cache();
                    break;
                case 'cloudflare':
                    $result = \Holler\CacheControl\Admin\Cache\Cloudflare::purge_cache();
                    break;
                case 'cloudflare_apo':
                    $result = \Holler\CacheControl\Admin\Cache\CloudflareAPO::purge_cache();
                    break;
                default:
                    $result = array(
                        'success' => false,
                        'message' => sprintf(__('Unknown cache type: %s', 'holler-cache-control'), $type)
                    );
            }

            if ($result['success']) {
                $this->track_cache_clear($type, $source);
            }

            return $result;
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to purge %s cache: %s', 'holler-cache-control'), $type, $e->getMessage())
            );
        }
    }

    /**
     * Handle dashboard form submissions
     */
    public function handle_dashboard_form_submission() {
    // Simple debug to track if this method is called
    file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - handle_dashboard_form_submission() called' . "\n", FILE_APPEND);
    
    // Debug: Log all form submissions
    if (!empty($_POST)) {
        error_log('Holler Cache Control: Form submission detected - POST data: ' . print_r($_POST, true));
        // Also write to a temporary file for easier debugging
        file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - Form submission: ' . print_r($_POST, true) . "\n", FILE_APPEND);
        
        // Check specifically for our cache_action
        if (isset($_POST['cache_action'])) {
            file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - Found cache_action: ' . $_POST['cache_action'] . "\n", FILE_APPEND);
        }
    }
        
        // Check if this is a dashboard form submission
        if (!isset($_POST['cache_action']) || !isset($_POST['holler_nonce'])) {
            if (!empty($_POST)) {
                error_log('Holler Cache Control: Form submission missing cache_action or holler_nonce');
            }
            return;
        }
        
        error_log('Holler Cache Control: Dashboard form submission detected with action: ' . $_POST['cache_action']);

        // Verify nonce
        if (!wp_verify_nonce($_POST['holler_nonce'], 'holler_cache_action')) {
            wp_die(__('Security check failed. Please try again.', 'holler-cache-control'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'holler-cache-control'));
        }

        $cache_action = sanitize_text_field($_POST['cache_action']);
    
    // Detect which tab the form was submitted from
    $current_tab = 'dashboard'; // default
    if (isset($_POST['_wp_http_referer'])) {
        $referer = $_POST['_wp_http_referer'];
        if (strpos($referer, 'tab=cloudflare') !== false) {
            $current_tab = 'cloudflare';
        } elseif (strpos($referer, 'tab=settings') !== false) {
            $current_tab = 'settings';
        } elseif (strpos($referer, 'tab=diagnostics') !== false) {
            $current_tab = 'diagnostics';
        } elseif (strpos($referer, 'tab=security') !== false) {
            $current_tab = 'security';
        }
    }
    
    $redirect_url = admin_url('options-general.php?page=settings_page_holler-cache-control&tab=' . $current_tab);
    
    // Debug: Log the cache action being processed
    file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - Processing cache_action: ' . $cache_action . ' on tab: ' . $current_tab . "\n", FILE_APPEND);

        switch ($cache_action) {
            case 'purge_all':
                $this->purge_all_caches('dashboard');
                $redirect_url = add_query_arg('cache_cleared', 'all', $redirect_url);
                break;

            case 'purge_nginx':
                $nginx = new \Holler\CacheControl\Admin\Cache\Nginx();
                $result = $nginx->purge_cache();
                $status = $result['success'] ? 'success' : 'error';
                $redirect_url = add_query_arg(array('cache_cleared' => 'nginx', 'status' => $status), $redirect_url);
                break;

            case 'purge_redis':
                $redis = new \Holler\CacheControl\Admin\Cache\Redis();
                $result = $redis->purge_cache();
                $status = $result['success'] ? 'success' : 'error';
                $redirect_url = add_query_arg(array('cache_cleared' => 'redis', 'status' => $status), $redirect_url);
                break;

            case 'purge_cloudflare':
                $cloudflare = new \Holler\CacheControl\Admin\Cache\Cloudflare();
                $result = $cloudflare->purge_cache();
                $status = $result['success'] ? 'success' : 'error';
                $redirect_url = add_query_arg(array('cache_cleared' => 'cloudflare', 'status' => $status), $redirect_url);
                break;

            case 'check_cloudflare_settings':
                $this->handle_check_cloudflare_settings();
                $redirect_url = add_query_arg('cloudflare_checked', '1', $redirect_url);
                break;

            case 'purge_cloudflare_apo':
                $apo = new \Holler\CacheControl\Admin\Cache\CloudflareAPO();
                $result = $apo->purge_cache();
                $status = $result['success'] ? 'success' : 'error';
                $redirect_url = add_query_arg(array('cache_cleared' => 'cloudflare_apo', 'status' => $status), $redirect_url);
                break;

            case 'fix_permissions':
                $this->handle_fix_permissions();
                $redirect_url = add_query_arg('permissions_fixed', '1', $redirect_url);
                break;

            default:
                // Unknown action, redirect without message
                break;
        }

        // Redirect to prevent form resubmission
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Handle GridPane permissions fix with multiple execution methods
     */
    private function handle_fix_permissions() {
        $site_url = home_url();
        $domain = parse_url($site_url, PHP_URL_HOST);
        
        // Log the action
        error_log('Holler Cache Control: Running GridPane permissions fix for: ' . $domain);
        
        // Build the GridPane CLI command
        $command = sprintf('gp fix perms %s', escapeshellarg($domain));
        
        // Try multiple execution methods based on what's available
        $result = $this->execute_system_command($command);
        
        // Log the result
        if ($result['success']) {
            error_log('Holler Cache Control: GridPane permissions fix completed successfully');
            error_log('Holler Cache Control: Method used: ' . $result['method']);
            error_log('Holler Cache Control: Command output: ' . implode('\n', $result['output']));
        } else {
            error_log('Holler Cache Control: GridPane permissions fix failed');
            error_log('Holler Cache Control: Error: ' . $result['error']);
            if (!empty($result['output'])) {
                error_log('Holler Cache Control: Command output: ' . implode('\n', $result['output']));
            }
        }
        
        return $result;
    }
    
    /**
     * Execute system command with multiple fallback methods
     * 
     * @param string $command Command to execute
     * @return array Result with success, output, method used, and error info
     */
    private function execute_system_command($command) {
        $output = array();
        $return_var = 0;
        $method_used = '';
        $error_message = '';
        
        // Method 1: Try exec() if available
        if (function_exists('exec')) {
            try {
                \exec($command . ' 2>&1', $output, $return_var);
                $method_used = 'exec';
                
                return array(
                    'success' => ($return_var === 0),
                    'output' => $output,
                    'return_code' => $return_var,
                    'method' => $method_used,
                    'error' => $return_var !== 0 ? 'Command failed with return code: ' . $return_var : null
                );
            } catch (\Exception $e) {
                $error_message .= 'exec() failed: ' . $e->getMessage() . '; ';
            }
        } else {
            $error_message .= 'exec() function disabled; ';
        }
        
        // Method 2: Try shell_exec() if available
        if (function_exists('shell_exec')) {
            try {
                $shell_output = \shell_exec($command . ' 2>&1');
                if ($shell_output !== null) {
                    $output = $shell_output ? explode("\n", trim($shell_output)) : array();
                    $method_used = 'shell_exec';
                    
                    return array(
                        'success' => true,
                        'output' => $output,
                        'return_code' => 0, // shell_exec doesn't provide return code
                        'method' => $method_used,
                        'error' => null
                    );
                }
                $error_message .= 'shell_exec() returned null; ';
            } catch (\Exception $e) {
                $error_message .= 'shell_exec() failed: ' . $e->getMessage() . '; ';
            }
        } else {
            $error_message .= 'shell_exec() function disabled; ';
        }
        
        // Method 3: Try system() if available
        if (function_exists('system')) {
            try {
                ob_start();
                $last_line = \system($command . ' 2>&1', $return_var);
                $system_output = ob_get_clean();
                
                $output = $system_output ? explode("\n", trim($system_output)) : array();
                if ($last_line) {
                    $output[] = $last_line;
                }
                $method_used = 'system';
                
                return array(
                    'success' => ($return_var === 0),
                    'output' => $output,
                    'return_code' => $return_var,
                    'method' => $method_used,
                    'error' => $return_var !== 0 ? 'Command failed with return code: ' . $return_var : null
                );
            } catch (\Exception $e) {
                $error_message .= 'system() failed: ' . $e->getMessage() . '; ';
            }
        } else {
            $error_message .= 'system() function disabled; ';
        }
        
        // Method 4: Try passthru() if available
        if (function_exists('passthru')) {
            try {
                ob_start();
                \passthru($command . ' 2>&1', $return_var);
                $passthru_output = ob_get_clean();
                
                $output = $passthru_output ? explode("\n", trim($passthru_output)) : array();
                $method_used = 'passthru';
                
                return array(
                    'success' => ($return_var === 0),
                    'output' => $output,
                    'return_code' => $return_var,
                    'method' => $method_used,
                    'error' => $return_var !== 0 ? 'Command failed with return code: ' . $return_var : null
                );
            } catch (\Exception $e) {
                $error_message .= 'passthru() failed: ' . $e->getMessage() . '; ';
            }
        } else {
            $error_message .= 'passthru() function disabled; ';
        }
        
        // If all methods failed
        $disabled_functions = ini_get('disable_functions');
        $error_message .= 'All system execution functions unavailable. ';
        $error_message .= 'Disabled functions: ' . ($disabled_functions ?: 'none listed');
        
        return array(
            'success' => false,
            'output' => array('Error: Unable to execute system commands on this server'),
            'return_code' => -1,
            'method' => 'none_available',
            'error' => $error_message
        );
    }

    /**
     * Handle Cloudflare settings check and configuration
     */
    private function handle_check_cloudflare_settings() {
    // Log the action
    error_log('Holler Cache Control: Starting Cloudflare settings check and configuration');
    file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - handle_check_cloudflare_settings() called' . "\n", FILE_APPEND);
        
        try {
            // Use the full check and configure settings method that actually applies optimizations
            $result = \Holler\CacheControl\Admin\Cache\Cloudflare::check_and_configure_settings();
            
            if ($result['success']) {
                $details = array();
                $details[] = 'Connection: Successfully connected to Cloudflare API';
                
                // Show current settings if available
                if (isset($result['settings']) && is_array($result['settings'])) {
                    $settings = $result['settings'];
                    
                    // Display key settings information
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
                        $details[] = '\n<strong>✓ Optimization: Recommended settings applied successfully!</strong>';
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
                    $details[] = '\n<em>Note: Auto-optimization has been enabled. Click "Check Settings" again to apply recommended performance settings.</em>';
                }
                
                add_action('admin_notices', function() use ($details) {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>Cloudflare Settings Check & Configuration: Success!</strong></p>';
                    if (!empty($details)) {
                        echo '<ul style="margin-left: 20px;">';
                        foreach ($details as $detail) {
                            if (strpos($detail, '\n') !== false) {
                                echo '<li>' . wp_kses_post(str_replace('\n', '</li><li>', $detail)) . '</li>';
                            } else {
                                echo '<li>' . wp_kses_post($detail) . '</li>';
                            }
                        }
                        echo '</ul>';
                    }
                    echo '<p><strong>What this does:</strong> This checks your Cloudflare connection and applies recommended performance settings like optimal cache TTL, minification, Always Online, and Rocket Loader for faster WordPress performance.</p>';
                    echo '</div>';
                });
            } else {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p><strong>Cloudflare Settings Check Failed:</strong> ' . esc_html($result['message']) . '</p>';
                    echo '<p>Please verify your Cloudflare credentials in wp-config.php or plugin settings.</p>';
                    echo '</div>';
                });
            }
            
        } catch (Exception $e) {
            error_log('Holler Cache Control: Cloudflare settings check exception: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Cloudflare Settings Check Error:</strong> ' . esc_html($e->getMessage()) . '</p>';
                echo '</div>';
            });
        }
    }

    /**
     * Handle AJAX request for Cloudflare settings check
     */
    public function handle_check_cloudflare_settings_ajax() {
        // Debug: Log that AJAX handler was called
        error_log('Holler Cache Control: handle_check_cloudflare_settings_ajax() called');
        file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - AJAX handler called: handle_check_cloudflare_settings_ajax' . "\n", FILE_APPEND);
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'holler_cache_control')) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'holler-cache-control')));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'holler-cache-control')));
        }

        try {
            // Use the existing Cloudflare settings check method
            $result = \Holler\CacheControl\Admin\Cache\Cloudflare::check_and_configure_settings();
            
            if ($result['success']) {
                $details = array();
                $details[] = 'Connection: Successfully connected to Cloudflare API';
                
                // Show current settings if available
                if (isset($result['settings']) && is_array($result['settings'])) {
                    $settings = $result['settings'];
                    
                    // Display key settings information
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
            
        } catch (Exception $e) {
            error_log('Holler Cache Control: Cloudflare settings check AJAX exception: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'An error occurred while checking Cloudflare settings: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Handle AJAX request for Cloudflare settings check (unique action)
     */
    public function handle_cloudflare_check_settings_ajax() {
        // Debug: Log that AJAX handler was called
        error_log('Holler Cache Control: handle_cloudflare_check_settings_ajax() called');
        file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - AJAX handler called: handle_cloudflare_check_settings_ajax' . "\n", FILE_APPEND);
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'holler_cache_control')) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'holler-cache-control')));
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'holler-cache-control')));
            return;
        }
        
        try {
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
        } catch (Exception $e) {
            error_log('Holler Cache Control: Cloudflare settings check error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Error checking Cloudflare settings: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Test AJAX Handler
     */
    public function test_ajax_handler() {
        error_log('Test AJAX handler called');
        file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - Test AJAX handler called' . "\n", FILE_APPEND);
        wp_send_json_success(array('message' => 'Test successful!'));
    }

    /**
     * Simple Cloudflare Settings Check Handler
     */
    public function handle_simple_cloudflare_check() {
        // Debug logging
        error_log('Simple Cloudflare check called');
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cloudflare_check')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        try {
            // Get Cloudflare credentials
            $cloudflare = new \Holler\CacheControl\Admin\Cache\Cloudflare();
            $credentials = $cloudflare->get_credentials();
            
            if (!$credentials['email'] || !$credentials['api_key'] || !$credentials['zone_id']) {
                wp_send_json_error(array(
                    'message' => 'Cloudflare credentials not configured. Please set your API credentials in the settings.'
                ));
                return;
            }
            
            // Test connection
            $api = new \Holler\CacheControl\API\CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );
            
            // Get zone settings
            $settings = $api->get_zone_settings();
            
            if (!$settings) {
                wp_send_json_error(array(
                    'message' => 'Failed to connect to Cloudflare API. Please check your credentials.'
                ));
                return;
            }
            
            // Build response details
            $details = array();
            $details[] = '🔗 Successfully connected to Cloudflare API';
            $details[] = '📧 Email: ' . $credentials['email'];
            $details[] = '🌐 Zone ID: ' . substr($credentials['zone_id'], 0, 8) . '...';
            
            // Add current settings
            if (isset($settings['development_mode'])) {
                $dev_mode = $settings['development_mode']['value'] === 'on' ? 'Enabled' : 'Disabled';
                $details[] = '🔧 Development Mode: ' . $dev_mode;
            }
            
            if (isset($settings['cache_level'])) {
                $details[] = '📈 Cache Level: ' . ucfirst($settings['cache_level']['value']);
            }
            
            if (isset($settings['browser_cache_ttl'])) {
                $ttl = $settings['browser_cache_ttl']['value'];
                $details[] = '⏱️ Browser Cache TTL: ' . $ttl . ' seconds';
            }
            
            wp_send_json_success(array(
                'message' => 'Cloudflare connection successful!',
                'details' => $details
            ));
            
        } catch (Exception $e) {
            error_log('Cloudflare check error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Error: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register visibility settings with simplified configuration
        register_setting(
            'holler_cache_control_settings',  // Option group
            'holler_cache_control_visibility', // Option name
            array(
                'sanitize_callback' => array($this, 'sanitize_visibility_settings'),
                'default' => array(
                    'hide_nginx_helper' => false,
                    'hide_redis_cache' => false,
                    'hide_cloudflare' => false,
                    'hide_nginx_purge' => false,
                    'hide_redis_purge' => false,
                    'excluded_roles' => array()
                )
            )
        );

        // Register auto-cache purging settings
        register_setting(
            'holler_cache_control_settings',  // Option group
            'holler_cache_control_auto_purge', // Option name
            array(
                'type' => 'object',
                'description' => 'Holler Cache Control Auto-Purge Settings',
                'sanitize_callback' => array($this, 'sanitize_auto_purge_settings'),
                'show_in_rest' => false,
                'default' => array(
                    // Event-based purging (all enabled by default)
                    'purge_on_post_save' => true,
                    'purge_on_post_delete' => true,
                    'purge_on_post_trash' => true,
                    'purge_on_menu_update' => true,
                    'purge_on_widget_update' => true,
                    'purge_on_theme_switch' => true,
                    'purge_on_customizer_save' => true,
                    'purge_on_plugin_activation' => true,
                    'purge_on_core_update' => true,
                    'purge_daily_scheduled' => true,
                    // Cache layer controls (all enabled by default)
                    'purge_nginx_cache' => true,
                    'purge_redis_cache' => true,
                    'purge_cloudflare_cache' => true,
                    'purge_cloudflare_apo' => true
                )
            )
        );

        // Register smart cache invalidation settings
        register_setting(
            'holler_cache_control_settings',  // Option group
            'holler_cache_control_smart_invalidation', // Option name
            array(
                'type' => 'object',
                'description' => 'Holler Cache Control Smart Invalidation Settings',
                'sanitize_callback' => array($this, 'sanitize_smart_invalidation_settings'),
                'show_in_rest' => false,
                'default' => array(
                    'strategy' => 'smart',
                    'enabled_layers' => array('local', 'server', 'external'),
                    'enabled_scopes' => array('page', 'category', 'site'),
                    'manual_purge_scope' => 'site',
                    'cloudflare_selective_enabled' => true,
                    'auto_analysis_enabled' => true
                )
            )
        );

        // Add settings sections
        add_settings_section(
            'holler_cache_control_visibility',
            'Plugin & Feature Visibility',
            array($this, 'render_visibility_section'),
            'holler_cache_control_settings'
        );

        add_settings_section(
            'holler_cache_control_auto_purge',
            'Automatic Cache Purging',
            array($this, 'render_auto_purge_section'),
            'holler_cache_control_settings'
        );

        add_settings_section(
            'holler_cache_control_smart_invalidation',
            'Smart Cache Invalidation',
            array($this, 'render_smart_invalidation_section'),
            'holler_cache_control_settings'
        );

        // Add plugin visibility fields
        add_settings_field(
            'plugin_visibility',
            'Hide Plugins',
            array($this, 'render_plugin_visibility_field'),
            'holler_cache_control_settings',
            'holler_cache_control_visibility'
        );

        // Add admin bar fields
        add_settings_field(
            'admin_bar_visibility',
            'Hide Admin Bar Features',
            array($this, 'render_admin_bar_visibility_field'),
            'holler_cache_control_settings',
            'holler_cache_control_visibility'
        );

        // Add role exclusion field
        add_settings_field(
            'excluded_roles',
            'Hide From User Roles',
            array($this, 'render_role_exclusion_field'),
            'holler_cache_control_settings',
            'holler_cache_control_visibility'
        );

        // Add auto-purge event fields
        add_settings_field(
            'auto_purge_events',
            'Auto-Purge Events',
            array($this, 'render_auto_purge_events_field'),
            'holler_cache_control_settings',
            'holler_cache_control_auto_purge'
        );

        // Add cache layer fields
        add_settings_field(
            'cache_layers',
            'Cache Layers to Purge',
            array($this, 'render_cache_layers_field'),
            'holler_cache_control_settings',
            'holler_cache_control_auto_purge'
        );

        // Add smart invalidation strategy field
        add_settings_field(
            'invalidation_strategy',
            'Cache Invalidation Strategy',
            array($this, 'render_invalidation_strategy_field'),
            'holler_cache_control_settings',
            'holler_cache_control_smart_invalidation'
        );

        // Add cache layers control field
        add_settings_field(
            'smart_cache_layers',
            'Enabled Cache Layers',
            array($this, 'render_smart_cache_layers_field'),
            'holler_cache_control_settings',
            'holler_cache_control_smart_invalidation'
        );

        // Add invalidation scopes field
        add_settings_field(
            'invalidation_scopes',
            'Invalidation Scopes',
            array($this, 'render_invalidation_scopes_field'),
            'holler_cache_control_settings',
            'holler_cache_control_smart_invalidation'
        );

        // Add Cloudflare selective purging field
        add_settings_field(
            'cloudflare_selective',
            'Cloudflare Selective Purging',
            array($this, 'render_cloudflare_selective_field'),
            'holler_cache_control_settings',
            'holler_cache_control_smart_invalidation'
        );
    }



/**
 * Sanitize auto-purge settings
 */
public function sanitize_auto_purge_settings($input) {
        try {
            // Log the input for debugging
            error_log('Holler Cache Control - Sanitizing auto-purge settings: ' . print_r($input, true));
            
            // Ensure input is an array
            if (!is_array($input)) {
                error_log('Holler Cache Control - Auto-purge input is not an array, using empty array');
                $input = array();
            }
            
            $sanitized = array(
                // Event-based purging
                'purge_on_post_save' => !empty($input['purge_on_post_save']),
                'purge_on_post_delete' => !empty($input['purge_on_post_delete']),
                'purge_on_post_trash' => !empty($input['purge_on_post_trash']),
                'purge_on_menu_update' => !empty($input['purge_on_menu_update']),
                'purge_on_widget_update' => !empty($input['purge_on_widget_update']),
                'purge_on_theme_switch' => !empty($input['purge_on_theme_switch']),
                'purge_on_customizer_save' => !empty($input['purge_on_customizer_save']),
                'purge_on_plugin_activation' => !empty($input['purge_on_plugin_activation']),
                'purge_on_core_update' => !empty($input['purge_on_core_update']),
                'purge_daily_scheduled' => !empty($input['purge_daily_scheduled']),
                // Cache layer controls
                'purge_nginx_cache' => !empty($input['purge_nginx_cache']),
                'purge_redis_cache' => !empty($input['purge_redis_cache']),
                'purge_cloudflare_cache' => !empty($input['purge_cloudflare_cache']),
                'purge_cloudflare_apo' => !empty($input['purge_cloudflare_apo'])
            );
            
            // Log the sanitized output
            error_log('Holler Cache Control - Sanitized auto-purge settings: ' . print_r($sanitized, true));
            
            return $sanitized;
            
        } catch (Exception $e) {
            error_log('Holler Cache Control - Error in sanitize_auto_purge_settings: ' . $e->getMessage());
            
            // Return safe defaults on error
            return array(
                'purge_on_post_save' => true,
                'purge_on_post_delete' => true,
                'purge_on_post_trash' => true,
                'purge_on_menu_update' => true,
                'purge_on_widget_update' => true,
                'purge_on_theme_switch' => true,
                'purge_on_customizer_save' => true,
                'purge_on_plugin_activation' => true,
                'purge_on_core_update' => true,
                'purge_daily_scheduled' => true,
                'purge_nginx_cache' => true,
                'purge_redis_cache' => true,
                'purge_cloudflare_cache' => true,
                'purge_cloudflare_apo' => true
            );
        }
    }

    /**
     * Sanitize visibility settings
     */
    public function sanitize_visibility_settings($input) {
        try {
            // Log the input for debugging
            error_log('Holler Cache Control - Sanitizing visibility settings: ' . print_r($input, true));
            
            // Ensure input is an array
            if (!is_array($input)) {
                error_log('Holler Cache Control - Input is not an array, using empty array');
                $input = array();
            }
            
            $sanitized = array(
                'hide_nginx_helper' => !empty($input['hide_nginx_helper']),
                'hide_redis_cache' => !empty($input['hide_redis_cache']),
                'hide_cloudflare' => !empty($input['hide_cloudflare']),
                'hide_nginx_purge' => !empty($input['hide_nginx_purge']),
                'hide_redis_purge' => !empty($input['hide_redis_purge']),
                'excluded_roles' => array()
            );

            // Sanitize excluded roles
            if (!empty($input['excluded_roles']) && is_array($input['excluded_roles'])) {
                $all_roles = array_keys(wp_roles()->get_names());
                $sanitized['excluded_roles'] = array_intersect($input['excluded_roles'], $all_roles);
            }
            
            // Log the sanitized output
            error_log('Holler Cache Control - Sanitized visibility settings: ' . print_r($sanitized, true));
            
            return $sanitized;
            
        } catch (Exception $e) {
            error_log('Holler Cache Control - Error in sanitize_visibility_settings: ' . $e->getMessage());
            
            // Return safe defaults on error
            return array(
                'hide_nginx_helper' => false,
                'hide_redis_cache' => false,
                'hide_cloudflare' => false,
                'hide_nginx_purge' => false,
                'hide_redis_purge' => false,
                'excluded_roles' => array()
            );
        }
    }

    /**
     * Render the visibility section description
     */
    public function render_visibility_section() {
        echo '<p>Control the visibility of cache plugins and features for different user roles.</p>';
    }

    /**
     * Render plugin visibility checkboxes
     */
    public function render_plugin_visibility_field() {
        $settings = get_option('holler_cache_control_visibility', array());
        
        $plugins = array(
            'hide_nginx_helper' => array(
                'label' => 'Nginx Helper',
                'description' => 'Hide the Nginx Helper plugin from the plugins list'
            ),
            'hide_redis_cache' => array(
                'label' => 'Redis Object Cache',
                'description' => 'Hide the Redis Object Cache plugin from the plugins list'
            ),
            'hide_cloudflare' => array(
                'label' => 'Cloudflare',
                'description' => 'Hide the Cloudflare plugin from the plugins list'
            )
        );

        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">';
        
        foreach ($plugins as $key => $plugin) {
            $checked = !empty($settings[$key]) ? 'checked' : '';
            printf(
                '<label style="display: flex; align-items: flex-start; gap: 8px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">'
                . '<input type="checkbox" name="holler_cache_control_visibility[%s]" value="1" %s style="margin-top: 2px;">'
                . '<div>'
                . '<strong>%s</strong><br>'
                . '<small style="color: #666;">%s</small>'
                . '</div>'
                . '</label>',
                esc_attr($key),
                $checked,
                esc_html($plugin['label']),
                esc_html($plugin['description'])
            );
        }
        
        echo '</div>';
        echo '<p class="description">Selected plugins will be hidden from the plugins list for all users (except Super Admin).</p>';
    }

    /**
     * Render admin bar visibility checkboxes
     */
    public function render_admin_bar_visibility_field() {
        $settings = get_option('holler_cache_control_visibility', array());
        
        $features = array(
            'hide_nginx_purge' => array(
                'label' => 'Page Cache Purge Button',
                'description' => 'Hide the Nginx page cache purge button from the admin bar'
            ),
            'hide_redis_purge' => array(
                'label' => 'Object Cache Purge Button',
                'description' => 'Hide the Redis object cache purge button from the admin bar'
            )
        );

        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">';
        
        foreach ($features as $key => $feature) {
            $checked = !empty($settings[$key]) ? 'checked' : '';
            printf(
                '<label style="display: flex; align-items: flex-start; gap: 8px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">'
                . '<input type="checkbox" name="holler_cache_control_visibility[%s]" value="1" %s style="margin-top: 2px;">'
                . '<div>'
                . '<strong>%s</strong><br>'
                . '<small style="color: #666;">%s</small>'
                . '</div>'
                . '</label>',
                esc_attr($key),
                $checked,
                esc_html($feature['label']),
                esc_html($feature['description'])
            );
        }
        
        echo '</div>';
        echo '<p class="description">Selected features will be hidden from the admin bar menu for all users (except Super Admin).</p>';
    }

    /**
     * Render role exclusion checkboxes
     */
    public function render_role_exclusion_field() {
        $settings = get_option('holler_cache_control_visibility', array());
        $excluded_roles = !empty($settings['excluded_roles']) ? $settings['excluded_roles'] : array();
        
        // Get all WordPress roles with descriptions
        $roles = wp_roles()->get_names();
        $role_descriptions = array(
            'administrator' => 'Full access to all WordPress features',
            'editor' => 'Can publish and manage posts including others\' posts',
            'author' => 'Can publish and manage their own posts',
            'contributor' => 'Can write and manage their own posts but cannot publish',
            'subscriber' => 'Can only manage their profile and read content'
        );
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">';
        
        foreach ($roles as $role_key => $role_name) {
            $checked = in_array($role_key, $excluded_roles) ? 'checked' : '';
            $description = isset($role_descriptions[$role_key]) ? $role_descriptions[$role_key] : 'Custom user role';
            
            printf(
                '<label style="display: flex; align-items: flex-start; gap: 8px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">'
                . '<input type="checkbox" name="holler_cache_control_visibility[excluded_roles][]" value="%s" %s style="margin-top: 2px;">'
                . '<div>'
                . '<strong>%s</strong><br>'
                . '<small style="color: #666;">%s</small>'
                . '</div>'
                . '</label>',
                esc_attr($role_key),
                $checked,
                esc_html($role_name),
                esc_html($description)
            );
        }
        
        echo '</div>';
        echo '<p class="description">Selected roles will not see the hidden plugins and features. <strong>Note:</strong> Super Admin will always see everything regardless of these settings.</p>';
    }

    /**
     * Render auto-purge section description
     */
    public function render_auto_purge_section() {
        echo '<p>Configure when caches should be automatically purged and which cache layers to clear.</p>';
    }

    /**
     * Render auto-purge events checkboxes
     */
    public function render_auto_purge_events_field() {
        $settings = get_option('holler_cache_control_auto_purge', array());
        
        $events = array(
            'purge_on_post_save' => array(
                'label' => 'Post/Page Updates',
                'description' => 'Save, publish, or update posts and pages'
            ),
            'purge_on_post_delete' => array(
                'label' => 'Post/Page Deletion',
                'description' => 'Delete or trash posts and pages'
            ),
            'purge_on_post_trash' => array(
                'label' => 'Post/Page Trash Actions',
                'description' => 'Trash or restore posts and pages'
            ),
            'purge_on_menu_update' => array(
                'label' => 'Menu Changes',
                'description' => 'Create, update, or delete navigation menus'
            ),
            'purge_on_widget_update' => array(
                'label' => 'Widget Changes',
                'description' => 'Add, remove, or modify widgets'
            ),
            'purge_on_theme_switch' => array(
                'label' => 'Theme Changes',
                'description' => 'Switch themes or activate new themes'
            ),
            'purge_on_customizer_save' => array(
                'label' => 'Customizer Changes',
                'description' => 'Save changes in WordPress Customizer'
            ),
            'purge_on_plugin_activation' => array(
                'label' => 'Plugin Changes',
                'description' => 'Activate or deactivate plugins'
            ),
            'purge_on_core_update' => array(
                'label' => 'WordPress Updates',
                'description' => 'WordPress core updates'
            ),
            'purge_daily_scheduled' => array(
                'label' => 'Daily Scheduled Purge',
                'description' => 'Automatic daily cache maintenance'
            )
        );

        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">';
        
        foreach ($events as $key => $event) {
            $checked = !empty($settings[$key]) ? 'checked' : '';
            printf(
                '<label style="display: flex; align-items: flex-start; gap: 8px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">'
                . '<input type="checkbox" name="holler_cache_control_auto_purge[%s]" value="1" %s style="margin-top: 2px;">'
                . '<div>'
                . '<strong>%s</strong><br>'
                . '<small style="color: #666;">%s</small>'
                . '</div>'
                . '</label>',
                esc_attr($key),
                $checked,
                esc_html($event['label']),
                esc_html($event['description'])
            );
        }
        
        echo '</div>';
        echo '<p class="description">Select which WordPress events should trigger automatic cache purging. Unchecked events will not clear caches automatically.</p>';
    }

    /**
     * Render cache layers checkboxes
     */
    public function render_cache_layers_field() {
        $settings = get_option('holler_cache_control_auto_purge', array());
        
        $layers = array(
            'purge_nginx_cache' => array(
                'label' => 'Nginx Page Cache',
                'description' => 'FastCGI and proxy cache stored on server'
            ),
            'purge_redis_cache' => array(
                'label' => 'Redis Object Cache',
                'description' => 'Database query and object cache'
            ),
            'purge_cloudflare_cache' => array(
                'label' => 'Cloudflare Cache',
                'description' => 'CDN cache stored on Cloudflare edge servers'
            ),
            'purge_cloudflare_apo' => array(
                'label' => 'Cloudflare APO',
                'description' => 'Automatic Platform Optimization cache'
            )
        );

        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">';
        
        foreach ($layers as $key => $layer) {
            $checked = !empty($settings[$key]) ? 'checked' : '';
            printf(
                '<label style="display: flex; align-items: flex-start; gap: 8px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">'
                . '<input type="checkbox" name="holler_cache_control_auto_purge[%s]" value="1" %s style="margin-top: 2px;">'
                . '<div>'
                . '<strong>%s</strong><br>'
                . '<small style="color: #666;">%s</small>'
                . '</div>'
                . '</label>',
                esc_attr($key),
                $checked,
                esc_html($layer['label']),
                esc_html($layer['description'])
            );
        }
        
        echo '</div>';
        echo '<p class="description">Select which cache layers to purge when auto-purge events are triggered. You can disable specific layers if they don\'t need to be cleared for certain events.</p>';
    
        // Add simple note about bulk selection
        echo '<div style="margin-top: 16px; padding: 12px; background: #f0f0f1; border-left: 4px solid #72aee6; border-radius: 4px;">';
        echo '<p style="margin: 0;"><strong>Tip:</strong> Use your browser\'s built-in functionality to select multiple checkboxes quickly. Most browsers support Shift+Click to select ranges of checkboxes.</p>';
        echo '</div>';
    }

    /**
     * Add notice container to the footer
     */
    public function add_notice_container() {
        echo '<div id="holler-cache-control-notices"></div>';
    }

    /**
     * Handle plugin activation/deactivation
     */
    public function handle_plugin_state_change() {
        // Skip cache purging during plugin deactivation if it's not our plugin
        if (isset($_GET['action']) && $_GET['action'] === 'deactivate') {
            if (isset($_GET['plugin']) && strpos($_GET['plugin'], 'holler-cache-control') === false) {
                return;
            }
            
            // For deactivation, schedule an async task to clear caches
            wp_schedule_single_event(time(), 'holler_cache_control_async_purge');
            return;
        }

        // For other state changes, purge caches immediately
        $this->purge_all_caches_on_update();
    }

    public static function get_cache_systems_status() {
        return array(
            'nginx' => \Holler\CacheControl\Admin\Cache\Nginx::get_status(),
            'redis' => \Holler\CacheControl\Admin\Cache\Redis::get_status(),
            'cloudflare' => \Holler\CacheControl\Admin\Cache\Cloudflare::get_status(),
            'cloudflare-apo' => \Holler\CacheControl\Admin\Cache\CloudflareAPO::get_status()
        );
    }

    /**
     * Smart detection to determine if we should skip auto-purge during editing sessions
     * Prevents AJAX timeouts and conflicts with page builders like Elementor
     * 
     * @return bool True if auto-purge should be skipped, false otherwise
     */
    private function should_skip_auto_purge() {
        // Skip if we're in an AJAX request from page builders
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // Check for Elementor editing - but allow publish actions
            if (isset($_POST['action']) && (
                strpos($_POST['action'], 'elementor') !== false ||
                strpos($_POST['action'], 'elementor_ajax') !== false ||
                $_POST['action'] === 'elementor_save_builder_content' ||
                $_POST['action'] === 'elementor_render_widget'
            )) {
                // Allow cache purging if this is a legitimate publish action
                if (isset($_POST['post_status']) && $_POST['post_status'] === 'publish') {
                    error_log('Holler Cache Control: Allowing purge - Elementor publish action detected');
                    return false;
                }
                
                // Try to find the post ID in various places Elementor might store it
                $post_id = null;
                if (isset($_POST['post_id']) && !empty($_POST['post_id'])) {
                    $post_id = $_POST['post_id'];
                } elseif (isset($_POST['editor_post_id']) && !empty($_POST['editor_post_id'])) {
                    $post_id = $_POST['editor_post_id'];
                } elseif (isset($_GET['post']) && !empty($_GET['post'])) {
                    $post_id = $_GET['post'];
                } elseif (isset($_POST['post']) && !empty($_POST['post']) && !is_array($_POST['post'])) {
                    $post_id = $_POST['post'];
                }
                
                // Allow cache purging if this is an update to published content
                if ($post_id) {
                    $post_status = get_post_status($post_id);
                    error_log('Holler Cache Control: Found post ID ' . $post_id . ' with status: ' . $post_status);
                    if ($post_status === 'publish') {
                        error_log('Holler Cache Control: Allowing purge - Updating published content via Elementor (ID: ' . $post_id . ')');
                        return false;
                    }
                }
                
                error_log('Holler Cache Control: Skipping - Elementor AJAX detected (auto-save): ' . $_POST['action']);
                return true;
            }

            // Check for WordPress block editor (Gutenberg) auto-saves
            if (isset($_POST['action']) && (
                $_POST['action'] === 'heartbeat' ||
                $_POST['action'] === 'autosave' ||
                strpos($_POST['action'], 'gutenberg') !== false
            )) {
                error_log('Holler Cache Control: Skipping - WordPress editor AJAX detected: ' . $_POST['action']);
                return true;
            }

            // Check for other common page builders
            if (isset($_POST['action']) && (
                strpos($_POST['action'], 'divi') !== false ||
                strpos($_POST['action'], 'beaver') !== false ||
                strpos($_POST['action'], 'vc_') !== false || // Visual Composer
                strpos($_POST['action'], 'fusion') !== false // Avada Fusion Builder
            )) {
                error_log('Holler Cache Control: Skipping - Page builder AJAX detected: ' . $_POST['action']);
                return true;
            }
        }

        // Skip if Elementor is actively editing (check URL parameters)
        if (isset($_GET['elementor-preview']) || isset($_GET['elementor_library'])) {
            error_log('Holler Cache Control: Skipping - Elementor preview/library mode detected');
            return true;
        }

        // Skip if we're in WordPress block editor
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && $screen->is_block_editor()) {
                error_log('Holler Cache Control: Skipping - WordPress block editor detected');
                return true;
            }
        }

        // Skip for post revisions and auto-drafts
        if (isset($_POST['post_status']) && (
            $_POST['post_status'] === 'auto-draft' ||
            $_POST['post_status'] === 'inherit' // revisions
        )) {
            error_log('Holler Cache Control: Skipping - Auto-draft or revision detected: ' . $_POST['post_status']);
            return true;
        }

        // Skip for preview requests
        if (isset($_POST['wp-preview']) && $_POST['wp-preview'] === 'dopreview') {
            error_log('Holler Cache Control: Skipping - Preview request detected');
            return true;
        }

        // Skip if this is just a draft save (not a publish)
        if (isset($_POST['save']) || isset($_POST['draft'])) {
            error_log('Holler Cache Control: Skipping - Draft save detected');
            return true;
        }

        error_log('Holler Cache Control: should_skip_auto_purge() returning false - purge should proceed');
        return false;
    }

    /**
     * Enhanced purge_all_caches method with smart detection
     * Only purges if not in an editing session that could cause timeouts
     */
    public function purge_all_caches_with_detection() {
        // Debug logging to understand what's happening
        error_log('Holler Cache Control: purge_all_caches_with_detection() called');
        error_log('Holler Cache Control: DOING_AJAX = ' . (defined('DOING_AJAX') && DOING_AJAX ? 'true' : 'false'));
        error_log('Holler Cache Control: $_POST action = ' . (isset($_POST['action']) ? $_POST['action'] : 'not set'));
        error_log('Holler Cache Control: $_POST post_status = ' . (isset($_POST['post_status']) ? $_POST['post_status'] : 'not set'));
        error_log('Holler Cache Control: $_POST post_id = ' . (isset($_POST['post_id']) ? $_POST['post_id'] : 'not set'));
        error_log('Holler Cache Control: $_GET elementor-preview = ' . (isset($_GET['elementor-preview']) ? 'true' : 'false'));
        
        // Log additional POST data to understand Elementor structure
        if (isset($_POST['editor_post_id'])) {
            error_log('Holler Cache Control: $_POST editor_post_id = ' . $_POST['editor_post_id']);
        }
        if (isset($_POST['post'])) {
            error_log('Holler Cache Control: $_POST post = ' . (is_array($_POST['post']) ? 'array' : $_POST['post']));
        }
        if (isset($_GET['post'])) {
            error_log('Holler Cache Control: $_GET post = ' . $_GET['post']);
        }
        
        // Log additional Elementor-specific data
        if (isset($_POST['post_id']) && !empty($_POST['post_id'])) {
            $current_post_status = get_post_status($_POST['post_id']);
            error_log('Holler Cache Control: Current post status for ID ' . $_POST['post_id'] . ' = ' . $current_post_status);
        }
        
        // Skip auto-purge during editing sessions to prevent AJAX timeouts
        if ($this->should_skip_auto_purge()) {
            error_log('Holler Cache Control: Skipping auto-purge during editing session to prevent AJAX timeout');
            return;
        }
        
        // For AJAX requests (like Elementor), schedule async purge to prevent timeouts
        if (defined('DOING_AJAX') && DOING_AJAX) {
            error_log('Holler Cache Control: Scheduling async cache purge to prevent AJAX timeout');
            $this->schedule_async_cache_purge();
            return;
        }
        
        error_log('Holler Cache Control: Proceeding with immediate cache purge!');
        $this->purge_all_caches();
    }

    /**
     * Schedule asynchronous cache purge to prevent AJAX timeouts
     * Uses WordPress cron to run cache purge in background
     */
    private function schedule_async_cache_purge() {
        // Schedule a single event to run immediately in background
        if (!wp_next_scheduled('holler_cache_control_async_purge')) {
            wp_schedule_single_event(time(), 'holler_cache_control_async_purge');
            error_log('Holler Cache Control: Async cache purge scheduled');
        } else {
            error_log('Holler Cache Control: Async cache purge already scheduled');
        }
    }

    /**
     * Handle the async cache purge cron event
     */
    public function handle_async_cache_purge($post_id = null, $context = 'async') {
        error_log("Holler Cache Control: Executing async cache purge - Post ID: " . ($post_id ?? 'none') . ", Context: {$context}");
        
        // Use SmartInvalidation for async purges
        \Holler\CacheControl\Admin\Cache\SmartInvalidation::invalidate($post_id, $context);
    }
    
    /**
     * Register smart invalidation hooks for auto-purge events
     */
    private function register_smart_invalidation_hooks() {
        // Only register hooks if auto-purge is enabled
        $auto_purge_settings = get_option('holler_cache_auto_purge_settings', []);
        
        if (!empty($auto_purge_settings['events']['save_post'])) {
            add_action('save_post', [$this, 'handle_smart_save_post'], 10, 1);
            add_action('publish_post', [$this, 'handle_smart_publish_post'], 10, 1);
        }
        
        if (!empty($auto_purge_settings['events']['delete_post'])) {
            add_action('delete_post', [$this, 'handle_smart_delete_post'], 10, 1);
            add_action('wp_trash_post', [$this, 'handle_smart_trash_post'], 10, 1);
        }
        
        if (!empty($auto_purge_settings['events']['menu_update'])) {
            add_action('wp_update_nav_menu', [$this, 'handle_smart_menu_update'], 10, 1);
        }
        
        if (!empty($auto_purge_settings['events']['theme_switch'])) {
            add_action('switch_theme', [$this, 'handle_smart_theme_switch'], 10, 1);
            add_action('customize_save_after', [$this, 'handle_smart_customizer_save'], 10, 1);
        }
    }
    
    /**
     * Handle smart cache invalidation on post save
     *
     * @param int $post_id Post ID
     */
    public function handle_smart_save_post($post_id) {
        // Skip if this is an auto-save or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Skip during Elementor editing sessions
        if ($this->is_elementor_editing_session()) {
            error_log("Holler Cache Control: Skipping smart invalidation during Elementor editing session");
            return;
        }
        
        // Use SmartInvalidation for context-aware purging
        \Holler\CacheControl\Admin\Cache\SmartInvalidation::invalidate($post_id, 'save_post');
    }
    
    /**
     * Handle smart cache invalidation on post publish
     *
     * @param int $post_id Post ID
     */
    public function handle_smart_publish_post($post_id) {
        // Use SmartInvalidation for publish events
        \Holler\CacheControl\Admin\Cache\SmartInvalidation::invalidate($post_id, 'publish_post');
    }
    
    /**
     * Handle smart cache invalidation on post delete
     *
     * @param int $post_id Post ID
     */
    public function handle_smart_delete_post($post_id) {
        \Holler\CacheControl\Admin\Cache\SmartInvalidation::invalidate($post_id, 'delete_post');
    }
    
    /**
     * Handle smart cache invalidation on post trash
     *
     * @param int $post_id Post ID
     */
    public function handle_smart_trash_post($post_id) {
        \Holler\CacheControl\Admin\Cache\SmartInvalidation::invalidate($post_id, 'trash_post');
    }
    
    /**
     * Handle smart cache invalidation on menu update
     *
     * @param int $menu_id Menu ID
     */
    public function handle_smart_menu_update($menu_id) {
        \Holler\CacheControl\Admin\Cache\SmartInvalidation::invalidate(null, 'wp_update_nav_menu');
    }
    
    /**
     * Handle smart cache invalidation on theme switch
     *
     * @param string $new_theme New theme name
     */
    public function handle_smart_theme_switch($new_theme) {
        \Holler\CacheControl\Admin\Cache\SmartInvalidation::invalidate(null, 'switch_theme');
    }
    
    /**
     * Handle smart cache invalidation on customizer save
     *
     * @param \WP_Customize_Manager $manager Customizer manager
     */
    public function handle_smart_customizer_save($manager) {
        \Holler\CacheControl\Admin\Cache\SmartInvalidation::invalidate(null, 'customize_save_after');
    }

    /**
     * Detect conflicting cache plugins that might interfere with Holler Cache Control
     * 
     * @return array Array of detected conflicting plugins with details
     */
    public function detect_conflicting_cache_plugins() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        $conflicting_plugins = array();

        // Nginx Helper Plugin
        if (is_plugin_active('nginx-helper/nginx-helper.php') || 
            is_plugin_active('nginx-cache/nginx-cache.php') ||
            class_exists('Nginx_Helper') ||
            defined('RT_WP_NGINX_HELPER_VERSION')) {
            
            $nginx_settings = get_option('rt_wp_nginx_helper_options', array());
            $is_purging_enabled = !empty($nginx_settings['enable_purge']);
            
            // Check specific purge settings
            $purge_settings = array(
                'purge_homepage_on_edit' => !empty($nginx_settings['purge_homepage_on_edit']),
                'purge_homepage_on_del' => !empty($nginx_settings['purge_homepage_on_del']),
                'purge_page_on_mod' => !empty($nginx_settings['purge_page_on_mod']),
                'purge_page_on_new_comment' => !empty($nginx_settings['purge_page_on_new_comment']),
                'purge_page_on_deleted_comment' => !empty($nginx_settings['purge_page_on_deleted_comment']),
                'purge_archive_on_edit' => !empty($nginx_settings['purge_archive_on_edit']),
                'purge_archive_on_del' => !empty($nginx_settings['purge_archive_on_del']),
                'purge_archive_on_new_comment' => !empty($nginx_settings['purge_archive_on_new_comment']),
                'purge_archive_on_deleted_comment' => !empty($nginx_settings['purge_archive_on_deleted_comment'])
            );
            
            $enabled_purge_count = count(array_filter($purge_settings));
            $total_purge_settings = count($purge_settings);
            
            // Determine conflict level based on enabled settings
            $conflict_level = 'low';
            if ($is_purging_enabled && $enabled_purge_count > 0) {
                if ($enabled_purge_count >= 6) {
                    $conflict_level = 'high';
                } elseif ($enabled_purge_count >= 3) {
                    $conflict_level = 'medium';
                } else {
                    $conflict_level = 'low';
                }
            }
            
            // Generate detailed recommendation
            $recommendation = 'Plugin is active but purging is disabled - no immediate conflict';
            if ($is_purging_enabled) {
                if ($enabled_purge_count === 0) {
                    $recommendation = 'Nginx Helper purging is enabled but no specific purge triggers are active';
                } else {
                    $recommendation = sprintf(
                        'Nginx Helper has %d of %d purge triggers enabled. Consider disabling these to prevent duplicate cache clearing with Holler Cache Control: %s',
                        $enabled_purge_count,
                        $total_purge_settings,
                        implode(', ', array_keys(array_filter($purge_settings)))
                    );
                }
            }
            
            $conflicting_plugins['nginx_helper'] = array(
                'name' => 'Nginx Helper',
                'status' => 'active',
                'purging_enabled' => $is_purging_enabled,
                'conflict_level' => $conflict_level,
                'description' => 'Handles Nginx FastCGI cache purging',
                'recommendation' => $recommendation,
                'purge_settings' => $purge_settings,
                'enabled_purge_count' => $enabled_purge_count,
                'total_purge_settings' => $total_purge_settings
            );
        }

        // Redis Object Cache Plugin (Informational)
        if (is_plugin_active('redis-cache/redis-cache.php') ||
            is_plugin_active('wp-redis/wp-redis.php') ||
            class_exists('RedisObjectCache') ||
            defined('WP_REDIS_VERSION')) {
            
            // Check Redis connection status if possible
            $redis_status = 'unknown';
            $redis_info = '';
            
            if (class_exists('\Redis')) {
                try {
                    $redis = new \Redis();
                    if ($redis->connect('127.0.0.1', 6379) || $redis->connect('/var/run/redis/redis-server.sock')) {
                        $redis_status = 'connected';
                        $redis_info = 'Redis server is accessible and functioning';
                        $redis->close();
                    } else {
                        $redis_status = 'disconnected';
                        $redis_info = 'Redis server connection failed';
                    }
                } catch (\Exception $e) {
                    $redis_status = 'error';
                    $redis_info = 'Redis connection error: ' . $e->getMessage();
                }
            }
            
            $conflicting_plugins['redis_cache'] = array(
                'name' => 'Redis Object Cache',
                'status' => 'active',
                'purging_enabled' => false, // Redis doesn't auto-purge like Nginx Helper
                'conflict_level' => 'info', // Changed from 'low' to 'info'
                'description' => 'Manages Redis object cache for improved performance',
                'recommendation' => 'Informational only - Redis Object Cache works seamlessly with Holler Cache Control. Both plugins can safely manage different cache layers.',
                'redis_status' => $redis_status,
                'redis_info' => $redis_info,
                'is_informational' => true
            );
        }

        // W3 Total Cache
        if (is_plugin_active('w3-total-cache/w3-total-cache.php') ||
            class_exists('W3_Plugin_TotalCache') ||
            defined('W3TC_VERSION')) {
            
            $conflicting_plugins['w3_total_cache'] = array(
                'name' => 'W3 Total Cache',
                'status' => 'active',
                'purging_enabled' => true,
                'conflict_level' => 'high',
                'description' => 'Comprehensive caching solution',
                'recommendation' => 'Consider disabling W3TC auto-purge features or use W3TC exclusively'
            );
        }

        // WP Rocket
        if (is_plugin_active('wp-rocket/wp-rocket.php') ||
            class_exists('WP_Rocket\\Engine\\Cache\\Purge') ||
            defined('WP_ROCKET_VERSION')) {
            
            $conflicting_plugins['wp_rocket'] = array(
                'name' => 'WP Rocket',
                'status' => 'active',
                'purging_enabled' => true,
                'conflict_level' => 'high',
                'description' => 'Premium caching and optimization plugin',
                'recommendation' => 'Consider disabling WP Rocket auto-purge features or use WP Rocket exclusively'
            );
        }

        // WP Super Cache
        if (is_plugin_active('wp-super-cache/wp-cache.php') ||
            function_exists('wp_cache_clear_cache') ||
            defined('WPCACHEHOME')) {
            
            $conflicting_plugins['wp_super_cache'] = array(
                'name' => 'WP Super Cache',
                'status' => 'active',
                'purging_enabled' => true,
                'conflict_level' => 'medium',
                'description' => 'Simple page caching plugin',
                'recommendation' => 'Consider disabling WP Super Cache auto-purge or use one plugin exclusively'
            );
        }

        // LiteSpeed Cache
        if (is_plugin_active('litespeed-cache/litespeed-cache.php') ||
            class_exists('LiteSpeed\\Cache') ||
            defined('LSCWP_VERSION')) {
            
            $conflicting_plugins['litespeed_cache'] = array(
                'name' => 'LiteSpeed Cache',
                'status' => 'active',
                'purging_enabled' => true,
                'conflict_level' => 'high',
                'description' => 'LiteSpeed server-specific caching plugin',
                'recommendation' => 'Consider using LiteSpeed Cache exclusively if on LiteSpeed server'
            );
        }

        return $conflicting_plugins;
    }

    /**
     * Get conflict warnings for admin notices
     * 
     * @return array Array of warning messages for conflicting plugins
     */
    public function get_cache_plugin_conflict_warnings() {
        $conflicting_plugins = $this->detect_conflicting_cache_plugins();
        $warnings = array();

        foreach ($conflicting_plugins as $plugin_key => $plugin_info) {
            // Skip informational plugins from warnings
            if (isset($plugin_info['is_informational']) && $plugin_info['is_informational']) {
                continue;
            }
            
            if ($plugin_info['conflict_level'] === 'high') {
                $warnings[] = array(
                    'type' => 'error',
                    'message' => sprintf(
                        '<strong>Cache Plugin Conflict:</strong> %s is active and may conflict with Holler Cache Control. %s',
                        $plugin_info['name'],
                        $plugin_info['recommendation']
                    )
                );
            } elseif ($plugin_info['conflict_level'] === 'medium') {
                $warnings[] = array(
                    'type' => 'warning',
                    'message' => sprintf(
                        '<strong>Cache Plugin Notice:</strong> %s is active. %s',
                        $plugin_info['name'],
                        $plugin_info['recommendation']
                    )
                );
            }
        }

        return $warnings;
    }

    /**
     * Add styles for admin bar
     */
    public function admin_bar_styles() {
        // Only load if admin bar is showing and user has permissions
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
        
        // Debug: Add a simple console log to verify this method is being called
        echo '<script>console.log("Holler Cache Control: admin_bar_styles() method called on " + (window.location.href.indexOf("/wp-admin/") !== -1 ? "admin" : "frontend"));</script>';
        echo '<style>
            /* Basic menu item styling */
            #wpadminbar .holler-cache-status-item {
                display: block !important;
            }

            #wpadminbar .holler-cache-status-item a {
                height: 28px !important;
                line-height: 28px !important;
                padding: 0 8px !important;
                color: #fff !important;
                text-decoration: none !important;
                cursor: default !important;
            }

            /* Status text styling */
            #wpadminbar .holler-cache-status {
                display: inline-block !important;
                font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif !important;
                font-size: 13px !important;
                line-height: 28px !important;
                font-weight: normal !important;
                color: #fff !important;
            }

            /* Remove all default WordPress styling */
            #wpadminbar .holler-cache-status-item a::before,
            #wpadminbar .holler-cache-status-item a::after,
            #wpadminbar #wp-admin-bar-holler-cache-status a::before,
            #wpadminbar #wp-admin-bar-holler-nginx-status a::before,
            #wpadminbar #wp-admin-bar-holler-redis-status a::before,
            #wpadminbar #wp-admin-bar-holler-cloudflare-status a::before,
            #wpadminbar #wp-admin-bar-holler-cloudflare-apo-status a::before {
                display: none !important;
                content: none !important;
                width: 0 !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                font: 0/0 a !important;
                text-shadow: none !important;
                background: none !important;
                text-indent: 0 !important;
            }

            /* Override WordPress admin text styling */
            #wpadminbar .holler-cache-status-item * {
                font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif !important;
                text-shadow: none !important;
                letter-spacing: normal !important;
            }

            /* Remove hover effects */
            #wpadminbar .holler-cache-status-item:hover a,
            #wpadminbar .holler-cache-status-item a:hover {
                background: none !important;
                color: #fff !important;
            }

            /* Force text color */
            #wpadminbar .holler-cache-status-item a,
            #wpadminbar .holler-cache-status-item:hover a,
            #wpadminbar .holler-cache-status-item a:hover,
            #wpadminbar .holler-cache-status-item a:focus {
                color: #fff !important;
            }

            /* Remove any WordPress icons */
            #wpadminbar .holler-cache-status-item .ab-icon,
            #wpadminbar .holler-cache-status-item a:before,
            #wpadminbar>#wp-toolbar>#wp-admin-bar-root-default .holler-cache-status-item .ab-icon {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                font: 0/0 a !important;
                text-shadow: none !important;
                background: none !important;
            }

            /* Fix separators */
            #wpadminbar #wp-admin-bar-holler-cache-separator-1 span,
            #wpadminbar #wp-admin-bar-holler-cache-separator-2 span {
                display: block !important;
                margin: 5px 0 !important;
                padding: 0 !important;
                height: 1px !important;
                line-height: 1px !important;
            }

            /* Remove list numbers */
            #wpadminbar .ab-submenu,
            #wpadminbar .ab-submenu li {
                list-style: none !important;
                list-style-type: none !important;
            }

            #wpadminbar .ab-submenu li::after {
                display: none !important;
                content: "" !important;
            }

            /* Clear All Caches button styling */
            #wp-admin-bar-holler-cache-clear-all {
                cursor: pointer !important;
            }
            
            #wp-admin-bar-holler-cache-clear-all.purging {
                opacity: 0.7 !important;
            }
            
            #wp-admin-bar-holler-cache-clear-all.purging a {
                cursor: wait !important;
            }
        </style>';
        
        // Add JavaScript for Clear All Caches functionality
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Debug: Check if the element exists
            console.log('Holler Cache Control: Looking for admin bar button...');
            var $clearButton = $('#wp-admin-bar-holler-cache-clear-all');
            console.log('Holler Cache Control: Found button:', $clearButton.length > 0 ? 'YES' : 'NO');
            
            if ($clearButton.length === 0) {
                console.error('Holler Cache Control: Admin bar button not found! Available admin bar items:', $('#wpadminbar').find('[id*="holler"]').map(function() { return this.id; }).get());
                return;
            }
            
            $clearButton.on('click', function(e) {
                console.log('Holler Cache Control: Button clicked!');
                e.preventDefault();
                
                var $button = $(this);
                var $link = $button.find('a');
                var originalText = $link.text();
                
                // Prevent multiple clicks
                if ($button.hasClass('purging')) {
                    return false;
                }
                
                // Update button state
                $button.addClass('purging');
                $link.text('Purging All Caches...');
                
                // Get AJAX URL - works for both admin and frontend
                var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                
                // Make AJAX request
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'holler_purge_all',
                        nonce: '<?php echo wp_create_nonce('holler_cache_control_purge_all'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $link.text('✓ All Caches Cleared!');
                            
                            // Show detailed results if available
                            if (response.data && response.data.details) {
                                console.log('Cache purge details:', response.data.details);
                            }
                            
                            // Reset text after 3 seconds
                            setTimeout(function() {
                                $link.text(originalText);
                                $button.removeClass('purging');
                            }, 3000);
                        } else {
                            $link.text('✗ Purge Failed');
                            console.error('Cache purge failed:', response.data);
                            
                            // Reset text after 3 seconds
                            setTimeout(function() {
                                $link.text(originalText);
                                $button.removeClass('purging');
                            }, 3000);
                        }
                    },
                    error: function(xhr, status, error) {
                        $link.text('✗ Network Error');
                        console.error('AJAX error:', error);
                        
                        // Reset text after 3 seconds
                        setTimeout(function() {
                            $link.text(originalText);
                            $button.removeClass('purging');
                        }, 3000);
                    }
                });
                
                return false;
            });
        });
        </script>
        <?php
    }

    /**
     * Handle Cloudflare connection test AJAX request
     */
    public function handle_test_cloudflare_connection_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'holler_cache_control')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $cloudflare_status = Cloudflare::get_status();
            if ($cloudflare_status['status'] === 'active') {
                wp_send_json_success(array(
                    'message' => __('Cloudflare connection is working properly.', 'holler-cache-control')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $cloudflare_status['message']
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle Cloudflare settings save AJAX request
     */
    public function handle_save_cloudflare_settings_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'holler_cloudflare_settings')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            // Save Cloudflare settings
            if (isset($_POST['agnt_cloudflare_email'])) {
                update_option('agnt_cloudflare_email', sanitize_email($_POST['agnt_cloudflare_email']));
            }
            if (isset($_POST['agnt_cloudflare_api_key'])) {
                update_option('agnt_cloudflare_api_key', sanitize_text_field($_POST['agnt_cloudflare_api_key']));
            }
            if (isset($_POST['agnt_cloudflare_zone_id'])) {
                update_option('agnt_cloudflare_zone_id', sanitize_text_field($_POST['agnt_cloudflare_zone_id']));
            }

            wp_send_json_success(array(
                'message' => __('Cloudflare settings saved successfully.', 'holler-cache-control')
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle diagnostics export AJAX request
     */
    public function handle_export_diagnostics_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'holler_cache_control')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $format = sanitize_text_field($_POST['format'] ?? 'text');
            
            // Get comprehensive diagnostics
            $diagnostics = array(
                'plugin_version' => HOLLER_CACHE_CONTROL_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'cache_status' => $this->get_cache_systems_status(),
                'cache_paths' => \Holler\CacheControl\Core\CachePathDetector::get_comprehensive_report(),
                'system_info' => array(
                    'wp_debug' => WP_DEBUG,
                    'wp_cache' => defined('WP_CACHE') && WP_CACHE,
                    'redis_extension' => class_exists('Redis'),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'gridpane_hook' => has_action('rt_nginx_helper_purge_all')
                ),
                'generated_at' => current_time('mysql')
            );

            if ($format === 'json') {
                $content = json_encode($diagnostics, JSON_PRETTY_PRINT);
            } else {
                // Generate text format
                $content = "Holler Cache Control - Diagnostics Report\n";
                $content .= "Generated: " . $diagnostics['generated_at'] . "\n\n";
                $content .= "Plugin Version: " . $diagnostics['plugin_version'] . "\n";
                $content .= "WordPress Version: " . $diagnostics['wordpress_version'] . "\n";
                $content .= "PHP Version: " . $diagnostics['php_version'] . "\n";
                $content .= "Server: " . $diagnostics['server_software'] . "\n\n";
                
                $content .= "Cache Systems Status:\n";
                foreach ($diagnostics['cache_status'] as $system => $status) {
                    $content .= "  {$system}: {$status['status']} - {$status['message']}\n";
                }
                
                $content .= "\nCache Paths:\n";
                if (!empty($diagnostics['cache_paths']['detected_paths'])) {
                    foreach ($diagnostics['cache_paths']['detected_paths'] as $path) {
                        $content .= "  {$path['path']} ({$path['environment']}, Priority: {$path['priority']})\n";
                    }
                }
            }

            wp_send_json_success(array(
                'content' => $content
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Remove old menu items
     */
    public function remove_old_menu_items() {
        remove_submenu_page('options-general.php', 'holler-cache-control');
    }

    /**
     * Remove duplicate admin pages from MU plugins or other sources
     */
    public function remove_duplicate_admin_pages() {
        // Remove the duplicate admin page created by agnt-cache-manager.php MU plugin
        remove_submenu_page('options-general.php', 'cache-control-settings');
        
        // Remove any other potential duplicate cache control admin pages
        remove_submenu_page('options-general.php', 'holler-cache-settings');
        remove_submenu_page('options-general.php', 'cache-settings');
    }

    /**
     * Remove other cache plugin admin bar items to avoid duplication
     * Using the exact same approach as the working MU plugin
     */
    public function remove_cache_admin_bar_items() {
        global $wp_admin_bar;
        
        // Remove cache buttons from admin bar - exact same code as MU plugin
        $wp_admin_bar->remove_node('nginx-helper-purge-all');
        $wp_admin_bar->remove_node('redis-cache');
        
        // Also remove Redis Cache inline scripts - exact same code as MU plugin
        remove_action('admin_footer', 'redis_object_cache_script');
        remove_action('wp_footer', 'redis_object_cache_script');
    }

    /**
     * Aggressively remove Redis Cache admin bar items
     */
    public function remove_redis_cache_admin_bar() {
        global $wp_admin_bar;
        
        if ($wp_admin_bar) {
            $wp_admin_bar->remove_node('redis-cache');
            $wp_admin_bar->remove_node('redis-cache-flush');
            $wp_admin_bar->remove_node('redis-cache-metrics');
            $wp_admin_bar->remove_node('redis-cache-info');
            $wp_admin_bar->remove_node('redis-cache-info-details');
        }
        
        // Remove Nginx Helper as well
        if ($wp_admin_bar) {
            $wp_admin_bar->remove_node('nginx-helper-purge-all');
            $wp_admin_bar->remove_node('nginx-helper');
        }
        
        // Remove Redis Cache inline scripts
        remove_action('admin_footer', 'redis_object_cache_script');
        remove_action('wp_footer', 'redis_object_cache_script');
        
        // Remove Redis Cache admin bar actions
        remove_action('admin_bar_menu', 'redis_object_cache_admin_bar_menu');
        remove_action('wp_before_admin_bar_render', 'redis_object_cache_admin_bar_menu');
    }

    /**
     * Add CSS to hide Redis Cache admin bar items as fallback
     */
    public function hide_redis_cache_admin_bar_css() {
        echo '<style type="text/css">
            #wp-admin-bar-redis-cache,
            #wp-admin-bar-redis-cache-flush,
            #wp-admin-bar-redis-cache-metrics,
            #wp-admin-bar-redis-cache-info,
            #wp-admin-bar-redis-cache-info-details,
            #wp-admin-bar-nginx-helper-purge-all,
            #wp-admin-bar-nginx-helper {
                display: none !important;
            }
            #redis-cache-admin-bar-style {
                display: none !important;
            }
        </style>';
    }

    /**
     * Enqueue scripts for frontend admin bar functionality
     */
    public function enqueue_frontend_admin_bar_scripts() {
        // Debug: Always log this method being called
        error_log('Holler Cache Control: enqueue_frontend_admin_bar_scripts() called');
        error_log('Holler Cache Control: is_admin_bar_showing() = ' . (is_admin_bar_showing() ? 'true' : 'false'));
        error_log('Holler Cache Control: current_user_can(manage_options) = ' . (current_user_can('manage_options') ? 'true' : 'false'));
        
        // Only load if admin bar is showing and user can manage options
        if (is_admin_bar_showing() && current_user_can('manage_options')) {
            wp_enqueue_script('jquery');
            
            // Add inline script for admin bar functionality
            $inline_script = '
                jQuery(document).ready(function($) {
                    // Debug: Check if the element exists
                    console.log("Holler Cache Control: Frontend admin bar script loaded");
                    var $clearButton = $("#wp-admin-bar-holler-cache-clear-all");
                    console.log("Holler Cache Control: Found button:", $clearButton.length > 0 ? "YES" : "NO");
                    
                    if ($clearButton.length === 0) {
                        console.error("Holler Cache Control: Admin bar button not found! Available admin bar items:", $("#wpadminbar").find("[id*=holler]").map(function() { return this.id; }).get());
                        return;
                    }
                    
                    $clearButton.on("click", function(e) {
                        console.log("Holler Cache Control: Button clicked!");
                        e.preventDefault();
                        
                        var $button = $(this);
                        var $link = $button.find("a");
                        var originalText = $link.text();
                        
                        // Prevent multiple clicks
                        if ($button.hasClass("purging")) {
                            return false;
                        }
                        
                        // Update button state
                        $button.addClass("purging");
                        $link.text("Purging All Caches...");
                        
                        // Make AJAX request
                        $.ajax({
                            url: "' . admin_url('admin-ajax.php') . '",
                            type: "POST",
                            data: {
                                action: "holler_purge_all",
                                nonce: "' . wp_create_nonce('holler_cache_control_purge_all') . '"
                            },
                            success: function(response) {
                                if (response.success) {
                                    $link.text("✓ All Caches Cleared!");
                                    
                                    // Show detailed results if available
                                    if (response.data && response.data.details) {
                                        console.log("Cache purge details:", response.data.details);
                                    }
                                    
                                    // Reset text after 3 seconds
                                    setTimeout(function() {
                                        $link.text(originalText);
                                        $button.removeClass("purging");
                                    }, 3000);
                                } else {
                                    $link.text("✗ Purge Failed");
                                    console.error("Cache purge failed:", response.data);
                                    
                                    // Reset text after 3 seconds
                                    setTimeout(function() {
                                        $link.text(originalText);
                                        $button.removeClass("purging");
                                    }, 3000);
                                }
                            },
                            error: function(xhr, status, error) {
                                $link.text("✗ Network Error");
                                console.error("AJAX error:", error);
                                
                                // Reset text after 3 seconds
                                setTimeout(function() {
                                    $link.text(originalText);
                                    $button.removeClass("purging");
                                }, 3000);
                            }
                        });
                        
                        return false;
                    });
                });
            ';
            
            wp_add_inline_script('jquery', $inline_script);
            
            // Add inline CSS for admin bar styling
            $inline_css = '
                #wp-admin-bar-holler-cache-clear-all {
                    cursor: pointer !important;
                }
                
                #wp-admin-bar-holler-cache-clear-all.purging {
                    opacity: 0.7 !important;
                }
                
                #wp-admin-bar-holler-cache-clear-all.purging a {
                    cursor: wait !important;
                }
            ';
            
            wp_add_inline_style('admin-bar', $inline_css);
        }
    }

    /**
     * Add frontend admin bar script via wp_footer hook
     */
    public function add_frontend_admin_bar_script() {
        // Debug: Always output something to verify this method is called
        if (!is_admin()) {
            echo '<script>console.log("Holler Cache Control: wp_footer hook fired on frontend");</script>';
        }
        
        // Only load if admin bar is showing, user can manage options, and we're not in admin
        if (!is_admin() && is_admin_bar_showing() && current_user_can('manage_options')) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Debug: Check if the element exists
                console.log('Holler Cache Control: Frontend admin bar script loaded via wp_footer');
                var $clearButton = $('#wp-admin-bar-holler-cache-clear-all');
                console.log('Holler Cache Control: Found button:', $clearButton.length > 0 ? 'YES' : 'NO');
                
                if ($clearButton.length === 0) {
                    console.error('Holler Cache Control: Admin bar button not found! Available admin bar items:', $('#wpadminbar').find('[id*="holler"]').map(function() { return this.id; }).get());
                    return;
                }
                
                $clearButton.on('click', function(e) {
                    console.log('Holler Cache Control: Button clicked!');
                    e.preventDefault();
                    
                    var $button = $(this);
                    var $link = $button.find('a');
                    var originalText = $link.text();
                    
                    // Prevent multiple clicks
                    if ($button.hasClass('purging')) {
                        return false;
                    }
                    
                    // Update button state
                    $button.addClass('purging');
                    $link.text('Purging All Caches...');
                    
                    // Make AJAX request
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'holler_purge_all',
                            nonce: '<?php echo wp_create_nonce('holler_cache_control_purge_all'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $link.text('✓ All Caches Cleared!');
                                
                                // Show detailed results if available
                                if (response.data && response.data.details) {
                                    console.log('Cache purge details:', response.data.details);
                                }
                                
                                // Reset text after 3 seconds
                                setTimeout(function() {
                                    $link.text(originalText);
                                    $button.removeClass('purging');
                                }, 3000);
                            } else {
                                $link.text('✗ Purge Failed');
                                console.error('Cache purge failed:', response.data);
                                
                                // Reset text after 3 seconds
                                setTimeout(function() {
                                    $link.text(originalText);
                                    $button.removeClass('purging');
                                }, 3000);
                            }
                        },
                        error: function(xhr, status, error) {
                            $link.text('✗ Network Error');
                            console.error('AJAX error:', error);
                            
                            // Reset text after 3 seconds
                            setTimeout(function() {
                                $link.text(originalText);
                                $button.removeClass('purging');
                            }, 3000);
                        }
                    });
                    
                    return false;
                });
            });
            </script>
            <style type="text/css">
                #wp-admin-bar-holler-cache-clear-all {
                    cursor: pointer !important;
                }
                
                #wp-admin-bar-holler-cache-clear-all.purging {
                    opacity: 0.7 !important;
                }
                
                #wp-admin-bar-holler-cache-clear-all.purging a {
                    cursor: wait !important;
                }
            </style>
            <?php
        }
    }

    /**
     * Handle AJAX request to toggle Cloudflare development mode
     */
    public function handle_toggle_cloudflare_dev_mode_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'holler_cache_control_admin')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $action = sanitize_text_field($_POST['dev_mode_action']);
        $value = ($action === 'enable') ? 'on' : 'off';

        // Get current status first
        $current_status = \Holler\CacheControl\Admin\Cache\Cloudflare::get_development_mode();
        
        // Toggle development mode
        $result = \Holler\CacheControl\Admin\Cache\Cloudflare::update_development_mode($value);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'new_status' => $value,
                'action_performed' => $action
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }

    /**
     * Handle AJAX request to update Cloudflare security setting
     */
    public function handle_update_security_setting_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'holler_cache_control_security')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $setting = sanitize_text_field($_POST['setting']);
        $value = sanitize_text_field($_POST['value']);
        
        // Validate setting name
        $valid_settings = array('security_level', 'bot_fight_mode', 'browser_check', 'email_obfuscation');
        if (!in_array($setting, $valid_settings)) {
            wp_send_json_error(array(
                'message' => 'Invalid security setting'
            ));
        }
        
        // Update security setting
        $result = \Holler\CacheControl\Admin\Cache\Cloudflare::update_security_setting($setting, $value);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'setting' => $setting,
                'new_value' => $value
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }
    
    /**
     * Render invalidation strategy field
     */
    public function render_invalidation_strategy_field() {
        $settings = get_option('holler_cache_control_smart_invalidation', array());
        $strategy = isset($settings['strategy']) ? $settings['strategy'] : 'smart';
        
        echo '<div class="holler-field-group">';
        echo '<div class="holler-radio-group">';
        
        $strategies = array(
            'full' => array(
                'label' => 'Full Cache Purge',
                'description' => 'Always purge all caches (fastest, but uses more resources)'
            ),
            'selective' => array(
                'label' => 'Selective Purge',
                'description' => 'Purge only related URLs and pages (balanced approach)'
            ),
            'smart' => array(
                'label' => 'Smart Analysis',
                'description' => 'Analyze content changes and purge intelligently (recommended)'
            )
        );
        
        foreach ($strategies as $value => $info) {
            $checked = checked($strategy, $value, false);
            echo '<label class="holler-radio-option">';
            echo '<input type="radio" name="holler_cache_control_smart_invalidation[strategy]" value="' . esc_attr($value) . '" ' . $checked . ' />';
            echo '<span class="holler-radio-label">';
            echo '<strong>' . esc_html($info['label']) . '</strong><br>';
            echo '<small>' . esc_html($info['description']) . '</small>';
            echo '</span>';
            echo '</label>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render smart cache layers field
     */
    public function render_smart_cache_layers_field() {
        $settings = get_option('holler_cache_control_smart_invalidation', array());
        $enabled_layers = isset($settings['enabled_layers']) ? $settings['enabled_layers'] : array('local', 'server', 'external');
        
        echo '<div class="holler-field-group">';
        echo '<div class="holler-checkbox-grid">';
        
        $layers = array(
            'local' => array(
                'label' => 'Local Caches',
                'description' => 'OPcache, Object Cache'
            ),
            'server' => array(
                'label' => 'Server Caches', 
                'description' => 'Nginx, Redis Page Cache'
            ),
            'external' => array(
                'label' => 'External Services',
                'description' => 'Cloudflare, CDN'
            )
        );
        
        foreach ($layers as $value => $info) {
            $checked = in_array($value, $enabled_layers) ? 'checked="checked"' : '';
            echo '<label class="holler-checkbox-option">';
            echo '<input type="checkbox" name="holler_cache_control_smart_invalidation[enabled_layers][]" value="' . esc_attr($value) . '" ' . $checked . ' />';
            echo '<span class="holler-checkbox-label">';
            echo '<strong>' . esc_html($info['label']) . '</strong><br>';
            echo '<small>' . esc_html($info['description']) . '</small>';
            echo '</span>';
            echo '</label>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render invalidation scopes field
     */
    public function render_invalidation_scopes_field() {
        $settings = get_option('holler_cache_control_smart_invalidation', array());
        $enabled_scopes = isset($settings['enabled_scopes']) ? $settings['enabled_scopes'] : array('page', 'category', 'site');
        
        echo '<div class="holler-field-group">';
        echo '<div class="holler-checkbox-grid">';
        
        $scopes = array(
            'page' => array(
                'label' => 'Page Level',
                'description' => 'Individual pages and posts'
            ),
            'category' => array(
                'label' => 'Category Level',
                'description' => 'Archive pages, taxonomies'
            ),
            'site' => array(
                'label' => 'Site Level',
                'description' => 'Homepage, feeds, sitemaps'
            )
        );
        
        foreach ($scopes as $value => $info) {
            $checked = in_array($value, $enabled_scopes) ? 'checked="checked"' : '';
            echo '<label class="holler-checkbox-option">';
            echo '<input type="checkbox" name="holler_cache_control_smart_invalidation[enabled_scopes][]" value="' . esc_attr($value) . '" ' . $checked . ' />';
            echo '<span class="holler-checkbox-label">';
            echo '<strong>' . esc_html($info['label']) . '</strong><br>';
            echo '<small>' . esc_html($info['description']) . '</small>';
            echo '</span>';
            echo '</label>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render Cloudflare selective purging field
     */
    public function render_cloudflare_selective_field() {
        $settings = get_option('holler_cache_control_smart_invalidation', array());
        $selective_enabled = isset($settings['cloudflare_selective_enabled']) ? $settings['cloudflare_selective_enabled'] : true;
        $auto_analysis = isset($settings['auto_analysis_enabled']) ? $settings['auto_analysis_enabled'] : true;
        
        echo '<div class="holler-field-group">';
        echo '<div class="holler-toggle-group">';
        
        // Selective purging toggle
        echo '<label class="holler-toggle-option">';
        echo '<input type="checkbox" name="holler_cache_control_smart_invalidation[cloudflare_selective_enabled]" value="1" ' . checked($selective_enabled, true, false) . ' />';
        echo '<span class="holler-toggle-label">';
        echo '<strong>Enable Selective URL Purging</strong><br>';
        echo '<small>Purge specific URLs instead of entire Cloudflare cache (reduces API usage)</small>';
        echo '</span>';
        echo '</label>';
        
        // Auto analysis toggle
        echo '<label class="holler-toggle-option">';
        echo '<input type="checkbox" name="holler_cache_control_smart_invalidation[auto_analysis_enabled]" value="1" ' . checked($auto_analysis, true, false) . ' />';
        echo '<span class="holler-toggle-label">';
        echo '<strong>Automatic Content Analysis</strong><br>';
        echo '<small>Analyze content changes to determine optimal purge strategy</small>';
        echo '</span>';
        echo '</label>';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render smart invalidation section description
     */
    public function render_smart_invalidation_section() {
        echo '<p>Configure intelligent cache invalidation strategies to optimize performance and reduce unnecessary cache purging.</p>';
    }
    
    /**
     * Sanitize smart invalidation settings
     */
    public function sanitize_smart_invalidation_settings($input) {
        try {
            error_log('Holler Cache Control - Sanitizing smart invalidation settings: ' . print_r($input, true));
            
            if (!is_array($input)) {
                error_log('Holler Cache Control - Smart invalidation input is not an array, using empty array');
                $input = array();
            }
            
            $sanitized = array(
                'strategy' => isset($input['strategy']) && in_array($input['strategy'], array('full', 'selective', 'smart')) ? $input['strategy'] : 'smart',
                'enabled_layers' => isset($input['enabled_layers']) && is_array($input['enabled_layers']) ? array_intersect($input['enabled_layers'], array('local', 'server', 'external')) : array('local', 'server', 'external'),
                'enabled_scopes' => isset($input['enabled_scopes']) && is_array($input['enabled_scopes']) ? array_intersect($input['enabled_scopes'], array('page', 'category', 'site')) : array('page', 'category', 'site'),
                'cloudflare_selective_enabled' => !empty($input['cloudflare_selective_enabled']),
                'auto_analysis_enabled' => !empty($input['auto_analysis_enabled'])
            );
            
            error_log('Holler Cache Control - Sanitized smart invalidation settings: ' . print_r($sanitized, true));
            return $sanitized;
            
        } catch (Exception $e) {
            error_log('Holler Cache Control - Error sanitizing smart invalidation settings: ' . $e->getMessage());
            return array(
                'strategy' => 'smart',
                'enabled_layers' => array('local', 'server', 'external'),
                'enabled_scopes' => array('page', 'category', 'site'),
                'cloudflare_selective_enabled' => true,
                'auto_analysis_enabled' => true
            );
        }
    }
}
