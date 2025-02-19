<?php
namespace HollerCacheControl\Admin;

use HollerCacheControl\Cache\Nginx;
use HollerCacheControl\Cache\Redis;
use HollerCacheControl\Cache\Cloudflare;
use HollerCacheControl\Cache\CloudflareAPO;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/Admin
 */
class Tools {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Initialize admin hooks
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Initialize front-end hooks if admin bar is showing
        add_action('init', array($this, 'init_front_end'));

        // Add admin bar menu
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 999);

        // Register AJAX handlers
        add_action('wp_ajax_holler_cache_control_status', array($this, 'handle_cache_status'));
        add_action('wp_ajax_holler_purge_cache', array($this, 'handle_purge_cache'));
        add_action('wp_ajax_holler_cache_control_save_settings', array($this, 'handle_save_settings'));

        // Add async cache purge hook
        add_action('holler_cache_control_async_purge', array($this, 'purge_all_caches'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add cache purging hooks
        $this->add_cache_purging_hooks();

        // Add error handling for AJAX requests
        add_action('wp_ajax_nopriv_holler_purge_cache', function() {
            wp_send_json_error('You must be logged in to perform this action.');
        });

        // Add shutdown function to catch fatal errors
        add_action('shutdown', function() {
            $error = error_get_last();
            if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR))) {
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    wp_send_json_error(array(
                        'message' => 'Fatal error: ' . $error['message'],
                        'file' => $error['file'],
                        'line' => $error['line']
                    ));
                }
            }
        });

        // Add plugin activation/deactivation hook
        add_action('activated_plugin', array($this, 'handle_plugin_state_change'));
        add_action('deactivated_plugin', array($this, 'handle_plugin_state_change'));

        // Remove Nginx Helper plugin's purge button if hidden
        add_action('admin_init', function() {
            $settings = get_option('holler_cache_control_settings', array());
            $hide_nginx = !empty($settings['hide_nginx_purge_button']);

            if ($hide_nginx) {
                // Override Nginx Helper's menu items with empty ones at a higher priority
                add_action('admin_bar_menu', function($wp_admin_bar) {
                    // Remove existing items first
                    $wp_admin_bar->remove_node('nginx-helper-purge-all');
                    $wp_admin_bar->remove_node('nginx-helper-purge-current-page');
                    $wp_admin_bar->remove_node('nginx-helper');
                    
                    // Add empty items to prevent Nginx Helper from adding theirs
                    $wp_admin_bar->add_menu(array(
                        'id' => 'nginx-helper-purge-all',
                        'title' => '',
                        'href' => '#',
                        'meta' => array('class' => 'hidden')
                    ));
                    $wp_admin_bar->add_menu(array(
                        'id' => 'nginx-helper-purge-current-page',
                        'title' => '',
                        'href' => '#',
                        'meta' => array('class' => 'hidden')
                    ));
                    $wp_admin_bar->add_menu(array(
                        'id' => 'nginx-helper',
                        'title' => '',
                        'href' => '#',
                        'meta' => array('class' => 'hidden')
                    ));
                }, 99); // Run before Nginx Helper's priority of 100
                
                // Hide any remaining items with CSS
                add_action('wp_head', function() {
                    echo '<style>
                        #wp-admin-bar-nginx-helper-purge-all,
                        #wp-admin-bar-nginx-helper-purge-current-page,
                        #wp-admin-bar-nginx-helper,
                        .nginx-helper-purge-button,
                        a[href*="nginx_helper_action=purge"] { display: none !important; }
                    </style>';
                });
                add_action('admin_head', function() {
                    echo '<style>
                        #wp-admin-bar-nginx-helper-purge-all,
                        #wp-admin-bar-nginx-helper-purge-current-page,
                        #wp-admin-bar-nginx-helper,
                        .nginx-helper-purge-button,
                        a[href*="nginx_helper_action=purge"] { display: none !important; }
                    </style>';
                });
                
                // Block Nginx Helper's purge URLs
                if (isset($_GET['nginx_helper_action']) && $_GET['nginx_helper_action'] === 'purge') {
                    wp_redirect(admin_url());
                    exit;
                }
            }
        });

        // Remove Nginx Helper's purge links from admin pages
        add_action('admin_head', function() {
            $settings = get_option('holler_cache_control_settings', array());
            $hide_nginx = !empty($settings['hide_nginx_purge_button']);

            if ($hide_nginx) {
                echo '<style>
                    .nginx-helper-purge-button,
                    #wp-admin-bar-nginx-helper-purge-all,
                    #wp-admin-bar-nginx-helper-purge-current-page,
                    #wp-admin-bar-nginx-helper,
                    a[href*="nginx_helper_action=purge"] { display: none !important; }
                </style>';
            }
        });

        // Remove Nginx Helper's admin page if hidden
        add_action('admin_menu', function() {
            $settings = get_option('holler_cache_control_settings', array());
            $hide_nginx = !empty($settings['hide_nginx_purge_button']);

            if ($hide_nginx) {
                remove_menu_page('nginx');
                remove_submenu_page('options-general.php', 'nginx');
            }
        });

        // Deactivate Nginx Helper plugin if our plugin is active
        add_action('admin_init', function() {
            if (!function_exists('is_plugin_active') || !function_exists('deactivate_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            $nginx_helper_plugin = 'nginx-helper/nginx-helper.php';
            if (is_plugin_active($nginx_helper_plugin)) {
                deactivate_plugins($nginx_helper_plugin);
                
                // Store a flag to show notice
                update_option('holler_cache_deactivated_nginx_helper', true);
                
                // Redirect to remove the deactivation notice
                if (isset($_GET['activate'])) {
                    unset($_GET['activate']);
                }
            }
        });

        // Show notice that we deactivated Nginx Helper
        add_action('admin_notices', function() {
            if (get_option('holler_cache_deactivated_nginx_helper')) {
                delete_option('holler_cache_deactivated_nginx_helper');
                echo '<div class="notice notice-info is-dismissible"><p>' . 
                    __('Nginx Helper plugin has been deactivated as its functionality is now handled by Holler Cache Control.', 'holler-cache-control') . 
                    '</p></div>';
            }
        });
    }

    /**
     * Initialize front-end functionality if admin bar is showing
     */
    public function init_front_end() {
        // Get settings
        $settings = get_option('holler_cache_control_settings', array());
        $hide_nginx = !empty($settings['hide_nginx_purge_button']);

        if ($hide_nginx) {
            // Disable Nginx Helper's purge functionality entirely
            add_filter('pre_option_rt_wp_nginx_helper_options', function($value) {
                if (!is_array($value)) {
                    $value = array();
                }
                $value['enable_purge'] = false;
                return $value;
            });
            
            // Remove Nginx Helper's purge functionality
            add_filter('nginx_helper_purge_url', '__return_false');
            add_filter('nginx_helper_purge_all', '__return_false');
            add_filter('rt_nginx_helper_purge_urls', '__return_false');
            add_filter('rt_nginx_helper_purge_urls_on_update', '__return_false');
            
            // Prevent Nginx Helper from adding menu items
            global $nginx_helper;
            if (isset($nginx_helper) && isset($nginx_helper->loader)) {
                remove_action('admin_bar_menu', array($nginx_helper->loader, 'nginx_helper_toolbar_purge_link'), 100);
            }
            
            // Remove menu items at priority 90 (before they're added at 100)
            add_action('admin_bar_menu', function($wp_admin_bar) {
                $wp_admin_bar->remove_node('nginx-helper-purge-all');
                $wp_admin_bar->remove_node('nginx-helper-purge-current-page');
                $wp_admin_bar->remove_node('nginx-helper');
            }, 90);
            
            // Also remove at priority 999 in case they were added later
            add_action('admin_bar_menu', function($wp_admin_bar) {
                $wp_admin_bar->remove_node('nginx-helper-purge-all');
                $wp_admin_bar->remove_node('nginx-helper-purge-current-page');
                $wp_admin_bar->remove_node('nginx-helper');
            }, 999);
            
            // Hide any remaining Nginx Helper elements with CSS
            add_action('wp_head', function() {
                echo '<style>
                    #wp-admin-bar-nginx-helper-purge-all,
                    #wp-admin-bar-nginx-helper-purge-current-page,
                    #wp-admin-bar-nginx-helper,
                    .nginx-helper-settings,
                    a[href*="nginx_helper_action=purge"] { display: none !important; }
                </style>';
            });
            add_action('admin_head', function() {
                echo '<style>
                    #wp-admin-bar-nginx-helper-purge-all,
                    #wp-admin-bar-nginx-helper-purge-current-page,
                    #wp-admin-bar-nginx-helper,
                    .nginx-helper-settings,
                    a[href*="nginx_helper_action=purge"] { display: none !important; }
                </style>';
            });
            
            // Prevent the menu items from being added in the first place
            add_filter('show_admin_bar', function($show) {
                if (isset($_GET['nginx_helper_action']) && $_GET['nginx_helper_action'] === 'purge') {
                    return false;
                }
                return $show;
            });
        }

        // Add front-end scripts and notice container if admin bar is showing
        if (!is_admin() && is_admin_bar_showing()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_footer', array($this, 'add_notice_container'));
        }
    }

    /**
     * Add hooks for automatic cache purging on various WordPress actions
     */
    private function add_cache_purging_hooks() {
        // Content updates - use async purging
        add_action('save_post', array($this, 'schedule_async_cache_purge'), 10, 0);
        add_action('edit_post', array($this, 'schedule_async_cache_purge'), 10, 0);
        add_action('deleted_post', array($this, 'schedule_async_cache_purge'), 10, 0);
        add_action('wp_insert_post', array($this, 'schedule_async_cache_purge'), 10, 0);
        
        // Theme changes - these are less frequent, so use sync purging
        add_action('switch_theme', array($this, 'purge_all_caches_on_update'), 10, 0);
        
        // Plugin changes - these are less frequent, so use sync purging
        add_action('upgrader_process_complete', array($this, 'purge_all_caches_on_update'), 10, 0);

        // Elementor specific hooks
        // add_action('elementor/core/files/clear_cache', array($this, 'purge_elementor_cache'), 10, 0);
        // add_action('elementor/maintenance_mode/enable', array($this, 'purge_all_caches_on_update'), 10, 0);
        // add_action('elementor/maintenance_mode/disable', array($this, 'purge_all_caches_on_update'), 10, 0);
        // add_action('elementor/settings/save', array($this, 'purge_all_caches_on_update'), 10, 0);
        // add_action('elementor/editor/after_save', array($this, 'purge_elementor_cache'), 10, 0);
        
        // Astra theme specific hooks
        add_action('astra_addon_update_after', array($this, 'purge_astra_cache'), 10, 0);
        add_action('astra_background_obj_created', array($this, 'purge_astra_cache'), 10, 0);
        add_action('astra_cache_clear', array($this, 'purge_astra_cache'), 10, 0);
        add_action('customize_save_after', array($this, 'purge_all_caches_on_update'), 10, 0);
        
        // Add async cache purge hook
        add_action('holler_cache_control_async_purge', array($this, 'purge_all_caches'));
        
        // Add filter to optimize Elementor's external file loading
       //add_filter('elementor/frontend/print_google_fonts', array($this, 'optimize_elementor_google_fonts'), 10, 1);
       // add_filter('elementor/frontend/print_google_fonts_preconnect', '__return_false');

        // Add Astra optimization filters
        //add_filter('astra_addon_asset_js_enable', array($this, 'optimize_astra_assets'), 10, 1);
        //add_filter('astra_addon_asset_css_enable', array($this, 'optimize_astra_assets'), 10, 1);
        //add_filter('astra_dynamic_css_preload', '__return_true');
    }

    /**
     * Purge all caches on plugin update/activation/deactivation
     */
    public function purge_all_caches_on_update() {
        $messages = array();
        $failures = array();
        $successes = array();

        // Get status of all caches
        $statuses = array(
            'redis' => \HollerCacheControl\Cache\Redis::get_status(),
            'nginx' => \HollerCacheControl\Cache\Nginx::get_status(),
            'cloudflare' => \HollerCacheControl\Cache\Cloudflare::get_status(),
            'cloudflare-apo' => \HollerCacheControl\Cache\CloudflareAPO::get_status()
        );

        // Define cache types in specific order, but only include active ones
        $caches = array();
        if ($statuses['redis']['active']) {
            $caches[] = 'redis';           // 1. Clear Redis object cache first
        }
        if ($statuses['nginx']['active']) {
            $caches[] = 'nginx';           // 2. Clear Nginx page cache second
        }
        if ($statuses['cloudflare']['active']) {
            $caches[] = 'cloudflare';      // 3. Clear Cloudflare cache last
        }
        if ($statuses['cloudflare-apo']['active']) {
            $caches[] = 'cloudflare-apo';  // 4. Clear Cloudflare APO last
        }

        foreach ($caches as $cache_type) {
            $cache_result = $this->purge_single_cache($cache_type);
            
            if (!empty($cache_result['message'])) {
                // Skip "not found" or "already empty" messages for success cases
                if ($cache_result['success'] && (
                    strpos($cache_result['message'], 'not found') !== false ||
                    strpos($cache_result['message'], 'already empty') !== false
                )) {
                    continue;
                }
                $messages[] = $cache_result['message'];
            }
            
            if ($cache_result['success']) {
                $successes[] = $cache_type;
            } else {
                // Only add to failures if it's a real error, not just "not found" or "already empty"
                if (strpos($cache_result['message'], 'not found') === false &&
                    strpos($cache_result['message'], 'already empty') === false) {
                    $failures[] = $cache_type;
                }
            }
        }

        $response = $this->format_messages($messages, $successes, $failures);

        // Return result array for CLI and AJAX
        return array(
            'success' => !empty($successes),
            'message' => $response
        );
    }

    /**
     * Actually purge all caches
     */
    public function purge_all_caches() {
        $result = array(
            'success' => false,
            'message' => ''
        );

        try {
            // Purge Nginx cache
            $nginx_result = \HollerCacheControl\Cache\Nginx::purge();
            if (!$nginx_result['success']) {
                $result['message'] .= $nginx_result['message'] . "\n";
            }

            // Purge Redis cache
            $redis_result = \HollerCacheControl\Cache\Redis::purge();
            if (!$redis_result['success']) {
                $result['message'] .= $redis_result['message'] . "\n";
            }

            // Purge Cloudflare cache
            $cloudflare_result = \HollerCacheControl\Cache\Cloudflare::purge();
            if (!$cloudflare_result['success']) {
                $result['message'] .= $cloudflare_result['message'] . "\n";
            }

            // Purge Cloudflare APO cache
            $cloudflare_apo_result = \HollerCacheControl\Cache\CloudflareAPO::purge();
            if (!$cloudflare_apo_result['success']) {
                $result['message'] .= $cloudflare_apo_result['message'] . "\n";
            }

            $result['success'] = true;
            if (empty($result['message'])) {
                $result['message'] = __('All caches cleared successfully', 'holler-cache-control');
            }

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Clear Elementor cache
     */
    public function purge_elementor_cache() {
        if (!class_exists('\Elementor\Plugin') || !current_user_can('manage_options')) {
            return;
        }

        // Use WordPress Filesystem API
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        // Clear Elementor's CSS cache
        $uploads_dir = wp_get_upload_dir();
        $elementor_css_dir = trailingslashit($uploads_dir['basedir']) . 'elementor/css';
        
        if ($wp_filesystem->exists($elementor_css_dir)) {
            // Remove min directory
            $min_dir = trailingslashit($elementor_css_dir) . 'min';
            if ($wp_filesystem->exists($min_dir)) {
                $wp_filesystem->rmdir($min_dir, true);
            }
            
            // Remove all files in css directory but keep the directory itself
            $files = $wp_filesystem->dirlist($elementor_css_dir);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file['type'] === 'f') {
                        $wp_filesystem->delete($elementor_css_dir . '/' . $file['name']);
                    }
                }
            }
        }

        // Clear Elementor's data cache
        if (method_exists('\Elementor\Plugin', 'instance')) {
            remove_action('elementor/core/files/clear_cache', array(\Elementor\Plugin::instance()->files_manager, 'clear_cache'));
            \Elementor\Plugin::instance()->files_manager->clear_cache();
        }
    }

    /**
     * Delete contents of a directory using WordPress Filesystem API
     * @param string $dir Directory path
     */
    private function delete_directory_contents($dir) {
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        if (!$wp_filesystem->exists($dir)) {
            return;
        }

        $files = $wp_filesystem->dirlist($dir);
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if ($file['type'] === 'f') {
                $wp_filesystem->delete($dir . '/' . $file['name']);
            }
        }
    }

    /**
     * Delete all transients from the WordPress database
     */
    private function delete_transient_cache() {
        global $wpdb;
        
        // Use prepare to safely construct queries
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_%'));
        
        // If this is a multisite, also clear network transients
        if (is_multisite()) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", '_site_transient_%'));
        }
    }

    /**
     * Log error message securely
     */
    private function log_error($message) {
        // Sanitize message
        $message = sanitize_text_field($message);
        
        // Log to PHP error log
        error_log('[Holler Cache Control] ' . $message);
        
        // Log to GridPane debug log if writable
        $debug_log = '/var/log/gp/debug.log';
        if (is_writable($debug_log)) {
            $log_entry = sprintf('[%s] %s' . PHP_EOL, 
                current_time('Y-m-d H:i:s'), 
                $message
            );
            file_put_contents($debug_log, $log_entry, FILE_APPEND);
        }
    }

    /**
     * Purge Astra-specific caches
     */
    public function purge_astra_cache() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Clear Astra's dynamic CSS cache
        delete_option('astra-addon-css-dynamic-css');
        delete_site_option('astra-addon-css-dynamic-css');
        
        // Clear customizer CSS cache
        delete_option('astra-customizer-css');
        delete_site_option('astra-customizer-css');

        // Clear cached Google Fonts
        delete_option('astra-google-fonts-cache');
        delete_site_option('astra-google-fonts-cache');

        // Clear theme CSS cache files using WordPress Filesystem API
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        $upload_dir = wp_get_upload_dir();
        $astra_css_dir = trailingslashit($upload_dir['basedir']) . 'astra-addon';
        
        if ($wp_filesystem->exists($astra_css_dir)) {
            $files = $wp_filesystem->dirlist($astra_css_dir);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file['type'] === 'f') {
                        $wp_filesystem->delete($astra_css_dir . '/' . $file['name']);
                    }
                }
            }
        }

        // Clear child theme cache if using holler-agnt
        if (get_stylesheet() === 'holler-agnt') {
            delete_option('holler_agnt_dynamic_css');
            delete_site_option('holler_agnt_dynamic_css');
            
            $child_css_dir = trailingslashit($upload_dir['basedir']) . 'holler-agnt';
            if ($wp_filesystem->exists($child_css_dir)) {
                $files = $wp_filesystem->dirlist($child_css_dir);
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if ($file['type'] === 'f') {
                            $wp_filesystem->delete($child_css_dir . '/' . $file['name']);
                        }
                    }
                }
            }
        }

        // Refresh Astra's asset versions
        update_option('astra-addon-auto-version', time());
        if (function_exists('astra_addon_filesystem')) {
            astra_addon_filesystem()->reset_assets_cache();
        }
    }

    /**
     * Optimize Elementor's Google Fonts loading
     * 
     * @param array $google_fonts Array of Google Fonts to be loaded
     * @return array Modified array of Google Fonts
     */
    public function optimize_elementor_google_fonts($google_fonts) {
        // If we're in the admin, return original fonts
        if (is_admin()) {
            return $google_fonts;
        }

        // Get stored font preferences
        $stored_fonts = get_option('holler_elementor_font_optimization', array());
        
        if (empty($stored_fonts)) {
            // Store the fonts for future reference
            update_option('holler_elementor_font_optimization', $google_fonts);
            return $google_fonts;
        }

        // Return stored fonts to maintain consistency
        return $stored_fonts;
    }

    /**
     * Optimize Astra's asset loading
     * 
     * @param bool $enable Whether to enable the asset
     * @return bool Modified enable status
     */
    public function optimize_astra_assets($enable) {
        // If we're in the admin, return original setting
        if (is_admin()) {
            return $enable;
        }

        // Get stored optimization preferences
        $stored_prefs = get_option('holler_astra_optimization', null);
        
        if (is_null($stored_prefs)) {
            // Store the current preference for future reference
            update_option('holler_astra_optimization', $enable);
            return $enable;
        }

        // Return stored preference to maintain consistency
        return $stored_prefs;
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
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
                'type' => 'string',
                'default' => '0',
                'sanitize_callback' => function($value) {
                    return $value === '1' ? '1' : '0';
                }
            )
        );

        register_setting(
            'holler_cache_control_settings',
            'hide_redis_purge_button',
            array(
                'type' => 'string',
                'default' => '0',
                'sanitize_callback' => function($value) {
                    return $value === '1' ? '1' : '0';
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
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        $css_file = plugin_dir_path(dirname(__DIR__)) . 'assets/css/admin.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'holler-cache-control-admin',
                plugin_dir_url(dirname(__DIR__)) . 'assets/css/admin.css',
                array(),
                filemtime($css_file)
            );
        }
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts($hook) {
        if (!$this->is_plugin_admin_page($hook) && !is_admin_bar_showing()) {
            return;
        }

        // Add ajaxurl for front-end
        if (!is_admin()) {
            wp_enqueue_script('jquery');
            wp_add_inline_script('jquery', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');
        }

        wp_enqueue_script(
            'holler-cache-control-admin',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('holler-cache-control-admin', 'hollerCacheControl', array(
            'nonces' => array(
                'status' => wp_create_nonce('holler_cache_control_status'),
                'all' => wp_create_nonce('holler_purge_all'),
                'nginx' => wp_create_nonce('holler_purge_nginx'),
                'redis' => wp_create_nonce('holler_purge_redis'),
                'cloudflare' => wp_create_nonce('holler_purge_cloudflare'),
                'cloudflare-apo' => wp_create_nonce('holler_purge_cloudflare-apo'),
                'settings' => wp_create_nonce('holler_cache_control_settings')
            ),
            'i18n' => array(
                'purging' => __('Purging...', 'holler-cache-control'),
                'confirm_purge_all' => __('Are you sure you want to purge all caches?', 'holler-cache-control')
            )
        ));

        // Add inline styles for front-end notices
        if (!is_admin()) {
            wp_enqueue_style('dashicons');
            wp_add_inline_style('dashicons', '
                #holler-cache-notice-container {
                    position: fixed;
                    top: 32px;
                    left: 0;
                    right: 0;
                    z-index: 99999;
                }
                #holler-cache-notice-container .notice {
                    margin: 5px auto;
                    max-width: 800px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                    position: relative;
                    padding: 1px 40px 1px 12px;
                    display: flex;
                    align-items: center;
                    background: #fff;
                    border-left: 4px solid #72aee6;
                }
                #holler-cache-notice-container .notice p {
                    margin: 0.5em 0;
                    padding: 2px;
                    display: inline-block;
                }
                #holler-cache-notice-container .notice-success {
                    border-left-color: #00a32a;
                }
                #holler-cache-notice-container .notice-error {
                    border-left-color: #d63638;
                }
                #holler-cache-notice-container .notice-dismiss {
                    position: absolute;
                    top: 0;
                    right: 1px;
                    border: none;
                    margin: 0;
                    padding: 9px;
                    background: none;
                    color: #787c82;
                    cursor: pointer;
                }
                #holler-cache-notice-container .notice-dismiss:before {
                    background: none;
                    color: #787c82;
                    content: "\\f153";
                    display: block;
                    font: normal 16px/20px dashicons;
                    speak: never;
                    height: 20px;
                    text-align: center;
                    width: 20px;
                    -webkit-font-smoothing: antialiased;
                    -moz-osx-font-smoothing: grayscale;
                }
                #holler-cache-notice-container .notice-dismiss:hover:before {
                    color: #d63638;
                }
                @media screen and (max-width: 782px) {
                    #holler-cache-notice-container {
                        top: 46px;
                    }
                }
            ');
        }
    }

    /**
     * Enqueue assets for both admin and front-end when user is logged in
     */
    public function enqueue_assets() {
        $this->enqueue_styles();
        $this->enqueue_scripts('admin.php');
    }

    public function add_plugin_admin_menu() {
        add_options_page(
            __('Cache Control', 'holler-cache-control'),
            __('Cache Control', 'holler-cache-control'),
            'manage_options',
            'holler-cache-control',
            array($this, 'display_plugin_admin_page')
        );
    }

    public static function get_cache_systems_status() {
        return array(
            'nginx' => Nginx::get_status(),
            'redis' => Redis::get_status(),
            'cloudflare' => Cloudflare::get_status(),
            'cloudflare-apo' => CloudflareAPO::get_status()
        );
    }

    public function display_plugin_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get cache statuses
        $nginx_status = \HollerCacheControl\Cache\Nginx::get_status();
        $redis_status = \HollerCacheControl\Cache\Redis::get_status();
        $cloudflare_status = \HollerCacheControl\Cache\Cloudflare::get_status();
        $cloudflare_apo_status = \HollerCacheControl\Cache\CloudflareAPO::get_status();

        // Include the admin display template
        include_once plugin_dir_path(dirname(__FILE__)) . 'Admin/views/admin-display.php';
    }

    /**
     * Handle AJAX request to get cache status
     */
    public function handle_cache_status() {
        // Check nonce without specifying the field name
        check_ajax_referer('holler_cache_control_status');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'holler-cache-control'));
            return;
        }

        $statuses = array(
            'nginx' => \HollerCacheControl\Cache\Nginx::get_status(),
            'redis' => \HollerCacheControl\Cache\Redis::get_status(),
            'cloudflare' => \HollerCacheControl\Cache\Cloudflare::get_status(),
            'cloudflare-apo' => \HollerCacheControl\Cache\CloudflareAPO::get_status()
        );

        wp_send_json_success($statuses);
    }

    public function handle_purge_cache() {
        try {
            // Convert type to use underscores for nonce verification
            $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
            
            $this->log_error('Purge cache request received:');
            $this->log_error('Type: ' . $type);
            $this->log_error('POST data: ' . print_r($_POST, true));
            
            if (!isset($_POST['_ajax_nonce'])) {
                throw new \Exception(__('Invalid request: missing nonce', 'holler-cache-control'));
            }

            // Check nonce for general purge action
            if (!wp_verify_nonce($_POST['_ajax_nonce'], 'holler_purge_' . $type)) {
                throw new \Exception(__('Invalid security token', 'holler-cache-control'));
            }

            if (!current_user_can('manage_options')) {
                throw new \Exception(__('You do not have permission to perform this action.', 'holler-cache-control'));
            }

            if (empty($type)) {
                throw new \Exception(__('No cache type specified.', 'holler-cache-control'));
            }

            $this->log_error('Cache type validated: ' . $type);

            // If type is 'all', purge all active caches in specific order
            if ($type === 'all') {
                // Get status of all caches
                $statuses = array(
                    'redis' => \HollerCacheControl\Cache\Redis::get_status(),
                    'nginx' => \HollerCacheControl\Cache\Nginx::get_status(),
                    'cloudflare' => \HollerCacheControl\Cache\Cloudflare::get_status(),
                    'cloudflare-apo' => \HollerCacheControl\Cache\CloudflareAPO::get_status()
                );
                
                $this->log_error('Cache statuses: ' . print_r($statuses, true));
                
                // Define cache types in specific order, but only include active ones
                $caches = array();
                if ($statuses['redis']['active']) {
                    $caches[] = 'redis';           // 1. Clear Redis object cache first
                }
                if ($statuses['nginx']['active']) {
                    $caches[] = 'nginx';           // 2. Clear Nginx page cache second
                }
                if ($statuses['cloudflare']['active']) {
                    $caches[] = 'cloudflare';      // 3. Clear Cloudflare cache last
                }
                if ($statuses['cloudflare-apo']['active']) {
                    $caches[] = 'cloudflare-apo';  // 4. Clear Cloudflare APO last
                }
                
                if (empty($caches)) {
                    throw new \Exception(__('No active caches found to purge.', 'holler-cache-control'));
                }
                
                $this->log_error('Active caches to purge: ' . implode(', ', $caches));
                
                $messages = array();
                $failures = array();
                $successes = array();

                foreach ($caches as $cache_type) {
                    $this->log_error('Purging cache type: ' . $cache_type);
                    $cache_result = $this->purge_single_cache($cache_type);
                    
                    if (!empty($cache_result['message'])) {
                        // Skip "not found" or "already empty" messages for success cases
                        if ($cache_result['success'] && (
                            strpos($cache_result['message'], 'not found') !== false ||
                            strpos($cache_result['message'], 'already empty') !== false
                        )) {
                            continue;
                        }
                        $messages[] = $cache_result['message'];
                    }
                    
                    if ($cache_result['success']) {
                        $successes[] = $cache_type;
                    } else {
                        // Only add to failures if it's a real error, not just "not found" or "already empty"
                        if (strpos($cache_result['message'], 'not found') === false &&
                            strpos($cache_result['message'], 'already empty') === false) {
                            $failures[] = $cache_type;
                        }
                        $this->log_error('Failed to purge ' . $cache_type . ': ' . $cache_result['message']);
                    }
                }

                $response = $this->format_messages($messages, $successes, $failures);

                // If we have any successes, consider it a success
                if (!empty($successes)) {
                    wp_send_json_success($response);
                } else {
                    wp_send_json_error($response);
                }
                return;
            }

            // Handle single cache type
            $this->log_error('Purging single cache type: ' . $type);
            $result = $this->purge_single_cache($type);
            if ($result['success']) {
                wp_send_json_success('✅ ' . $result['message']);
            } else {
                wp_send_json_error('❌ ' . $result['message']);
            }

        } catch (\Exception $e) {
            $this->log_error('Cache purge error: ' . $e->getMessage());
            $this->log_error('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('❌ ' . $e->getMessage());
        }
    }

    private function purge_single_cache($type) {
        $result = array(
            'success' => false,
            'message' => ''
        );

        $this->log_error('Purging single cache: ' . $type);

        switch ($type) {
            case 'nginx':
                $result = \HollerCacheControl\Cache\Nginx::purge_cache();
                break;
            case 'redis':
                $result = \HollerCacheControl\Cache\Redis::purge_cache();
                break;
            case 'cloudflare':
                $result = \HollerCacheControl\Cache\Cloudflare::purge_cache();
                break;
            case 'cloudflare-apo':  // Match the hyphenated format from JavaScript
                $result = \HollerCacheControl\Cache\CloudflareAPO::purge_cache();
                break;
            default:
                $result['message'] = sprintf(
                    __('Invalid cache type: %s', 'holler-cache-control'),
                    $type
                );
                break;
        }

        return $result;
    }

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

    public function purge_nginx_cache() {
        check_ajax_referer('holler_purge_nginx_cache');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'holler-cache-control'));
            return;
        }
        
        $result = \HollerCacheControl\Cache\Nginx::purge();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public function admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get settings
        $settings = get_option('holler_cache_control_settings', array());
        $hide_nginx = !empty($settings['hide_nginx_purge_button']);
        $hide_redis = !empty($settings['hide_redis_purge_button']);

        // Get cache statuses
        $cloudflare_status = \HollerCacheControl\Cache\Cloudflare::get_status();
        $cloudflare_apo_status = \HollerCacheControl\Cache\CloudflareAPO::get_status();
        $nginx_status = \HollerCacheControl\Cache\Nginx::get_status();
        $redis_status = \HollerCacheControl\Cache\Redis::get_status();

        // Add main cache control node
        $wp_admin_bar->add_node(array(
            'id' => 'holler-cache-control',
            'title' => __('Cache Control', 'holler-cache-control'),
            'href' => admin_url('options-general.php?page=holler-cache-control')
        ));

        // Add cache status submenu
        $wp_admin_bar->add_node(array(
            'parent' => 'holler-cache-control',
            'id' => 'holler-cache-status',
            'title' => __('Cache Status', 'holler-cache-control')
        ));

        // Add individual cache status items
        if (!$hide_nginx) {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-cache-status',
                'id' => 'holler-nginx-status',
                'title' => sprintf(
                    '%s Nginx Cache: %s',
                    $nginx_status['active'] ? '✓' : '✗',
                    $nginx_status['active'] ? __('Running', 'holler-cache-control') : __('Not Active', 'holler-cache-control')
                )
            ));
        }

        if (!$hide_redis) {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-cache-status',
                'id' => 'holler-redis-status',
                'title' => sprintf(
                    '%s Redis Cache: %s',
                    $redis_status['active'] ? '✓' : '✗',
                    $redis_status['active'] ? __('Running', 'holler-cache-control') : __('Not Active', 'holler-cache-control')
                )
            ));
        }

        if ($cloudflare_status['active']) {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-cache-status',
                'id' => 'holler-cloudflare-status',
                'title' => sprintf(
                    '%s Cloudflare Cache: %s',
                    $cloudflare_status['active'] ? '✓' : '✗',
                    $cloudflare_status['active'] ? __('Running', 'holler-cache-control') : __('Not Active', 'holler-cache-control')
                )
            ));
        }

        if ($cloudflare_apo_status['active']) {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-cache-status',
                'id' => 'holler-cloudflare-apo-status',
                'title' => sprintf(
                    '%s Cloudflare APO: %s',
                    $cloudflare_apo_status['active'] ? '✓' : '✗',
                    $cloudflare_apo_status['active'] ? __('Running', 'holler-cache-control') : __('Not Active', 'holler-cache-control')
                )
            ));
        }

        // Add separator
        $wp_admin_bar->add_node(array(
            'parent' => 'holler-cache-control',
            'id' => 'holler-cache-separator-1',
            'title' => '<div class="ab-item ab-empty-item" style="margin: 5px 0; border-top: 1px solid rgba(255,255,255,0.2);"></div>'
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
        if ($nginx_status['active'] && !$hide_nginx) {
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

        if ($redis_status['active'] && !$hide_redis) {
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

        if ($cloudflare_status['active']) {
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

        if ($cloudflare_apo_status['active']) {
            $wp_admin_bar->add_node(array(
                'parent' => 'holler-purge-cache',
                'id' => 'holler-purge-cloudflare-apo',
                'title' => __('Purge Cloudflare APO Cache', 'holler-cache-control'),
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
            'title' => '<div class="ab-item ab-empty-item" style="margin: 5px 0; border-top: 1px solid rgba(255,255,255,0.2);"></div>'
        ));

        // Add settings link
        $wp_admin_bar->add_node(array(
            'parent' => 'holler-cache-control',
            'id' => 'holler-cache-settings',
            'title' => __('Settings', 'holler-cache-control'),
            'href' => admin_url('options-general.php?page=holler-cache-control')
        ));

        // Remove other plugins' cache purge menus if we're handling that cache type
        if ($nginx_status['active'] && !$hide_nginx) {
            remove_action('admin_bar_menu', 'nginx_cache_purge_admin_bar', 100);
        }
        if ($redis_status['active'] && !$hide_redis) {
            remove_action('admin_bar_menu', 'redis_cache_purge_admin_bar', 100);
        }
    }

    private function is_plugin_admin_page($hook) {
        return strpos($hook, 'holler-cache-control') !== false;
    }

    public function add_notice_container() {
        echo '<div id="holler-cache-notice-container"></div>';
    }

    /**
     * Schedule an async cache purge
     */
    public function schedule_async_cache_purge() {
        if (!wp_next_scheduled('holler_cache_control_async_purge')) {
            wp_schedule_single_event(time(), 'holler_cache_control_async_purge');
        }
    }

    /**
     * Handle settings form submission via AJAX
     */
    public function handle_save_settings() {
        check_ajax_referer('holler_cache_control_settings');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'holler-cache-control'));
            return;
        }

        $settings = array(
            'cloudflare_email' => isset($_POST['cloudflare_email']) ? sanitize_email($_POST['cloudflare_email']) : '',
            'cloudflare_api_key' => isset($_POST['cloudflare_api_key']) ? sanitize_text_field($_POST['cloudflare_api_key']) : '',
            'cloudflare_zone_id' => isset($_POST['cloudflare_zone_id']) ? sanitize_text_field($_POST['cloudflare_zone_id']) : '',
        );

        foreach ($settings as $key => $value) {
            if (!empty($value)) {
                update_option($key, $value);
            }
        }

        wp_send_json_success(__('Settings saved successfully', 'holler-cache-control'));
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
}
