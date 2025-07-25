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
        return StatusCache::get_status('cloudflare_apo', [__CLASS__, 'get_fresh_status']);
    }
    
    /**
     * Get fresh CloudflareAPO status (called by StatusCache when cache is expired)
     *
     * @return array Status information
     */
    public static function get_fresh_status() {
        $apo_enabled = get_option('holler_cache_cloudflare_apo_enabled', false);
        $credentials = Cloudflare::get_credentials();
        $is_configured = !empty($credentials['email']) && !empty($credentials['api_key']) && !empty($credentials['zone_id']);
        $details = array();

        if ($is_configured && $apo_enabled) {
            // Get detailed APO information
            try {
                $apo_info = Cloudflare::get_apo_info();
                if ($apo_info && is_array($apo_info)) {
                    $details['APO Status'] = $apo_info['enabled'] ? 'Active' : 'Inactive';
                    
                    if (isset($apo_info['cache_by_device_type'])) {
                        $details['Device Type Caching'] = $apo_info['cache_by_device_type'] ? 'Enabled' : 'Disabled';
                    }
                    
                    if (isset($apo_info['cache_by_location'])) {
                        $details['Location Caching'] = $apo_info['cache_by_location'] ? 'Enabled' : 'Disabled';
                    }
                    
                    // Add cache statistics if available
                    if (isset($apo_info['cache_stats']) && is_array($apo_info['cache_stats'])) {
                        $stats = $apo_info['cache_stats'];
                        if (isset($stats['requests'])) {
                            $details['Total Requests'] = number_format($stats['requests']);
                        }
                        if (isset($stats['cached_requests'])) {
                            $details['Cached Requests'] = number_format($stats['cached_requests']);
                        }
                        if (isset($stats['cache_hit_rate'])) {
                            $details['Cache Hit Rate'] = $stats['cache_hit_rate'];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Holler Cache Control: Error getting APO details: ' . $e->getMessage());
                $details['Status'] = 'Connected (details unavailable)';
            }
        } elseif ($is_configured) {
            $details['Configuration'] = 'APO available but disabled';
            $details['Enable APO'] = 'Go to Settings → Cloudflare to enable';
        }

        $status_info = array(
            'enabled' => $apo_enabled,
            'configured' => $is_configured,
            'status' => $apo_enabled && $is_configured ? 'active' : ($is_configured ? 'disabled' : 'not_configured'),
            'message' => $apo_enabled && $is_configured ? 
                __('Cloudflare APO is active.', 'holler-cache-control') : 
                ($is_configured ? __('Cloudflare APO is disabled.', 'holler-cache-control') : __('Cloudflare APO is not configured.', 'holler-cache-control'))
        );
        
        if (!empty($details)) {
            $status_info['details'] = $details;
        }
        
        return $status_info;
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

    /**
     * Purge specific URLs from Cloudflare APO cache
     *
     * @param array $urls URLs to purge
     * @return array Purge result
     */
    public static function purge_urls($urls) {
        try {
            $credentials = Cloudflare::get_credentials();
            
            if (empty($credentials['email']) || empty($credentials['api_key']) || empty($credentials['zone_id'])) {
                return array(
                    'success' => false,
                    'message' => __('Cloudflare credentials not configured.', 'holler-cache-control')
                );
            }

            if (empty($urls) || !is_array($urls)) {
                return array(
                    'success' => false,
                    'message' => __('No URLs provided for APO purging.', 'holler-cache-control')
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
                    error_log('Holler Cache Control - Cloudflare APO URL purge error: ' . $response->get_error_message());
                    continue;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!$body || !isset($body['success'])) {
                    error_log('Holler Cache Control - Invalid Cloudflare APO response for URL purge');
                    continue;
                }

                if ($body['success']) {
                    $total_purged += count($chunk);
                    $results[] = sprintf(__('APO purged %d URLs successfully.', 'holler-cache-control'), count($chunk));
                } else {
                    $message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control');
                    error_log('Holler Cache Control - Cloudflare APO URL purge failed: ' . $message);
                }
            }

            if ($total_purged > 0) {
                return array(
                    'success' => true,
                    'message' => sprintf(__('Cloudflare APO cache purged for %d URLs.', 'holler-cache-control'), $total_purged),
                    'urls_purged' => $total_purged
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Failed to purge any URLs from Cloudflare APO cache.', 'holler-cache-control')
                );
            }

        } catch (\Exception $e) {
            error_log('Holler Cache Control - Cloudflare APO URL purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}
