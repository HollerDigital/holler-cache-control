<?php
namespace Holler\CacheControl\Admin\Cache;

/**
 * Handles Cloudflare cache functionality
 */
class Cloudflare {
    /**
     * Initialize Cloudflare cache headers
     */
    public static function init() {
        if (!is_admin()) {
            header('Cache-Control: public, max-age=14400');
            // header('X-Robots-Tag: noarchive');
        }
    }

    /**
     * Purge Cloudflare cache
     *
     * @return array Purge result
     */
    public static function purge() {
        return self::purge_cache();
    }

    /**
     * Get Cloudflare configuration status with credential source information
     *
     * @return array Status information including credential sources
     */
    public static function get_status() {
        return StatusCache::get_status('cloudflare', [__CLASS__, 'get_fresh_status']);
    }
    
    /**
     * Get fresh Cloudflare status (called by StatusCache when cache is expired)
     *
     * @return array Status information including credential sources
     */
    public static function get_fresh_status() {
        $credentials = self::get_credentials();
        $is_configured = !empty($credentials['email']) && !empty($credentials['api_key']) && !empty($credentials['zone_id']);
        
        // Build status message with source information
        $message = '';
        $details = array();
        
        if ($is_configured) {
            if ($credentials['all_from_config']) {
                $message = __('Cloudflare cache is active (credentials from wp-config.php).', 'holler-cache-control');
            } else {
                $config_count = 0;
                $admin_count = 0;
                foreach ($credentials['sources'] as $source) {
                    if ($source === 'wp-config') $config_count++;
                    else $admin_count++;
                }
                
                if ($config_count > 0 && $admin_count > 0) {
                    $message = sprintf(
                        __('Cloudflare cache is active (mixed sources: %d from wp-config.php, %d from admin settings).', 'holler-cache-control'),
                        $config_count,
                        $admin_count
                    );
                } else if ($admin_count > 0) {
                    $message = __('Cloudflare cache is active (credentials from admin settings).', 'holler-cache-control');
                }
            }
            
            // Get detailed Cloudflare settings if configured
            try {
                // Get development mode status
                $dev_mode = self::get_development_mode();
                if ($dev_mode && isset($dev_mode['value'])) {
                    $details['Development Mode'] = $dev_mode['value'] === 'on' ? 'Enabled' : 'Disabled';
                    if ($dev_mode['value'] === 'on' && isset($dev_mode['time_remaining'])) {
                        $hours = floor($dev_mode['time_remaining'] / 3600);
                        $minutes = floor(($dev_mode['time_remaining'] % 3600) / 60);
                        $details['Dev Mode Remaining'] = $hours . 'h ' . $minutes . 'm';
                    }
                }
                
                // Add credential source info
                $details['Email Source'] = $credentials['sources']['email_source'] === 'wp-config' ? 'wp-config.php' : 'Admin Settings';
                $details['API Key Source'] = $credentials['sources']['api_key_source'] === 'wp-config' ? 'wp-config.php' : 'Admin Settings';
                $details['Zone ID Source'] = $credentials['sources']['zone_id_source'] === 'wp-config' ? 'wp-config.php' : 'Admin Settings';
                
            } catch (Exception $e) {
                error_log('Holler Cache Control: Error getting Cloudflare details: ' . $e->getMessage());
                $details['Status'] = 'Connected (details unavailable)';
            }
        } else {
            $message = __('Cloudflare credentials not configured. Add constants to wp-config.php or configure via admin settings.', 'holler-cache-control');
        }
        
        $status_info = array(
            'enabled' => true,
            'configured' => $is_configured,
            'status' => $is_configured ? 'active' : 'not_configured',
            'message' => $message,
            'credential_sources' => $credentials['sources'] ?? array(),
            'all_from_config' => $credentials['all_from_config'] ?? false
        );
        
        if (!empty($details)) {
            $status_info['details'] = $details;
        }
        
        return $status_info;
    }

    /**
     * Get Cloudflare credentials with priority system
     * 
     * Priority order:
     * 1. wp-config.php constants (CLOUDFLARE_EMAIL, CLOUDFLARE_API_KEY, CLOUDFLARE_ZONE_ID)
     * 2. WordPress admin settings (stored in options table)
     * 
     * This allows for secure credential management via wp-config.php while maintaining
     * admin UI fallback for easier setup.
     *
     * @return array Credentials array with source information
     */
    public static function get_credentials() {
        // Check wp-config constants first (preferred method)
        $email = defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : get_option('cloudflare_email', '');
        $api_key = defined('CLOUDFLARE_API_KEY') ? CLOUDFLARE_API_KEY : get_option('cloudflare_api_key', '');
        $zone_id = defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : get_option('cloudflare_zone_id', '');
        
        // Determine source for each credential
        $sources = array(
            'email_source' => defined('CLOUDFLARE_EMAIL') ? 'wp-config' : 'admin_settings',
            'api_key_source' => defined('CLOUDFLARE_API_KEY') ? 'wp-config' : 'admin_settings',
            'zone_id_source' => defined('CLOUDFLARE_ZONE_ID') ? 'wp-config' : 'admin_settings'
        );

        return array(
            'email' => trim($email),
            'api_key' => trim($api_key),
            'zone_id' => trim($zone_id),
            'sources' => $sources,
            'all_from_config' => defined('CLOUDFLARE_EMAIL') && defined('CLOUDFLARE_API_KEY') && defined('CLOUDFLARE_ZONE_ID')
        );
    }

