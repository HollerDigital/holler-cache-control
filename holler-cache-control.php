<?php
/**
 * Plugin Name:       Holler Cache Control
 * Plugin URI:        https://github.com/HollerDigital/holler-cache-control
 * Description:       Control Nginx FastCGI Cache, Redis Object Cache, and Cloudflare Cache from the WordPress admin. Designed for GridPane Hosted Sites
 * Version:           1.6.4
 * Author:           Holler Digital
 * Author URI:       https://hollerdigital.com/
 * License:          GPL-2.0+
 * License URI:      http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:      holler-cache-control
 * Domain Path:      /languages
 * GitHub Plugin URI: HollerDigital/holler-cache-control
 * Update URI:       https://github.com/HollerDigital/holler-cache-control
 * Requires at least: 5.0
 * Tested up to:     6.4
 * Requires PHP:     7.4
 * Network:          false
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Cloudflare credentials are now managed through wp-config.php constants or admin settings
// Priority: wp-config.php constants > admin UI settings
// No hardcoded credentials in plugin files for security

/**
 * Currently plugin version.
 */
define('HOLLER_CACHE_CONTROL_VERSION', '1.6.4');

/**
 * Plugin directory
 */
define('HOLLER_CACHE_CONTROL_DIR', plugin_dir_path(__FILE__));

// Load helper functions
require_once HOLLER_CACHE_CONTROL_DIR . 'includes/helper-functions.php';

// Initialize plugin updater (only if library is available)
if (file_exists(HOLLER_CACHE_CONTROL_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php')) {
    // Include the plugin updater class
    require_once HOLLER_CACHE_CONTROL_DIR . 'src/Admin/PluginUpdater.php';
    
    // Initialize the plugin updater
    $updater = new Holler_Cache_Control_Plugin_Updater(
        'https://github.com/HollerDigital/holler-cache-control',
        __FILE__,
        'holler-cache-control',
        'master'
    );
    
    // Optional: If you're using a private repository, specify the access token like this:
    // $updater->set_authentication('your-token-here');
} else {
    // Log that automatic updates are not available
    error_log('Holler Cache Control: plugin-update-checker library not found. Automatic updates disabled.');
}

/**
 * Autoload classes
 */
spl_autoload_register(function ($class) {
    // Project-specific namespace prefixes
    $prefixes = [
        'HollerCacheControl\\' => __DIR__ . '/src/',
        'Holler\\CacheControl\\' => __DIR__ . '/src/'
    ];

    foreach ($prefixes as $prefix => $base_dir) {
        // Check if the class uses this namespace prefix
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        // Get the relative class name
        $relative_class = substr($class, $len);

        // Replace namespace separators with directory separators
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Load WP-CLI commands if available
if (defined('WP_CLI') && WP_CLI) {
    require_once HOLLER_CACHE_CONTROL_DIR . 'src/CLI/bootstrap.php';
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
        // Load core system files first
        require_once HOLLER_CACHE_CONTROL_DIR . 'src/Core/ErrorHandler.php';
        require_once HOLLER_CACHE_CONTROL_DIR . 'src/Core/CachePathDetector.php';
        
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
        // Include standalone AJAX handler
        require_once plugin_dir_path(__FILE__) . 'cloudflare-ajax.php';

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
        // Load text domain for translations
        add_action('init', function() {
            load_plugin_textdomain(
                'holler-cache-control',
                false,
                dirname(plugin_basename(__FILE__)) . '/languages'
            );
        });

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
        // Disable Slack integration by default (can be re-enabled later if needed)
        if (get_option('holler_cache_slack_disabled') === false) {
            update_option('holler_cache_slack_disabled', true);
        }
        
        // Add any other activation tasks here
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
