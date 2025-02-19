<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://hollerdigital.com
 * @since             1.0.0
 * @package           HollerCacheControl
 *
 * @wordpress-plugin
 * Plugin Name:       Holler Cache Control
 * Plugin URI:        https://github.com/hollerdigital/holler-cache-control
 * Description:       Advanced cache control for WordPress sites running on GridPane with Redis, Nginx, and Cloudflare.
 * Version:           1.2.0
 * Author:            Holler Digital
 * Author URI:        https://hollerdigital.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       holler-cache-control
 * Domain Path:       /languages
 * GitHub Plugin URI: hollerdigital/holler-cache-control
 * GitHub Branch:     master
 * Requires PHP:      7.4
 * Requires at least: 5.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin version
define('HOLLER_CACHE_CONTROL_VERSION', '1.2.0');

// Define plugin directory
define('HOLLER_CACHE_CONTROL_DIR', plugin_dir_path(__FILE__));

// Load helper functions
require_once HOLLER_CACHE_CONTROL_DIR . 'includes/helper-functions.php';

// Initialize cache control at the earliest possible point
add_action('muplugins_loaded', function() {
    if (!headers_sent()) {
        // Remove default headers to start fresh
        header_remove('Cache-Control');
        header_remove('Pragma');
        header_remove('Expires');
        header_remove('Last-Modified');
        header_remove('ETag');
        
        // Set cache headers based on the request type
        if (is_user_logged_in() || is_admin() || is_customize_preview()) {
            // No caching for logged-in users, admin pages, or customizer
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        } else {
            // Get TTL from Cloudflare APO settings or fallback to default
            $ttl = get_option('cloudflare_browser_ttl', 14400); // Default 4 hours if not set
            
            // Check if we can detect Cloudflare's actual TTL from headers
            if (isset($_SERVER['HTTP_CF_CONFIG_TTL'])) {
                $cf_ttl = intval($_SERVER['HTTP_CF_CONFIG_TTL']);
                if ($cf_ttl > 0) {
                    $ttl = $cf_ttl;
                }
            }
            
            // Public caching for anonymous users with dynamic TTL
            header(sprintf(
                'Cache-Control: public, max-age=%d, stale-while-revalidate=3600, stale-if-error=86400',
                $ttl
            ));
            
            // Set Last-Modified for conditional requests
            if (is_singular()) {
                $post = get_post();
                if ($post) {
                    $last_modified = get_post_modified_time('D, d M Y H:i:s', true);
                    if ($last_modified) {
                        header('Last-Modified: ' . $last_modified . ' GMT');
                    }
                }
            }

            // Add Cloudflare cache tags if available
            if (function_exists('is_product') && is_product()) {
                header('Cache-Tag: product');
            } elseif (is_front_page()) {
                header('Cache-Tag: frontpage');
            } elseif (is_singular('post')) {
                header('Cache-Tag: post');
            } elseif (is_singular('page')) {
                header('Cache-Tag: page');
            }
        }
    }
}, -999999);

// Autoload classes
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback autoloader
    spl_autoload_register(function ($class) {
        // Project-specific namespace prefix
        $prefix = 'HollerCacheControl\\';

        // Base directory for the namespace prefix
        $base_dir = __DIR__ . '/src/';

        // Does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // No, move to the next registered autoloader
            return;
        }

        // Get the relative class name
        $relative_class = substr($class, $len);

        // Replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Load required files
require_once HOLLER_CACHE_CONTROL_DIR . 'includes/class-holler-cache-control.php';

// Add cache control headers for REST API
add_action('rest_api_init', function() {
    // Set appropriate cache headers for REST API endpoints
    add_filter('rest_post_dispatch', function($response, $server, $request) {
        if (!is_wp_error($response)) {
            // Cache GET requests for 5 minutes, no cache for other methods
            if ($request->get_method() === 'GET') {
                $response->header('Cache-Control', 'public, max-age=300');
            } else {
                $response->header('Cache-Control', 'no-store');
            }
        }
        return $response;
    }, 10, 3);
});

// Prevent PHP session caching
@ini_set('session.cache_limiter', 'private_no_expire');

// Schedule Cloudflare TTL sync
if (!wp_next_scheduled('holler_cache_control_sync_cf_ttl')) {
    wp_schedule_event(time(), 'hourly', 'holler_cache_control_sync_cf_ttl');
}

add_action('holler_cache_control_sync_cf_ttl', function() {
    // Ensure we have admin capabilities for scheduled task
    require_once(ABSPATH . 'wp-includes/pluggable.php');
    if (!function_exists('get_users')) {
        return;
    }
    
    // Get the first admin user
    $admins = get_users(array('role' => 'administrator', 'number' => 1));
    if (!empty($admins)) {
        wp_set_current_user($admins[0]->ID);
    }
    
    \HollerCacheControl\Cache\Cloudflare::sync_browser_ttl();
});

// Register WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('holler-cache', new \HollerCacheControl\CLI\Commands());
}

// Try to include the plugin update checker if it exists
$plugin_update_checker_file = plugin_dir_path(__FILE__) . 'vendor/plugin-update-checker/plugin-update-checker.php';
if (file_exists($plugin_update_checker_file)) {
    require_once $plugin_update_checker_file;

    // Setup the update checker for public repository
    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/HollerDigital/holler-cache-control',
        __FILE__,
        'holler-cache-control'
    );

    // Set the branch that contains the stable release
    $updateChecker->setBranch('master');

    // Enable Releases instead of just tags
    $updateChecker->getVcsApi()->enableReleaseAssets();
}

// Load required files - these need to be loaded before the autoloader
$required_files = array(
    'src/Core/Loader.php',
    'src/Core/Plugin.php',
    'src/Admin/Tools.php',
    'src/Cache/Nginx.php',
    'src/Cache/Redis.php',
    'src/Cache/Cloudflare.php',
    'src/Cache/CloudflareAPO.php',
    'wp-cli.php'
);

// Check if all required files exist
$missing_files = array();
foreach ($required_files as $file) {
    $file_path = plugin_dir_path(__FILE__) . $file;
    if (!file_exists($file_path)) {
        $missing_files[] = $file;
    }
}

// If any required files are missing, show admin notice and return
if (!empty($missing_files)) {
    add_action('admin_notices', function() use ($missing_files) {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Holler Cache Control plugin is missing required files:', 'holler-cache-control'); ?></p>
            <ul>
                <?php foreach ($missing_files as $file): ?>
                    <li><?php echo esc_html($file); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><?php _e('Please reinstall the plugin or contact support.', 'holler-cache-control'); ?></p>
        </div>
        <?php
    });
    return;
}

// Load all required files
foreach ($required_files as $file) {
    require_once plugin_dir_path(__FILE__) . $file;
}

// Register autoloader for any additional plugin classes
spl_autoload_register(function ($class) {
    // Plugin namespace prefix
    $prefix = 'HollerCacheControl\\';
    $base_dir = plugin_dir_path(__FILE__) . 'src/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function run_holler_cache_control() {
    $plugin = new HollerCacheControl\Core\Plugin();
    $plugin->run();
}

run_holler_cache_control();
