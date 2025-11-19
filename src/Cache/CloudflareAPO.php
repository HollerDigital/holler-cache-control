<?php
namespace HollerCacheControl\Cache;

/**
 * Handles Cloudflare APO cache operations
 */
class CloudflareAPO extends Cloudflare {
    /**
     * Log error message
     */
    protected static function log_error($message) {
        // Removed error logging
    }

    /**
     * Get APO status for GridPane environment
     *
     * @return array
     */
    public static function get_status() {
        $result = array(
            'active' => false,
            'type' => 'apo',
            'details' => __('Automatic Platform Optimization is not configured', 'holler-cache-control'),
            'cache_by_device' => false,
            'cache_by_location' => false,
            'no_html_minify' => false
        );

        try {
            if (!self::is_proxied()) {
                $result['details'] = __('Website must be proxied through Cloudflare', 'holler-cache-control');
                return $result;
            }

            $credentials = self::get_credentials();
            if (!$credentials['valid']) {
                $result['details'] = __('Cloudflare credentials not configured', 'holler-cache-control');
                return $result;
            }

            // Check APO status
            $response = \wp_remote_get(
                'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'] . '/settings/automatic_platform_optimization',
                array(
                    'headers' => array(
                        'X-Auth-Key' => $credentials['api_key'],
                        'X-Auth-Email' => $credentials['email'],
                        'Content-Type' => 'application/json'
                    )
                )
            );

            if (\is_wp_error($response)) {
                $result['details'] = $response->get_error_message();
                return $result;
            }

            $body = \json_decode(\wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                $error = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error', 'holler-cache-control');
                $result['details'] = $error;
                return $result;
            }

            // Check if APO is enabled and properly configured
            if (isset($body['result']['value'])) {
                $settings = $body['result']['value'];
                
                // Handle both object and array response formats
                if (is_array($settings)) {
                    $result['active'] = !empty($settings['enabled']);
                    $result['cache_by_device'] = !empty($settings['cache_by_device_type']);
                    $result['cache_by_location'] = !empty($settings['cache_by_location']);
                    $result['no_html_minify'] = !empty($settings['no_html_minify']);
                } else {
                    $result['active'] = !empty($settings);
                }

                if ($result['active']) {
                    $result['details'] = __('Automatic Platform Optimization is enabled', 'holler-cache-control');
                } else {
                    $result['details'] = __('Automatic Platform Optimization is disabled', 'holler-cache-control');
                }

                // Log the raw response for debugging
                self::log_error('APO Status Response: ' . print_r($body, true));
            } else {
                $result['details'] = __('Automatic Platform Optimization settings not found', 'holler-cache-control');
            }

        } catch (\Exception $e) {
            $result['details'] = $e->getMessage();
            self::log_error('APO Status Error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Purge Cloudflare APO cache
     *
     * @return array
     */
    public static function purge() {
        $result = array(
            'success' => false,
            'message' => ''
        );

        try {
            if (!defined('WP_CLI') && !\current_user_can('manage_options')) {
                throw new \Exception(__('You do not have permission to purge cache', 'holler-cache-control'));
            }

            if (!self::is_proxied()) {
                throw new \Exception(__('Website must be proxied through Cloudflare to use APO', 'holler-cache-control'));
            }

            $credentials = self::get_credentials();
            if (!$credentials['valid']) {
                throw new \Exception(__('Cloudflare credentials not configured', 'holler-cache-control'));
            }

            self::log_error('Starting APO cache purge');

            // First check current APO status
            $status_response = \wp_remote_get(
                'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'] . '/settings/automatic_platform_optimization',
                array(
                    'headers' => array(
                        'X-Auth-Key' => $credentials['api_key'],
                        'X-Auth-Email' => $credentials['email'],
                        'Content-Type' => 'application/json'
                    )
                )
            );

            if (\is_wp_error($status_response)) {
                self::log_error('Status check error: ' . $status_response->get_error_message());
                throw new \Exception($status_response->get_error_message());
            }

            $status_body = \json_decode(\wp_remote_retrieve_body($status_response), true);
            if (!$status_body || !isset($status_body['success']) || !$status_body['success']) {
                $error = isset($status_body['errors'][0]['message']) ? $status_body['errors'][0]['message'] : __('Unknown error', 'holler-cache-control');
                self::log_error('Status check failed: ' . $error);
                throw new \Exception($error);
            }

            if (!isset($status_body['result']['value']) || !$status_body['result']['value']) {
                throw new \Exception(__('Automatic Platform Optimization is not enabled in Cloudflare', 'holler-cache-control'));
            }

            $current_settings = $status_body['result']['value'];
            if (is_array($current_settings)) {
                $current_settings = array_merge(array(
                    'enabled' => true,
                    'wordpress' => true,
                    'cache_by_device_type' => false,
                    'cache_by_location' => false,
                    'hostname' => parse_url(home_url(), PHP_URL_HOST)
                ), $current_settings);
            } else {
                $current_settings = array(
                    'enabled' => true,
                    'wordpress' => true,
                    'cache_by_device_type' => false,
                    'cache_by_location' => false,
                    'hostname' => parse_url(home_url(), PHP_URL_HOST)
                );
            }

            self::log_error('Current APO settings: ' . print_r($current_settings, true));

            // First purge the Cloudflare cache
            $purge_response = \wp_remote_post(
                'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'] . '/purge_cache',
                array(
                    'headers' => array(
                        'X-Auth-Key' => $credentials['api_key'],
                        'X-Auth-Email' => $credentials['email'],
                        'Content-Type' => 'application/json'
                    ),
                    'body' => \json_encode(array(
                        'purge_everything' => true
                    ))
                )
            );

            if (\is_wp_error($purge_response)) {
                self::log_error('Cache purge error: ' . $purge_response->get_error_message());
                throw new \Exception($purge_response->get_error_message());
            }

            // Now toggle APO off and on to clear the cache
            self::log_error('Disabling APO');
            $disable_response = \wp_remote_request(
                'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'] . '/settings/automatic_platform_optimization',
                array(
                    'method' => 'PATCH',
                    'headers' => array(
                        'X-Auth-Key' => $credentials['api_key'],
                        'X-Auth-Email' => $credentials['email'],
                        'Content-Type' => 'application/json'
                    ),
                    'body' => \json_encode(array(
                        'value' => array_merge($current_settings, array('enabled' => false))
                    ))
                )
            );

            if (\is_wp_error($disable_response)) {
                self::log_error('Disable APO Error: ' . $disable_response->get_error_message());
                throw new \Exception($disable_response->get_error_message());
            }

            $disable_body = \json_decode(\wp_remote_retrieve_body($disable_response), true);
            self::log_error('Disable APO Response: ' . print_r($disable_body, true));

            // Re-enable APO with original settings
            self::log_error('Re-enabling APO');
            $enable_response = \wp_remote_request(
                'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'] . '/settings/automatic_platform_optimization',
                array(
                    'method' => 'PATCH',
                    'headers' => array(
                        'X-Auth-Key' => $credentials['api_key'],
                        'X-Auth-Email' => $credentials['email'],
                        'Content-Type' => 'application/json'
                    ),
                    'body' => \json_encode(array(
                        'value' => array_merge($current_settings, array('enabled' => true))
                    ))
                )
            );

