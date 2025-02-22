<?php
/**
 * Handles Cloudflare APO (Automatic Platform Optimization) functionality.
 *
 * @package HollerCacheControl
 */

namespace Holler\CacheControl\Admin\Cache;

class CloudflareAPO {
    /**
     * Get APO configuration status
     *
     * @return array Status information
     */
    public static function get_status() {
        $apo_enabled = get_option('holler_cache_cloudflare_apo_enabled', false);
        $credentials = Cloudflare::get_credentials();
        $is_configured = !empty($credentials['email']) && !empty($credentials['api_key']) && !empty($credentials['zone_id']);

        return array(
            'enabled' => $apo_enabled,
            'configured' => $is_configured,
            'status' => $apo_enabled && $is_configured ? 'active' : ($is_configured ? 'disabled' : 'not_configured'),
            'message' => $apo_enabled && $is_configured ? 
                __('Cloudflare APO is active.', 'holler-cache-control') : 
                ($is_configured ? __('Cloudflare APO is disabled.', 'holler-cache-control') : __('Cloudflare APO is not configured.', 'holler-cache-control'))
        );
    }

    /**
     * Purge APO cache
     *
     * @return array Result of the purge operation
     */
    public static function purge_cache() {
        try {
            $credentials = Cloudflare::get_credentials();
            
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

            // First purge everything
            $response = wp_remote_post($api->get_api_endpoint() . '/zones/' . $credentials['zone_id'] . '/purge_cache', array(
                'headers' => $api->get_headers(),
                'body' => json_encode(array('purge_everything' => true))
            ));

            // Then specifically purge APO cache for the site
            $apo_response = wp_remote_post($api->get_api_endpoint() . '/zones/' . $credentials['zone_id'] . '/purge_cache', array(
                'headers' => $api->get_headers(),
                'body' => json_encode(array(
                    'files' => array(
                        get_site_url() . '/',
                        get_site_url() . '/index.php'
                    )
                ))
            ));

            if (is_wp_error($response) || is_wp_error($apo_response)) {
                return array(
                    'success' => false,
                    'message' => is_wp_error($response) ? $response->get_error_message() : $apo_response->get_error_message()
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $apo_body = json_decode(wp_remote_retrieve_body($apo_response), true);

            if (!$body['success'] || !$apo_body['success']) {
                $message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 
                    (isset($apo_body['errors'][0]['message']) ? $apo_body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control'));
                return array(
                    'success' => false,
                    'message' => $message
                );
            }

            return array(
                'success' => true,
                'message' => __('Cloudflare APO cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}
