<?php
/**
 * The core plugin class.
 *
 * @package HollerCacheControl
 */

class Holler_Cache_Control {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @var Holler_Cache_Control_Loader
     */
    protected $loader;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of this plugin.
     */
    protected $version;

    /**
     * The name of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The name of this plugin.
     */
    protected $plugin_name;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        if (defined('HOLLER_CACHE_CONTROL_VERSION')) {
            $this->version = HOLLER_CACHE_CONTROL_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'holler-cache-control';

        // Setup auto-purge hooks
        $this->setup_auto_purge();

        $this->load_dependencies();
        $this->define_admin_hooks();
        
        // Initialize Cloudflare cache headers
        \HollerCacheControl\Cache\Cloudflare::init();
    }

    /**
     * Auto-purge Cloudflare cache on content updates
     */
    private function setup_auto_purge() {
        // Post/page is updated or published
        add_action('save_post', array($this, 'purge_on_save'), 10, 3);
        add_action('wp_trash_post', array($this, 'purge_on_save'), 10, 1);
        add_action('publish_post', array($this, 'purge_on_save'), 10, 1);
        
        // Elementor updates
        add_action('elementor/editor/after_save', array($this, 'purge_on_elementor_save'), 10, 2);
        add_action('elementor/core/files/clear_cache', array($this, 'purge_cloudflare_cache'));
        
        // Clear cache when switching themes
        add_action('switch_theme', array($this, 'purge_cloudflare_cache'));
        
        // Clear cache when widgets are updated
        add_action('update_option_sidebars_widgets', array($this, 'purge_cloudflare_cache'));
        
        // Clear cache when customizer is updated
        add_action('customize_save_after', array($this, 'purge_cloudflare_cache'));
    }

    /**
     * Purge cache when a post is saved/updated
     */
    public function purge_on_save($post_id, $post = null, $update = null) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $this->purge_cloudflare_cache();
    }

    /**
     * Purge cache when Elementor saves
     */
    public function purge_on_elementor_save($post_id, $editor_data = null) {
        $this->purge_cloudflare_cache();
    }

    /**
     * Purge Cloudflare cache
     */
    public function purge_cloudflare_cache() {
        try {
            // Purge Cloudflare cache
            \HollerCacheControl\Cache\Cloudflare::purge();
            
            // Add admin notice
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>Cloudflare cache has been purged.</p>';
                echo '</div>';
            });
            
        } catch (\Exception $e) {
            // Log error and show admin notice
            error_log('Cloudflare cache purge failed: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>Failed to purge Cloudflare cache: ' . esc_html($e->getMessage()) . '</p>';
                echo '</div>';
            });
        }
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        require_once HOLLER_CACHE_CONTROL_DIR . 'includes/class-holler-cache-control-loader.php';
        require_once HOLLER_CACHE_CONTROL_DIR . 'includes/class-holler-cache-control-admin.php';
        require_once HOLLER_CACHE_CONTROL_DIR . 'includes/class-holler-cache-control-cloudflare.php';

        $this->loader = new Holler_Cache_Control_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks() {
        $plugin_admin = new Holler_Cache_Control_Admin();

        // Admin menu and settings
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');

        // Admin bar
        $this->loader->add_action('admin_bar_menu', $plugin_admin, 'add_admin_bar_menu', 100);

        // AJAX handlers
        $this->loader->add_action('wp_ajax_purge_all_caches', $plugin_admin, 'handle_purge_all_caches');
        
        // Admin notices
        $this->loader->add_action('admin_notices', $plugin_admin, 'display_admin_notices');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }
}
