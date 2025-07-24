<?php
/**
 * Plugin Updater
 *
 * @package HollerCacheControl
 * @subpackage Admin
 * @since 1.3.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Holler Cache Control Plugin Updater Class
 *
 * Handles the plugin updates from GitHub repository
 *
 * @since 1.3.0
 */
class Holler_Cache_Control_Plugin_Updater {

    /**
     * Update checker instance
     *
     * @var object
     */
    private $update_checker;

    /**
     * Constructor
     *
     * @param string $repository GitHub repository URL.
     * @param string $main_file   Main plugin file path.
     * @param string $slug        Plugin slug.
     * @param string $branch      GitHub branch to use for updates.
     */
    public function __construct($repository, $main_file, $slug, $branch = 'master') {
        // Check if plugin update checker library exists
        $update_checker_path = HOLLER_CACHE_CONTROL_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
        
        if (!file_exists($update_checker_path)) {
            // Log error and return early if library is missing
            error_log('Holler Cache Control: plugin-update-checker library not found at: ' . $update_checker_path);
            return;
        }
        
        // Load the plugin update checker library
        require_once $update_checker_path;
        
        // Check if the factory class exists after loading
        if (!class_exists('Puc_v4_Factory')) {
            error_log('Holler Cache Control: Puc_v4_Factory class not found after loading plugin-update-checker');
            return;
        }
        
        // Initialize the update checker
        try {
            $this->update_checker = Puc_v4_Factory::buildUpdateChecker(
                $repository,
                $main_file,
                $slug
            );
            
            // Set the branch that contains the stable release
            $this->update_checker->setBranch($branch);
            $this->update_checker->getVcsApi()->enableReleaseAssets();
            
        } catch (Exception $e) {
            error_log('Holler Cache Control: Failed to initialize plugin updater: ' . $e->getMessage());
            $this->update_checker = null;
        }
    }
    
    /**
     * Add icons and metadata to the update information
     *
     * @param object $info Update information object.
     * @return object Modified update information object with icons and metadata.
     */
    public function add_icons_to_update_info($info) {
        if (!is_object($info)) {
            return $info;
        }
        
        // Define plugin icons (using default WordPress plugin icon for now)
        $info->icons = array(
            '1x'      => 'https://ps.w.org/holler-cache-control/assets/icon-128x128.png',
            '2x'      => 'https://ps.w.org/holler-cache-control/assets/icon-256x256.png',
            'default' => 'https://ps.w.org/holler-cache-control/assets/icon-128x128.png',
        );
        
        // Add banners
        $info->banners = array(
            'low'  => 'https://ps.w.org/holler-cache-control/assets/banner-772x250.png',
            'high' => 'https://ps.w.org/holler-cache-control/assets/banner-1544x500.png',
        );
        
        // Add additional metadata
        $info->author = 'Holler Digital';
        $info->author_profile = 'https://hollerdigital.com/';
        $info->contributors = array('hollerdigital');
        $info->donate_link = '';
        $info->tags = array('cache', 'redis', 'cloudflare', 'nginx', 'gridpane', 'performance', 'optimization');
        $info->requires = '5.0';
        $info->tested = '6.4';
        $info->requires_php = '7.4';
        $info->rating = 100;
        $info->num_ratings = 1;
        $info->support_threads = 0;
        $info->support_threads_resolved = 0;
        $info->downloaded = 100;
        $info->last_updated = date('Y-m-d');
        $info->added = '2025-01-24';
        $info->homepage = 'https://github.com/HollerDigital/holler-cache-control';
        $info->short_description = 'Control Nginx FastCGI Cache, Redis Object Cache, and Cloudflare Cache from the WordPress admin. Designed for GridPane Hosted Sites.';
        
        // Add sections for the plugin details modal
        if (!isset($info->sections)) {
            $info->sections = array();
        }
        
        $info->sections['description'] = $this->get_description_section();
        $info->sections['installation'] = $this->get_installation_section();
        $info->sections['changelog'] = $this->get_changelog_section();
        $info->sections['faq'] = $this->get_faq_section();
        
        return $info;
    }

    /**
     * Set authentication for private repositories
     *
     * @param string $token GitHub access token.
     */
    public function set_authentication($token) {
        if (!empty($token) && $this->update_checker !== null) {
            $this->update_checker->setAuthentication($token);
        }
    }
    
