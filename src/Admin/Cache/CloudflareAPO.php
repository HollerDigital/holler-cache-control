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
            $result = Cloudflare::purge_cache();

            if ($result['success']) {
                return array(
                    'success' => true,
                    'message' => __('Cloudflare APO cache purged successfully.', 'holler-cache-control')
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
}
