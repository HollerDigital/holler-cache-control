<?php
namespace Holler\CacheControl;

/**
 * The core plugin class.
 *
 * @package Holler\CacheControl
 */

class HollerCacheControl {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @var Holler\CacheControl\Loader
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     */
    private $plugin_name;

    /**
     * The current version of the plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Setup auto-purge hooks
        $this->setup_auto_purge();

        $this->load_dependencies();
        $this->define_admin_hooks();
        
        // Initialize Cloudflare cache headers
        \Holler\CacheControl\Admin\Cache\Cloudflare::init();
    }

    /**
     * Auto-purge Cloudflare cache on content updates
     */
    private function setup_auto_purge() {
        // Read the same auto-purge settings used by Admin\Tools
        $settings = wp_parse_args(get_option('holler_cache_control_auto_purge', array()), array(
            'purge_on_post_save' => true,
            'purge_on_post_delete' => false,
            'purge_on_post_trash' => false,
            'purge_on_menu_update' => false,
            'purge_on_widget_update' => false,
            'purge_on_theme_switch' => false,
            'purge_on_customizer_save' => false,
            'purge_on_plugin_activation' => false,
            'purge_on_core_update' => false,
            'purge_daily_scheduled' => false
        ));

        // Post/page published (Cloudflare-only legacy path) — respect settings
        if (!empty($settings['purge_on_post_save'])) {
            add_action('publish_post', array($this, 'purge_on_save'), 10, 1);
        }

        // Elementor saves — opt-in via filter (default disabled)
        $enable_elementor_autopurge = apply_filters('holler_cache_control_enable_elementor_autopurge', false);
        if ($enable_elementor_autopurge) {
            add_action('elementor/editor/after_save', array($this, 'purge_on_elementor_save'), 10, 2);
        }

        // Theme switch (Cloudflare-only legacy path) — respect settings
        if (!empty($settings['purge_on_theme_switch'])) {
            add_action('switch_theme', array($this, 'purge_cloudflare_cache'));
        }
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
            \Holler\CacheControl\Admin\Cache\Cloudflare::purge();
            
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
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/helper-functions.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-holler-cache-control-loader.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'src/Admin/Tools.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'src/Admin/Cache/AjaxHandler.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'src/Admin/Cache/CacheManager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'src/Admin/Cache/CloudflareAPI.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'src/Admin/Cache/CloudflareAPO.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'src/Admin/Cache/Nginx.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'src/Admin/Cache/Redis.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'src/Admin/Cache/Cloudflare.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-holler-cache-control-updater.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-holler-cache-slack.php';

        // Create the loader that will be used to register hooks with WordPress
        $this->loader = new \Holler\CacheControl\Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks() {
        $plugin_admin = new \Holler\CacheControl\Admin\Tools($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu', 99);
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        $this->loader->add_action('admin_bar_menu', $plugin_admin, 'admin_bar_menu', 100);
        $this->loader->add_action('admin_head', $plugin_admin, 'admin_bar_styles');

        // Add AJAX handlers
        $this->loader->add_action('wp_ajax_holler_purge_cache', $plugin_admin, 'handle_purge_cache_ajax');
        $this->loader->add_action('wp_ajax_holler_purge_all_caches', $plugin_admin, 'handle_purge_all_caches_ajax');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }
}
