<?php
namespace Holler\CacheControl\Admin\Cache;

/**
 * Manages all cache operations
 */
class CacheManager {
    /**
     * Purge Nginx cache
     */
    public function purge_nginx_cache() {
        try {
            if (!function_exists('fastcgi_cache_purge')) {
                return array(
                    'success' => false,
                    'message' => __('Nginx FastCGI Cache Purge module not installed.', 'holler-cache-control')
                );
            }

            fastcgi_cache_purge();
            return array(
                'success' => true,
                'message' => __('Nginx cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Nginx purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Purge Redis cache
     */
    public function purge_redis_cache() {
        try {
            if (!class_exists('Redis')) {
                return array(
                    'success' => false,
                    'message' => __('Redis extension not installed.', 'holler-cache-control')
                );
            }

            global $redis;
            if (!$redis) {
                return array(
                    'success' => false,
                    'message' => __('Redis connection not available.', 'holler-cache-control')
                );
            }

            $redis->flushAll();
            return array(
                'success' => true,
                'message' => __('Redis cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Redis purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Purge Cloudflare cache
     */
    public function purge_cloudflare_cache() {
        try {
            $cf = new CloudflareAPI();
            return $cf->purge_cache();
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Cloudflare purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Purge Cloudflare APO cache
     */
    public function purge_apo_cache() {
        try {
            $cf = new CloudflareAPI();
            return $cf->purge_apo_cache();
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Cloudflare APO purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Purge all caches
     */
    public function purge_all_caches() {
        $results = array();
        $errors = array();
        
        // Purge Nginx cache
        $result = $this->purge_nginx_cache();
        $results['nginx'] = $result;
        if (!$result['success']) {
            $errors[] = "Nginx: " . $result['message'];
        }
        
        // Purge Redis cache
        $result = $this->purge_redis_cache();
        $results['redis'] = $result;
        if (!$result['success']) {
            $errors[] = "Redis: " . $result['message'];
        }
        
        // Purge Cloudflare cache
        $result = $this->purge_cloudflare_cache();
        $results['cloudflare'] = $result;
        if (!$result['success']) {
            $errors[] = "Cloudflare: " . $result['message'];
        }
        
        // Purge Cloudflare APO cache
        $result = $this->purge_apo_cache();
        $results['cloudflare_apo'] = $result;
        if (!$result['success']) {
            $errors[] = "Cloudflare APO: " . $result['message'];
        }
        
        // Store results for status polling
        update_option('holler_cache_control_last_purge_results', array(
            'timestamp' => time(),
            'results' => $results
        ));
        
        // Return overall success/failure
        if (empty($errors)) {
            return array(
                'success' => true,
                'message' => 'All caches cleared successfully'
            );
        } else {
            return array(
                'success' => false,
                'message' => "Failed to clear some caches:\n" . implode("\n", $errors)
            );
        }
    }
}
