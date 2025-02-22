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
     * Purge OPcache
     */
    public static function purge_opcache() {
        try {
            if (function_exists('opcache_reset')) {
                opcache_reset();
                return array(
                    'success' => true,
                    'message' => __('PHP OPcache cleared.', 'holler-cache-control')
                );
            }
            return array(
                'success' => false,
                'message' => __('OPcache not available.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - OPcache purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Sleep for a specified number of seconds
     * 
     * @param int $seconds Number of seconds to sleep
     */
    private static function wait($seconds) {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }

    /**
     * Purge all caches in the correct order
     * 
     * Order:
     * 1. PHP OPcache (compiled PHP code)
     * 2. Redis Object Cache (database queries)
     * 3. Nginx Page Cache (full pages)
     * 4. Cloudflare Main Cache
     * 5. Cloudflare APO Cache
     * 
     * Each cache operation is followed by a 5 second delay to ensure completion
     */
    public static function purge_all_caches() {
        $results = array();

        try {
            // Track the cache clear event
            Tools::track_cache_clear('all', 'admin');

            // 1. PHP OPcache
            $opcache_result = self::purge_opcache();
            if ($opcache_result['success']) {
                $results['opcache'] = $opcache_result;
            }
            self::wait(5); // Wait 5 seconds

            // 2. Redis Object Cache
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
                $results['redis'] = array(
                    'success' => true,
                    'message' => __('Redis object cache cleared.', 'holler-cache-control')
                );
                self::wait(5); // Wait 5 seconds for Redis to fully clear
            }

            // 3. Nginx Page Cache
            $nginx_result = Nginx::purge_cache();
            if ($nginx_result['success']) {
                $results['nginx'] = array(
                    'success' => true,
                    'message' => $nginx_result['message']
                );
                self::wait(5); // Wait 5 seconds for Nginx cache to clear
            }

            // 4. Cloudflare Cache
            $cloudflare_result = Cloudflare::purge_cache();
            if ($cloudflare_result['success']) {
                $results['cloudflare'] = array(
                    'success' => true,
                    'message' => $cloudflare_result['message']
                );
                self::wait(5); // Wait 5 seconds for Cloudflare to process
            }

            // 5. Cloudflare APO Cache
            $cloudflare_apo_result = CloudflareAPO::purge_cache();
            if ($cloudflare_apo_result['success']) {
                $results['cloudflare_apo'] = array(
                    'success' => true,
                    'message' => $cloudflare_apo_result['message']
                );
            }

            // Return results
            return array(
                'success' => true,
                'results' => $results
            );

        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to purge all caches: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to purge all caches.', 'holler-cache-control'),
                'error' => $e->getMessage()
            );
        }
    }
}
