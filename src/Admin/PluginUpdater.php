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
        // Make sure the plugin update checker is loaded
        require_once HOLLER_CACHE_CONTROL_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
        
        // Initialize the update checker
        $this->update_checker = Puc_v4_Factory::buildUpdateChecker(
            $repository,
            $main_file,
            $slug
        );
        
        // Set the branch that contains the stable release
        $this->update_checker->setBranch($branch);
        $this->update_checker->getVcsApi()->enableReleaseAssets();
        
        // Add filters to include plugin icons
        add_filter('puc_request_info_result-' . $slug, array($this, 'add_icons_to_update_info'));
    }
    
    /**
     * Add icons to the update information
     *
     * @param object $info Update information object.
     * @return object Modified update information object with icons.
     */
    public function add_icons_to_update_info($info) {
        if (!is_object($info)) {
            return $info;
        }
        
        // Define plugin icons (using default WordPress plugin icon for now)
        $info->icons = array(
            '1x'      => plugins_url('assets/img/icon-128x128.png', HOLLER_CACHE_CONTROL_DIR . 'holler-cache-control.php'),
            '2x'      => plugins_url('assets/img/icon-256x256.png', HOLLER_CACHE_CONTROL_DIR . 'holler-cache-control.php'),
            'default' => plugins_url('assets/img/icon-128x128.png', HOLLER_CACHE_CONTROL_DIR . 'holler-cache-control.php'),
        );
        
        return $info;
    }

    /**
     * Set authentication for private repositories
     *
     * @param string $token GitHub access token.
     */
    public function set_authentication($token) {
        if (!empty($token)) {
            $this->update_checker->setAuthentication($token);
        }
    }
}
