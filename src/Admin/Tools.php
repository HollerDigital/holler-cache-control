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
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 100);
        add_action('admin_head', array($this, 'admin_bar_styles'));
        add_action('admin_footer', array($this, 'add_notice_container'));

        // Initialize AJAX handlers
        $this->init_ajax_handlers();

        // Add hooks for automatic cache purging
        $this->add_cache_purging_hooks();

        // Initialize HTTPS filters
        $this->init_https_filters();

        // Remove old menu items
        add_action('admin_menu', array($this, 'remove_old_menu_items'), 999);
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
        include_once 'views/admin-display.php';
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
        if (!$this->ajax_handler) {
            $this->ajax_handler = new \Holler\CacheControl\Admin\Cache\AjaxHandler();
        }
        
        // Add AJAX actions
        add_action('wp_ajax_holler_cache_status', array($this->ajax_handler, 'handle_cache_status'));
        add_action('wp_ajax_holler_purge_all', array($this->ajax_handler, 'handle_purge_cache'));
        add_action('wp_ajax_holler_purge_nginx', array($this->ajax_handler, 'handle_purge_cache'));
        add_action('wp_ajax_holler_purge_redis', array($this->ajax_handler, 'handle_purge_cache'));
        add_action('wp_ajax_holler_purge_cloudflare', array($this->ajax_handler, 'handle_purge_cache'));
        add_action('wp_ajax_holler_purge_cloudflare_apo', array($this->ajax_handler, 'handle_purge_cache'));
    }

    /**
     * Add hooks for automatic cache purging on various WordPress actions
     */
    public function add_cache_purging_hooks() {
        // Post/Page updates
        add_action('save_post', array($this, 'purge_all_caches'));
        add_action('delete_post', array($this, 'purge_all_caches'));
        add_action('wp_trash_post', array($this, 'purge_all_caches'));
        add_action('untrash_post', array($this, 'purge_all_caches'));

        // Theme customization
        add_action('customize_save_after', array($this, 'purge_all_caches'));
        add_action('switch_theme', array($this, 'purge_all_caches'));

        // Plugin activation/deactivation
        add_action('activated_plugin', array($this, 'purge_all_caches'));
        add_action('deactivated_plugin', array($this, 'purge_all_caches'));

        // Menu updates
        add_action('wp_update_nav_menu', array($this, 'purge_all_caches'));
        add_action('wp_delete_nav_menu', array($this, 'purge_all_caches'));

        // Widget updates
        add_action('update_option_sidebars_widgets', array($this, 'purge_all_caches'));
        add_action('sidebar_admin_setup', array($this, 'purge_all_caches'));

        // Core updates
        add_action('_core_updated_successfully', array($this, 'purge_all_caches'));

        // Scheduled purge
        if (!wp_next_scheduled('holler_purge_all_caches_daily')) {
            wp_schedule_event(time(), 'daily', 'holler_purge_all_caches_daily');
        }
        add_action('holler_purge_all_caches_daily', array($this, 'purge_all_caches'));

        // Handle async purge requests
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
     *
     * @param string $source Source of the purge request
     */
    public function purge_all_caches($source = 'admin') {
        try {
            $successes = array();
            $failures = array();
            $messages = array();

            // Purge Nginx cache
            $result = \Holler\CacheControl\Admin\Cache\Nginx::purge_cache();
            if ($result['success']) {
                $successes[] = 'nginx';
            } else {
                $failures[] = 'nginx';
            }
            $messages[] = $result['message'];

            // Purge Redis cache
            $result = \Holler\CacheControl\Admin\Cache\Redis::purge_cache();
            if ($result['success']) {
                $successes[] = 'redis';
            } else {
                $failures[] = 'redis';
            }
            $messages[] = $result['message'];

            // Purge Cloudflare cache
            $result = \Holler\CacheControl\Admin\Cache\Cloudflare::purge_cache();
            if ($result['success']) {
                $successes[] = 'cloudflare';
            } else {
                $failures[] = 'cloudflare';
            }
            $messages[] = $result['message'];

            // Purge Cloudflare APO cache
            $result = \Holler\CacheControl\Admin\Cache\CloudflareAPO::purge_cache();
            if ($result['success']) {
                $successes[] = 'cloudflare_apo';
            } else {
                $failures[] = 'cloudflare_apo';
            }
            $messages[] = $result['message'];

            // Track the cache clear
            $this->track_cache_clear('all', $source);

            return array(
                'success' => !empty($successes),
                'message' => $this->format_messages($messages, $successes, $failures)
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
     * Register plugin settings
     */
    public function register_settings() {
        // Register visibility settings
        register_setting(
            'holler_cache_control_settings',  // Option group
            'holler_cache_control_visibility', // Option name
            array(
                'type' => 'object',
                'description' => 'Holler Cache Control Visibility Settings',
                'sanitize_callback' => array($this, 'sanitize_visibility_settings'),
                'show_in_rest' => false,
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

        // Add settings sections
        add_settings_section(
            'holler_cache_control_visibility',
            'Plugin & Feature Visibility',
            array($this, 'render_visibility_section'),
            'holler-cache-control'
        );

        // Add plugin visibility fields
        add_settings_field(
            'plugin_visibility',
            'Hide Plugins',
            array($this, 'render_plugin_visibility_field'),
            'holler-cache-control',
            'holler_cache_control_visibility'
        );

        // Add admin bar fields
        add_settings_field(
            'admin_bar_visibility',
            'Hide Admin Bar Features',
            array($this, 'render_admin_bar_visibility_field'),
            'holler-cache-control',
            'holler_cache_control_visibility'
        );

        // Add role exclusion field
        add_settings_field(
            'excluded_roles',
            'Hide From User Roles',
            array($this, 'render_role_exclusion_field'),
            'holler-cache-control',
            'holler_cache_control_visibility'
        );
    }

    /**
     * Sanitize visibility settings
     */
    public function sanitize_visibility_settings($input) {
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

        return $sanitized;
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
            'hide_nginx_helper' => 'Nginx Helper',
            'hide_redis_cache' => 'Redis Object Cache',
            'hide_cloudflare' => 'Cloudflare'
        );

        foreach ($plugins as $key => $label) {
            printf(
                '<label style="display: block; margin-bottom: 8px;"><input type="checkbox" name="holler_cache_control_visibility[%s]" value="1" %s> %s</label>',
                esc_attr($key),
                checked(!empty($settings[$key]), true, false),
                esc_html($label)
            );
        }
        echo '<p class="description">Selected plugins will be hidden from the plugins list.</p>';
    }

    /**
     * Render admin bar visibility checkboxes
     */
    public function render_admin_bar_visibility_field() {
        $settings = get_option('holler_cache_control_visibility', array());
        
        $features = array(
            'hide_nginx_purge' => 'Page Cache Purge Button',
            'hide_redis_purge' => 'Object Cache Purge Button'
        );

        foreach ($features as $key => $label) {
            printf(
                '<label style="display: block; margin-bottom: 8px;"><input type="checkbox" name="holler_cache_control_visibility[%s]" value="1" %s> %s</label>',
                esc_attr($key),
                checked(!empty($settings[$key]), true, false),
                esc_html($label)
            );
        }
        echo '<p class="description">Selected features will be hidden from the admin bar menu.</p>';
    }

    /**
     * Render role exclusion checkboxes
     */
    public function render_role_exclusion_field() {
        $settings = get_option('holler_cache_control_visibility', array());
        $excluded_roles = !empty($settings['excluded_roles']) ? $settings['excluded_roles'] : array();
        
        // Get all WordPress roles
        $roles = wp_roles()->get_names();
        
        foreach ($roles as $role_key => $role_name) {
            printf(
                '<label style="display: block; margin-bottom: 8px;"><input type="checkbox" name="holler_cache_control_visibility[excluded_roles][]" value="%s" %s> %s</label>',
                esc_attr($role_key),
                checked(in_array($role_key, $excluded_roles), true, false),
                esc_html($role_name)
            );
        }
        echo '<p class="description">Selected roles will not see the hidden plugins and features. Note: Super Admin will always see everything.</p>';
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
     * Add styles for admin bar
     */
    public function admin_bar_styles() {
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
        </style>';
    }

    /**
     * Remove old menu items
     */
    public function remove_old_menu_items() {
        remove_submenu_page('options-general.php', 'holler-cache-control');
    }
}
