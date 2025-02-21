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
            header('X-Robots-Tag: noarchive');
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
     * Get Cloudflare configuration status
     *
     * @return array Status information
     */
    public static function get_status() {
        $credentials = self::get_credentials();
        $is_configured = !empty($credentials['email']) && !empty($credentials['api_key']) && !empty($credentials['zone_id']);
        
        return array(
            'enabled' => true,
            'configured' => $is_configured,
            'status' => $is_configured ? 'active' : 'not_configured',
            'message' => $is_configured ? 
                __('Cloudflare cache is active.', 'holler-cache-control') : 
                __('Cloudflare credentials not configured.', 'holler-cache-control')
        );
    }

    /**
     * Get Cloudflare credentials
     *
     * @return array Credentials array
     */
    public static function get_credentials() {
        $email = defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : get_option('cloudflare_email');
        $api_key = defined('CLOUDFLARE_API_KEY') ? CLOUDFLARE_API_KEY : get_option('cloudflare_api_key');
        $zone_id = defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : get_option('cloudflare_zone_id');

        return array(
            'email' => $email,
            'api_key' => $api_key,
            'zone_id' => $zone_id
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
}