    /**
     * Verify Cloudflare credentials
     *
     * @param string $email Cloudflare email
     * @param string $api_key Cloudflare API key
     * @param string $zone_id Cloudflare zone ID
     * @return array Verification result
     */
    public static function verify_credentials($email, $api_key, $zone_id) {
        try {
            $api = new CloudflareAPI($email, $api_key, $zone_id);
            $result = $api->test_connection();
            
            if ($result['success']) {
                return array(
                    'success' => true,
                    'message' => __('Cloudflare credentials verified successfully.', 'holler-cache-control')
                );
            }
            
            return array(
                'success' => false,
                'message' => $result['message']
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Check and configure Cloudflare settings
     *
     * @return array Configuration status
     */
    public static function check_and_configure_settings() {
        $credentials = self::get_credentials();
        
        if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
            return array(
                'success' => false,
                'message' => __('Cloudflare credentials not configured.', 'holler-cache-control')
            );
        }

        try {
            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            // Test connection first
            $connection = $api->test_connection();
            if (!$connection['success']) {
                return array(
                    'success' => false,
                    'message' => $connection['message']
                );
            }

            // Get current settings
            $settings = $api->get_all_settings();

            // Check if we should apply recommended settings
            $apply_recommended = get_option('cloudflare_auto_optimize', false);
            if ($apply_recommended) {
                $optimization_results = $api->apply_recommended_settings();
                $settings['optimization_results'] = $optimization_results;
            }

            return array(
                'success' => true,
                'settings' => $settings,
                'message' => __('Successfully retrieved Cloudflare settings.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Cloudflare settings check error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Purge Cloudflare cache
     *
     * @return array Purge result
     */
    public static function purge_cache() {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'message' => __('Cloudflare credentials not configured.', 'holler-cache-control')
                );
            }

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            $response = wp_remote_post($api->get_api_endpoint() . '/zones/' . $credentials['zone_id'] . '/purge_cache', array(
                'headers' => $api->get_headers(),
                'body' => json_encode(array('purge_everything' => true))
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from Cloudflare.', 'holler-cache-control')
                );
            }

            if (!$body['success']) {
                $message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control');
                return array(
                    'success' => false,
                    'message' => $message
                );
            }

            return array(
                'success' => true,
                'message' => __('Cloudflare cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Cloudflare cache purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Purge specific URLs from Cloudflare cache
     *
     * @param array $urls URLs to purge
     * @return array Purge result
     */
    public static function purge_urls($urls) {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'message' => __('Cloudflare credentials not configured.', 'holler-cache-control')
                );
            }

            if (empty($urls) || !is_array($urls)) {
                return array(
                    'success' => false,
                    'message' => __('No URLs provided for purging.', 'holler-cache-control')
                );
            }

            // Cloudflare allows up to 30 URLs per request
            $url_chunks = array_chunk($urls, 30);
            $results = [];
            $total_purged = 0;

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            foreach ($url_chunks as $chunk) {
                $response = wp_remote_post($api->get_api_endpoint() . '/zones/' . $credentials['zone_id'] . '/purge_cache', array(
                    'headers' => $api->get_headers(),
                    'body' => json_encode(array('files' => $chunk))
                ));

                if (is_wp_error($response)) {
                    error_log('Holler Cache Control - Cloudflare URL purge error: ' . $response->get_error_message());
                    continue;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!$body || !isset($body['success'])) {
                    error_log('Holler Cache Control - Invalid Cloudflare response for URL purge');
                    continue;
                }

                if ($body['success']) {
                    $total_purged += count($chunk);
                    $results[] = sprintf(__('Purged %d URLs successfully.', 'holler-cache-control'), count($chunk));
                } else {
                    $message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control');
                    error_log('Holler Cache Control - Cloudflare URL purge failed: ' . $message);
                }
            }

            if ($total_purged > 0) {
                return array(
                    'success' => true,
                    'message' => sprintf(__('Cloudflare cache purged for %d URLs.', 'holler-cache-control'), $total_purged),
                    'urls_purged' => $total_purged
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Failed to purge any URLs from Cloudflare cache.', 'holler-cache-control')
                );
            }

        } catch (\Exception $e) {
            error_log('Holler Cache Control - Cloudflare URL purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update browser cache TTL
     *
     * @param int $ttl TTL in seconds
     * @return array Update result
     */
    public static function update_browser_cache_ttl($ttl = 14400) {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'message' => __('Cloudflare credentials not configured.', 'holler-cache-control')
                );
            }

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            return $api->update_browser_cache_ttl($ttl);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to update browser cache TTL: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update Always Online setting
     *
     * @param string $value 'on' or 'off'
     * @return array Update result
     */
    public static function update_always_online($value = 'on') {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'message' => __('Cloudflare credentials not configured.', 'holler-cache-control')
                );
            }

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            return $api->update_always_online($value);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to update Always Online setting: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update Rocket Loader setting
     *
     * @param string $value 'on' or 'off'
     * @return array Update result
     */
    public static function update_rocket_loader($value = 'on') {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'message' => __('Cloudflare credentials not configured.', 'holler-cache-control')
                );
            }

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            return $api->update_rocket_loader($value);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to update Rocket Loader setting: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update development mode setting
     *
     * @param string $value 'on' or 'off'
     * @return array Update result
     */
    public static function update_development_mode($value = 'off') {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'message' => __('Cloudflare credentials not configured.', 'holler-cache-control')
                );
            }

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            return $api->update_development_mode($value);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to update development mode: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get development mode status
     *
     * @return array Development mode status
     */
    public static function get_development_mode() {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'value' => 'off',
                    'message' => __('Cloudflare credentials not configured.', 'holler-cache-control')
                );
            }

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            $result = $api->get_development_mode();
            return array(
                'success' => true,
                'value' => $result['value'],
                'message' => $result['value'] === 'on' ? 
                    __('Development mode is currently enabled.', 'holler-cache-control') :
                    __('Development mode is currently disabled.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get development mode: ' . $e->getMessage());
            return array(
                'success' => false,
                'value' => 'off',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get APO (Automatic Platform Optimization) settings
     *
     * @return array APO settings and status
     */
    public static function get_apo_info() {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return false;
            }

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            // Get APO settings
            $response = wp_remote_get($api->get_api_endpoint() . '/zones/' . $credentials['zone_id'] . '/settings/automatic_platform_optimization', array(
                'headers' => $api->get_headers()
            ));

            if (is_wp_error($response)) {
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['result'])) {
                return false;
            }

            $apo_settings = $body['result'];
            $is_enabled = $apo_settings['value'];

            // Get cache analytics if APO is enabled
            $cache_stats = array();
            if ($is_enabled) {
                $analytics_response = wp_remote_get($api->get_api_endpoint() . '/zones/' . $credentials['zone_id'] . '/analytics/dashboard', array(
                    'headers' => $api->get_headers(),
                    'body' => array(
                        'since' => '-1440', // Last 24 hours
                        'continuous' => true
                    )
                ));

                if (!is_wp_error($analytics_response)) {
                    $analytics = json_decode(wp_remote_retrieve_body($analytics_response), true);
                    if ($analytics && isset($analytics['result'])) {
                        $cache_stats = array(
                            'requests' => $analytics['result']['totals']['requests']['all'],
                            'cached_requests' => $analytics['result']['totals']['requests']['cached'],
                            'cache_hit_rate' => round(($analytics['result']['totals']['requests']['cached'] / $analytics['result']['totals']['requests']['all']) * 100, 2) . '%'
                        );
                    }
                }
            }

            return array(
                'enabled' => $is_enabled,
                'cache_by_device_type' => $apo_settings['value'] && isset($apo_settings['cache_by_device_type']) ? $apo_settings['cache_by_device_type'] : false,
                'cache_by_location' => $apo_settings['value'] && isset($apo_settings['cache_by_location']) ? $apo_settings['cache_by_location'] : false,
                'cache_stats' => $cache_stats
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get APO info: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get security settings from Cloudflare
     *
     * @return array Security settings
     */
    public static function get_security_settings() {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'message' => 'Cloudflare credentials not configured'
                );
            }

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            return $api->get_security_settings();
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get security settings: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update security setting
     *
     * @param string $setting Security setting name
     * @param mixed $value Setting value
     * @return array Result of update operation
     */
    public static function update_security_setting($setting, $value) {
        try {
            $credentials = self::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'message' => 'Cloudflare credentials not configured'
                );
            }

            $api = new CloudflareAPI(
                $credentials['email'],
                $credentials['api_key'],
                $credentials['zone_id']
            );

            switch ($setting) {
                case 'security_level':
                    return $api->update_security_level($value);
                case 'bot_fight_mode':
                    return $api->update_bot_fight_mode($value === 'on');
                case 'browser_check':
                    return $api->update_browser_integrity_check($value === 'on');
                case 'email_obfuscation':
                    return $api->update_email_obfuscation($value === 'on');
                default:
                    return array(
                        'success' => false,
                        'message' => 'Invalid security setting'
                    );
            }
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to update security setting: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}