            if (\is_wp_error($enable_response)) {
                self::log_error('Enable APO Error: ' . $enable_response->get_error_message());
                throw new \Exception($enable_response->get_error_message());
            }

            $body = \json_decode(\wp_remote_retrieve_body($enable_response), true);
            self::log_error('Enable APO Response: ' . print_r($body, true));

            if (!$body || !isset($body['success']) || !$body['success']) {
                $error = isset($body['errors'][0]['message']) 
                    ? $body['errors'][0]['message'] 
                    : __('Unknown error', 'holler-cache-control');
                self::log_error('Enable APO Failed: ' . $error);
                throw new \Exception($error);
            }

            self::log_error('APO cache purge completed successfully');
            $result['success'] = true;
            $result['message'] = __('APO cache cleared successfully', 'holler-cache-control');

        } catch (\Exception $e) {
            self::log_error('APO cache purge error: ' . $e->getMessage());
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check if site is proxied through Cloudflare
     */
    protected static function is_proxied() {
        $headers = array();
        if (isset($_SERVER['HTTP_CF_RAY'])) {
            return true;
        }

        // Check zone settings
        $credentials = self::get_credentials();
        if (!$credentials['valid']) {
            return false;
        }

        $response = \wp_remote_get(
            'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'],
            array(
                'headers' => array(
                    'X-Auth-Key' => $credentials['api_key'],
                    'X-Auth-Email' => $credentials['email'],
                    'Content-Type' => 'application/json'
                )
            )
        );

        if (\is_wp_error($response)) {
            return false;
        }

        $body = \json_decode(\wp_remote_retrieve_body($response), true);
        if (!$body || !isset($body['success']) || !$body['success']) {
            return false;
        }

        return isset($body['result']['status']) && $body['result']['status'] === 'active';
    }
}
