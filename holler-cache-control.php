<?php
/**
 * Plugin Name:       Holler Cache Control
 * Plugin URI:        https://hollerdigital.com/
 * Description:       Control Nginx FastCGI Cache, Redis Object Cache, and Cloudflare Cache from the WordPress admin.
 * Version:          1.2.0
 * Author:           Holler Digital
 * Author URI:       https://hollerdigital.com/
 * License:          GPL-2.0+
 * License URI:      http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:      holler-cache-control
 * Domain Path:      /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define Cloudflare credentials
if (!defined('CLOUDFLARE_EMAIL')) {
    define('CLOUDFLARE_EMAIL', 'james@hollerdigital.com');
}
if (!defined('CLOUDFLARE_API_KEY')) {
    define('CLOUDFLARE_API_KEY', 'cfb08d12bd1f55cc531cadfd3d18b8700077a');
}
if (!defined('CLOUDFLARE_ZONE_ID')) {
    define('CLOUDFLARE_ZONE_ID', 'd3c3e0e2f208b3acff234772b444e24a');
}

/**
 * Currently plugin version.
 */
define('HOLLER_CACHE_CONTROL_VERSION', '1.2.0');

/**
 * Plugin directory
 */
define('HOLLER_CACHE_CONTROL_DIR', plugin_dir_path(__FILE__));

// Load WP-CLI commands if available
if (defined('WP_CLI') && WP_CLI) {
    require_once HOLLER_CACHE_CONTROL_DIR . 'src/CLI/Cache_Commands.php';
}

/**
 * The core plugin class
 */
class Holler_Cache_Control_Plugin {
    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        $this->version = HOLLER_CACHE_CONTROL_VERSION;
        $this->plugin_name = 'holler-cache-control';

        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load core files
        require_once HOLLER_CACHE_CONTROL_DIR . 'includes/class-holler-cache-loader.php';
        require_once HOLLER_CACHE_CONTROL_DIR . 'includes/class-holler-cache-control.php';
        require_once HOLLER_CACHE_CONTROL_DIR . 'includes/class-holler-cache-slack.php';

        // Load admin files
        require_once HOLLER_CACHE_CONTROL_DIR . 'src/Admin/Tools.php';
        require_once HOLLER_CACHE_CONTROL_DIR . 'src/Admin/Cache/CacheManager.php';
        require_once HOLLER_CACHE_CONTROL_DIR . 'src/Admin/Cache/CloudflareAPI.php';
        require_once HOLLER_CACHE_CONTROL_DIR . 'src/Admin/Cache/AjaxHandler.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize the plugin
        add_action('plugins_loaded', array($this, 'init_plugin'));

        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Register deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize the plugin
     */
    public function init_plugin() {
        // Initialize the main plugin class
        $plugin = new \Holler\CacheControl\HollerCacheControl($this->plugin_name, $this->version);
        $plugin->run();

        // Initialize the admin tools if in admin area
        if (is_admin()) {
            $admin = new \Holler\CacheControl\Admin\Tools($this->plugin_name, $this->version);
            $admin->init_ajax_handlers();
        }
    }

    /**
     * Activation hook
     */
    public function activate() {
        // Add any activation tasks here
    }

    /**
     * Deactivation hook
     */
    public function deactivate() {
        // Add any cleanup tasks here
    }
}

// Initialize the plugin
$holler_cache_control = new Holler_Cache_Control_Plugin();
