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
        
        // Define plugin icons
        $info->icons = array(
            '1x'      => plugins_url( 'assets/img/icon-128x128.png', HOLLER_CACHE_CONTROL_FILE ),
            '2x'      => plugins_url( 'assets/img/icon-256x256.png', HOLLER_CACHE_CONTROL_FILE ),
            'default' => plugins_url( 'assets/img/icon-128x128.png', HOLLER_CACHE_CONTROL_FILE ),
        );
        
        // Add banners for professional appearance
        $info->banners = array(
            'low'  => plugins_url('assets/img/icon-256x256.png', HOLLER_CACHE_CONTROL_FILE),
            'high' => plugins_url('assets/img/icon-256x256.png', HOLLER_CACHE_CONTROL_FILE),
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
        return '<p>Holler Cache Control is a comprehensive cache management plugin designed specifically for GridPane-hosted WordPress sites. <strong>Version 1.6.0</strong> brings enhanced reliability, robust server compatibility, and improved GridPane integration with production-ready stability.</p>
        
        <h4>🚀 New in v1.6.0 - Enhanced Reliability</h4>
        <ul>
            <li><strong>🛠️ Robust System Execution</strong> - 4 fallback methods ensure compatibility across all server configurations</li>
            <li><strong>🔐 Enhanced Redis Authentication</strong> - Full support for GridPane Redis ACL with username+password</li>
            <li><strong>📊 Comprehensive Cache Detection</strong> - Detects both FastCGI and Redis page caching methods</li>
            <li><strong>🔥 Production Bug Fixes</strong> - Resolved critical namespace errors and AJAX display issues</li>
            <li><strong>🌐 Server-Agnostic Design</strong> - Works reliably regardless of PHP security restrictions</li>
        </ul>
        
        <h4>Core Features</h4>
        <ul>
            <li><strong>Unified Cache Management</strong> - Control all cache layers from a single interface</li>
            <li><strong>One-Click Cache Purging</strong> - Clear all caches with detailed success/error feedback</li>
            <li><strong>Admin Bar Integration</strong> - Quick cache controls directly from the WordPress admin bar</li>
            <li><strong>Cloudflare Integration</strong> - Full Cloudflare cache and APO management with settings controls</li>
            <li><strong>GridPane CLI Integration</strong> - Reliable "gp fix perms" command execution</li>
            <li><strong>Redis Cache Support</strong> - Complete Redis Object cache integration with ACL authentication</li>
            <li><strong>Nginx FastCGI Cache</strong> - Native support for Nginx page caching with fallback detection</li>
            <li><strong>Automatic Cache Purging</strong> - Smart cache invalidation on content updates</li>
            <li><strong>Enhanced Diagnostics</strong> - Comprehensive troubleshooting and status reporting</li>
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
        return '<h4>1.6.0 - 2025-01-25</h4>
        <ul>
            <li><strong>🛠️ Added:</strong> Robust System Command Execution with 4 fallback methods (exec, shell_exec, system, passthru)</li>
            <li><strong>🔐 Added:</strong> Enhanced GridPane Redis Authentication with ACL username+password support</li>
            <li><strong>📊 Added:</strong> Comprehensive Cache Detection for both FastCGI and Redis page caching methods</li>
            <li><strong>🔧 Added:</strong> GridPane CLI Integration with reliable "gp fix perms" command execution</li>
            <li><strong>🔥 Fixed:</strong> Critical production bugs - exec() namespace errors, AJAX "Object" errors, Redis auth failures</li>
            <li><strong>🚀 Enhanced:</strong> Production-ready stability with server-agnostic design across PHP configurations</li>
            <li><strong>🛡️ Security:</strong> Proper namespace usage prevents fatal errors on restricted servers</li>
            <li><strong>📱 UX:</strong> Clear, actionable error messages replace confusing technical errors</li>
        </ul>
        
        <h4>1.5.0 - 2025-01-25</h4>
        <ul>
            <li><strong>Added:</strong> Comprehensive Cloudflare Settings Controls with real-time updates</li>
            <li><strong>Added:</strong> Enhanced Cloudflare Diagnostics and standalone AJAX system</li>
            <li><strong>Enhanced:</strong> Professional UI with toggle switches and responsive design</li>
        </ul>
        
        <h4>1.4.0 - 2024-12-24</h4>
        <ul>
            <li><strong>Added:</strong> Asynchronous Cache Purging for AJAX requests</li>
            <li><strong>Added:</strong> Enhanced Smart Detection for Elementor compatibility</li>
            <li><strong>Added:</strong> WordPress Cron Integration for background operations</li>
        </ul>
        
        <h4>1.3.3 - 2025-01-24</h4>
        <ul>
            <li><strong>Added:</strong> Cloudflare Development Mode Toggle with one-click enable/disable</li>
            <li><strong>Added:</strong> Real-time status display for development mode</li>
            <li><strong>Enhanced:</strong> Cloudflare settings page with development mode status</li>
        </ul>';
    }
}
