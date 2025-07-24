<?php
namespace HollerCacheControl\Core;

use HollerCacheControl\Admin\Tools;
use HollerCacheControl\Core\Loader;

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl\Core
 */
class Plugin {
    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * Initialize the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->plugin_name = 'holler-cache-control';
        $this->version = HOLLER_CACHE_CONTROL_VERSION;

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        
        // Hide plugin from plugins list if user doesn't have capability
        add_filter('all_plugins', array($this, 'hide_plugin_from_list'));
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        $this->loader = new Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $admin = new \HollerCacheControl\Admin\Tools();

        // Add admin menu items
        $this->loader->add_action('admin_menu', $admin, 'remove_old_menu_items', 999); // Run after other menu items are added
        $this->loader->add_action('admin_init', $admin, 'register_settings');
        $this->loader->add_action('admin_bar_menu', $admin, 'admin_bar_menu', 100);
        // Remove other cache plugin admin bar items at higher priority
        $this->loader->add_action('wp_before_admin_bar_render', $admin, 'remove_cache_admin_bar_items', 999);
        $this->loader->add_action('admin_head', $admin, 'admin_bar_styles');
        $this->loader->add_action('wp_head', $admin, 'admin_bar_styles'); // Also load on frontend
        
        // Ensure jQuery is loaded for admin bar functionality on frontend
        $this->loader->add_action('wp_enqueue_scripts', $admin, 'enqueue_frontend_admin_bar_scripts');
        $this->loader->add_action('wp_footer', $admin, 'add_frontend_admin_bar_script');

        // Register settings
        $this->loader->add_action('admin_init', $admin, 'register_settings');

        // AJAX handlers are now registered in Tools.php init_ajax_handlers() method

        // Enqueue admin scripts
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
    }

    private function define_public_hooks() {
        // Add public hooks here
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Hide related cache plugins from plugins list based on settings and user role
     *
     * @param array $plugins Array of plugins
     * @return array Modified array of plugins
     */
    public function hide_plugin_from_list($plugins) {
        // Super Admin can always see everything
        if (is_super_admin()) {
            return $plugins;
        }

        // Get visibility settings
        $settings = get_option('holler_cache_control_visibility', array());
        
        // Get current user's roles
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;

        // Check if user's role is excluded
        $excluded_roles = !empty($settings['excluded_roles']) ? $settings['excluded_roles'] : array();
        $should_hide = false;

        foreach ($user_roles as $role) {
            if (in_array($role, $excluded_roles)) {
                $should_hide = true;
                break;
            }
        }

        if ($should_hide) {
            // Map settings to plugin files
            $plugin_map = array(
                'hide_nginx_helper' => 'nginx-helper/nginx-helper.php',
                'hide_redis_cache' => 'redis-cache/redis-cache.php',
                'hide_cloudflare' => 'cloudflare/cloudflare.php'
            );

            // Hide plugins based on settings
            foreach ($plugin_map as $setting => $plugin_file) {
                if (!empty($settings[$setting]) && isset($plugins[$plugin_file])) {
                    unset($plugins[$plugin_file]);
                }
            }
        }

        return $plugins;
    }
}