    /**
     * Get description section for plugin details
     *
     * @return string HTML description content.
     */
    private function get_description_section() {
        return '<p>Holler Cache Control is a comprehensive cache management plugin designed specifically for GridPane-hosted WordPress sites. It provides a unified interface to manage multiple cache layers including Nginx FastCGI cache, Redis Object cache, and Cloudflare cache.</p>
        
        <h4>Key Features</h4>
        <ul>
            <li><strong>Unified Cache Management</strong> - Control all cache layers from a single interface</li>
            <li><strong>One-Click Cache Purging</strong> - Clear all caches with a single button click</li>
            <li><strong>Admin Bar Integration</strong> - Quick cache controls directly from the WordPress admin bar</li>
            <li><strong>Cloudflare Integration</strong> - Full Cloudflare cache and APO management</li>
            <li><strong>Development Mode Toggle</strong> - Easily enable/disable Cloudflare Development Mode</li>
            <li><strong>Redis Cache Support</strong> - Complete Redis Object cache integration</li>
            <li><strong>Nginx FastCGI Cache</strong> - Native support for Nginx page caching</li>
            <li><strong>Automatic Cache Purging</strong> - Smart cache invalidation on content updates</li>
            <li><strong>GridPane Optimized</strong> - Built specifically for GridPane hosting environment</li>
        </ul>';
    }
    
    /**
     * Get installation section for plugin details
     *
     * @return string HTML installation content.
     */
    private function get_installation_section() {
        return '<ol>
            <li>Upload the plugin files to <code>/wp-content/plugins/holler-cache-control/</code></li>
            <li>Activate the plugin through the \'Plugins\' screen in WordPress</li>
            <li>Configure your cache settings via Settings > Cache Control</li>
            <li>(Optional) Add Cloudflare credentials to wp-config.php for enhanced security</li>
        </ol>
        
        <h4>Cloudflare Configuration</h4>
        <p>Add these constants to your wp-config.php file:</p>
        <pre><code>define(\'CLOUDFLARE_EMAIL\', \'your-email@example.com\');
define(\'CLOUDFLARE_API_KEY\', \'your-api-key\');
define(\'CLOUDFLARE_ZONE_ID\', \'your-zone-id\');</code></pre>';
    }
    
    /**
     * Get FAQ section for plugin details
     *
     * @return string HTML FAQ content.
     */
    private function get_faq_section() {
        return '<h4>Is this plugin compatible with GridPane hosting?</h4>
        <p>Yes! This plugin is specifically designed and optimized for GridPane-hosted WordPress sites.</p>
        
        <h4>Can I use this plugin on other hosting providers?</h4>
        <p>While designed for GridPane, the plugin will work on other hosting providers that support Nginx FastCGI cache and Redis.</p>
        
        <h4>How do I configure Cloudflare integration?</h4>
        <p>You can configure Cloudflare credentials either through the admin interface or by adding constants to your wp-config.php file (recommended for security).</p>
        
        <h4>What is Development Mode?</h4>
        <p>Development Mode temporarily bypasses Cloudflare\'s cache for 3 hours, making it perfect for development and testing without affecting live site performance.</p>';
    }
    
    /**
     * Get changelog section for plugin details
     *
     * @return string HTML changelog content.
     */
    private function get_changelog_section() {
        return '<h4>1.3.3 - 2025-01-24</h4>
        <ul>
            <li><strong>Added:</strong> Cloudflare Development Mode Toggle with one-click enable/disable</li>
            <li><strong>Added:</strong> Real-time status display for development mode</li>
            <li><strong>Added:</strong> Interactive toggle button with loading states and visual feedback</li>
            <li><strong>Enhanced:</strong> Cloudflare settings page with development mode status</li>
        </ul>
        
        <h4>1.3.2 - 2025-01-24</h4>
        <ul>
            <li><strong>Fixed:</strong> Critical plugin-update-checker fatal error on fresh installs</li>
            <li><strong>Enhanced:</strong> Error handling and logging for update checker</li>
        </ul>
        
        <h4>1.3.1 - 2025-01-24</h4>
        <ul>
            <li><strong>Added:</strong> GitHub-based automatic plugin updates</li>
            <li><strong>Added:</strong> Plugin update checker integration</li>
            <li><strong>Enhanced:</strong> Documentation and changelog</li>
        </ul>';
    }
}
