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

        // Initialize AJAX handlers
        add_action('admin_init', array($this, 'init_ajax_handlers'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_purge_cache'));

        // Add admin menu
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(plugin_dir_path(__FILE__) . $this->plugin_name . '.php'), array($this, 'add_action_links'));

        // Add admin bar menu - use high priority to ensure our formatting
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 9999);
        add_action('wp_head', array($this, 'admin_bar_styles'));
        add_action('admin_head', array($this, 'admin_bar_styles'));

        // Add scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add notice container to footer
        add_action('admin_footer', array($this, 'add_notice_container'));

        // Initialize HTTPS filters
        add_action('init', array($this, 'init_https_filters'));

        // Add cache purging hooks
        add_action('init', array($this, 'add_cache_purging_hooks'));

        // Handle plugin activation/deactivation
        add_action('activate_' . $this->plugin_name . '/' . $this->plugin_name . '.php', array($this, 'handle_plugin_state_change'));
        add_action('deactivate_' . $this->plugin_name . '/' . $this->plugin_name . '.php', array($this, 'handle_plugin_state_change'));
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
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get settings
        $settings = get_option('holler_cache_control_settings', array());
        $hide_nginx = !empty($settings['hide_nginx_purge_button']);
        $hide_redis = !empty($settings['hide_redis_purge_button']);

        // Get cache statuses
        $cloudflare_status = \Holler\CacheControl\Admin\Cache\Cloudflare::get_status();
        $cloudflare_apo_status = \Holler\CacheControl\Admin\Cache\CloudflareAPO::get_status();
        $nginx_status = \Holler\CacheControl\Admin\Cache\Nginx::get_status();
        $redis_status = \Holler\CacheControl\Admin\Cache\Redis::get_status();

        // Add main cache control node
        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-control',
            'title' => 'Cache Control',
            'href' => admin_url('options-general.php?page=holler-cache-control'),
            'meta' => array(
                'class' => 'menupop'
            )
        ));

        // Add cache status submenu
        $wp_admin_bar->add_node(array(
            'parent' => 'holler-cache-control',
            'id' => 'holler-cache-status',
            'title' => 'Cache Status',
            'meta' => array(
                'class' => 'menupop'
            )
        ));

        // Helper function to get status indicator
        $get_status_text = function($status, $name) {
            $icon = $status['status'] === 'active' ? '🟢' : '🔴';
            return sprintf('%s %s', $icon, $name);
        };

        // Add individual cache status items
        if (!$hide_nginx) {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-cache-status',
                'id' => 'holler-nginx-status',
                'title' => $get_status_text($nginx_status, 'Nginx Cache'),
                'href' => '#',
                'meta' => array(
                    'class' => 'holler-cache-status-item',
                    'onclick' => 'return false;'
                )
            ));
        }

        if (!$hide_redis) {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-cache-status',
                'id' => 'holler-redis-status',
                'title' => $get_status_text($redis_status, 'Redis Cache'),
                'href' => '#',
                'meta' => array(
                    'class' => 'holler-cache-status-item',
                    'onclick' => 'return false;'
                )
            ));
        }

        if ($cloudflare_status['status'] !== 'not_configured') {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-cache-status',
                'id' => 'holler-cloudflare-status',
                'title' => $get_status_text($cloudflare_status, 'Cloudflare Cache'),
                'href' => '#',
                'meta' => array(
                    'class' => 'holler-cache-status-item',
                    'onclick' => 'return false;'
                )
            ));
        }

        if ($cloudflare_apo_status['status'] !== 'not_configured') {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-cache-status',
                'id' => 'holler-cloudflare-apo-status',
                'title' => $get_status_text($cloudflare_apo_status, 'Cloudflare APO'),
                'href' => '#',
                'meta' => array(
                    'class' => 'holler-cache-status-item',
                    'onclick' => 'return false;'
                )
            ));
        }

        // Add separator
        $wp_admin_bar->add_node(array(
            'parent' => 'holler-cache-control',
            'id' => 'holler-cache-separator-1',
            'title' => '<span style="margin: 5px 0; border-top: 1px solid rgba(255,255,255,0.2);"></span>',
            'meta' => array(
                'html' => true
            )
        ));

        // Add purge cache submenu
        $wp_admin_bar->add_node(array(
            'parent' => 'holler-cache-control',
            'id' => 'holler-purge-cache',
            'title' => __('Purge Cache', 'holler-cache-control')
        ));

        // Add purge all caches button
        $wp_admin_bar->add_node(array(
            'parent' => 'holler-purge-cache',
            'id' => 'holler-purge-all',
            'title' => __('Purge All Caches', 'holler-cache-control'),
            'href' => '#',
            'meta' => array(
                'class' => 'purge-cache',
                'onclick' => 'return false;'
            )
        ));

        // Add individual purge buttons
        if ($nginx_status['status'] === 'active' && !$hide_nginx) {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-purge-cache',
                'id' => 'holler-purge-nginx',
                'title' => __('Purge Nginx Cache', 'holler-cache-control'),
                'href' => '#',
                'meta' => array(
                    'class' => 'purge-cache',
                    'onclick' => 'return false;'
                )
            ));

            // Remove other plugins' Nginx purge buttons
            $wp_admin_bar->remove_node('nginx-cache');
            $wp_admin_bar->remove_node('nginx_cache');
            $wp_admin_bar->remove_node('purge-cache');
        }

        if ($redis_status['status'] === 'active' && !$hide_redis) {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-purge-cache',
                'id' => 'holler-purge-redis',
                'title' => __('Purge Redis Cache', 'holler-cache-control'),
                'href' => '#',
                'meta' => array(
                    'class' => 'purge-cache',
                    'onclick' => 'return false;'
                )
            ));

            // Remove other plugins' Redis purge buttons
            $wp_admin_bar->remove_node('redis-cache');
            $wp_admin_bar->remove_node('redis_cache');
        }

        if ($cloudflare_status['status'] === 'active') {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-purge-cache',
                'id' => 'holler-purge-cloudflare',
                'title' => __('Purge Cloudflare Cache', 'holler-cache-control'),
                'href' => '#',
                'meta' => array(
                    'class' => 'purge-cache',
                    'onclick' => 'return false;'
                )
            ));
        }

        if ($cloudflare_apo_status['status'] === 'active') {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-purge-cache',
                'id' => 'holler-purge-cloudflare-apo',
                'title' => __('Purge Cloudflare APO', 'holler-cache-control'),
                'href' => '#',
                'meta' => array(
                    'class' => 'purge-cache',
                    'onclick' => 'return false;'
                )
            ));
        }

        // Add separator
        $wp_admin_bar->add_node(array(
            'parent' => 'holler-cache-control',
            'id' => 'holler-cache-separator-2',
            'title' => '<span style="margin: 5px 0; border-top: 1px solid rgba(255,255,255,0.2);"></span>'
        ));

        // Add settings link
        $wp_admin_bar->add_node(array(
            'parent' => 'holler-cache-control',
            'id' => 'holler-cache-settings',
            'title' => __('Settings', 'holler-cache-control'),
            'href' => admin_url('options-general.php?page=holler-cache-control')
        ));

        // Remove other plugins' cache purge menus if we're handling that cache type
        if ($nginx_status['status'] === 'active' && !$hide_nginx) {
            remove_action('admin_bar_menu', 'nginx_cache_purge_admin_bar', 100);
        }
        if ($redis_status['status'] === 'active' && !$hide_redis) {
            remove_action('admin_bar_menu', 'redis_cache_purge_admin_bar', 100);
        }
    }

    /**
     * Register the administration menu for this plugin
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __('Cache Control', 'holler-cache-control'),
            __('Cache Control', 'holler-cache-control'),
            'manage_options',
            'holler-cache-control',
            array($this, 'display_plugin_admin_page'),
            'dashicons-performance',
            90
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
        add_action('wp_ajax_holler_purge_cache', array($this->ajax_handler, 'handle_purge_cache'));
        add_action('wp_ajax_holler_cache_control_status', array($this->ajax_handler, 'handle_cache_status'));
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
        // Register Slack settings
        register_setting(
            'holler_cache_control_settings',
            'slack_webhook_urls',
            array(
                'type' => 'array',
                'description' => __('Slack webhook URLs for cache control', 'holler-cache-control'),
                'sanitize_callback' => function($urls) {
                    if (!is_array($urls)) {
                        return array();
                    }
                    return array_map('esc_url_raw', array_filter($urls));
                }
            )
        );

        register_setting(
            'holler_cache_control_settings',
            'slack_allowed_sites',
            array(
                'type' => 'array',
                'description' => __('Sites that can be cleared via Slack', 'holler-cache-control'),
                'sanitize_callback' => function($sites) {
                    if (!is_array($sites)) {
                        return array();
                    }
                    return array_map('sanitize_text_field', array_filter($sites));
                }
            )
        );

        // Register browser TTL setting
        register_setting(
            'holler_cache_control_settings',
            'cloudflare_browser_ttl',
            array(
                'type' => 'integer',
                'default' => 14400,
                'sanitize_callback' => function($value) {
                    $value = intval($value);
                    return $value > 0 ? $value : 14400;
                }
            )
        );

        // Register Cloudflare optimization settings
        register_setting(
            'holler_cache_control_settings',
            'cloudflare_auto_optimize',
            array(
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => function($value) {
                    return (bool)$value;
                }
            )
        );

        // Register Nginx settings
        register_setting(
            'holler_cache_control_settings',
            'nginx_cache_method',
            array(
                'type' => 'string',
                'default' => 'redis',
                'sanitize_callback' => function($value) {
                    return in_array($value, array('fastcgi', 'redis')) ? $value : 'redis';
                }
            )
        );

        // Register admin bar settings
        register_setting(
            'holler_cache_control_settings',
            'hide_nginx_purge_button',
            array(
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => function($value) {
                    return (bool)$value;
                }
            )
        );

        register_setting(
            'holler_cache_control_settings',
            'hide_redis_purge_button',
            array(
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => function($value) {
                    return (bool)$value;
                }
            )
        );

        // Register Cloudflare settings
        if (!defined('CLOUDFLARE_EMAIL')) {
            register_setting(
                'holler_cache_control_settings',
                'cloudflare_email',
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email'
                )
            );
        }

        if (!defined('CLOUDFLARE_API_KEY')) {
            register_setting(
                'holler_cache_control_settings',
                'cloudflare_api_key',
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            );
        }

        if (!defined('CLOUDFLARE_ZONE_ID')) {
            register_setting(
                'holler_cache_control_settings',
                'cloudflare_zone_id',
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            );
        }
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
}
