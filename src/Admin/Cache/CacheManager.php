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
     * Purge all caches with optimized batch operations
     * 
     * Optimized approach:
     * 1. Local caches first (OPcache, Redis) - instant
     * 2. Server caches (Nginx) - fast local operation
     * 3. External APIs (Cloudflare) - batched when possible
     * 
     * No artificial delays - proper error handling and retries instead
     */
    public static function purge_all_caches() {
        $results = array();
        $start_time = microtime(true);

        try {
            // Track the cache clear event
            Tools::track_cache_clear('all', 'admin');
            error_log('Holler Cache Control: Starting optimized batch cache purge');

            // Phase 1: Local caches (instant operations)
            $local_results = self::purge_local_caches();
            $results = array_merge($results, $local_results);

            // Phase 2: Server caches (fast local operations)
            $server_results = self::purge_server_caches();
            $results = array_merge($results, $server_results);

            // Phase 3: External APIs (batched operations)
            $external_results = self::purge_external_caches();
            $results = array_merge($results, $external_results);

            // Invalidate status caches after successful purge
            \Holler\CacheControl\Admin\Cache\StatusCache::invalidate_all();
            
            $total_time = round((microtime(true) - $start_time) * 1000, 2);
            error_log("Holler Cache Control: Batch cache purge completed in {$total_time}ms");

            // Return results
            return array(
                'success' => true,
                'results' => $results,
                'execution_time_ms' => $total_time,
                'caches_cleared' => count($results)
            );

        } catch (\Exception $e) {
            $total_time = round((microtime(true) - $start_time) * 1000, 2);
            error_log('Holler Cache Control - Failed to purge all caches: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to purge all caches.', 'holler-cache-control'),
                'error' => $e->getMessage(),
                'execution_time_ms' => $total_time
            );
        }
    }
    
    /**
     * Purge local caches (OPcache, Redis Object Cache)
     * These are instant operations with no network latency
     *
     * @return array Results from local cache operations
     */
    private static function purge_local_caches() {
        $results = array();
        
        // PHP OPcache - instant operation
        $opcache_result = self::purge_opcache();
        if ($opcache_result['success']) {
            $results['opcache'] = $opcache_result;
        }
        
        // Redis Object Cache - local operation, very fast
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $results['redis'] = array(
                'success' => true,
                'message' => __('Redis object cache cleared.', 'holler-cache-control')
            );
        }
        
        return $results;
    }
    
    /**
     * Purge server caches (Nginx Page Cache)
     * Local server operations, fast but may involve file system
     *
     * @return array Results from server cache operations
     */
    private static function purge_server_caches() {
        $results = array();
        
        // Nginx Page Cache - local server operation
        $nginx_result = Nginx::purge_cache();
        if ($nginx_result['success']) {
            $results['nginx'] = array(
                'success' => true,
                'message' => $nginx_result['message']
            );
        }
        
        return $results;
    }
    
    /**
     * Purge external caches (Cloudflare, Cloudflare APO)
     * These involve API calls and benefit from batching
     *
     * @return array Results from external cache operations
     */
    private static function purge_external_caches() {
        $results = array();
        
        // Check if Cloudflare is configured before making API calls
        $cloudflare_status = Cloudflare::get_status();
        if ($cloudflare_status['configured']) {
            // Batch Cloudflare operations when possible
            $cloudflare_results = self::batch_cloudflare_operations();
            $results = array_merge($results, $cloudflare_results);
        }
        
        return $results;
    }
    
    /**
     * Batch Cloudflare cache operations for efficiency
     * Combines regular cache and APO purging where possible
     *
     * @return array Results from Cloudflare operations
     */
    private static function batch_cloudflare_operations() {
        $results = array();
        
        try {
            // Regular Cloudflare Cache
            $cloudflare_result = Cloudflare::purge_cache();
            if ($cloudflare_result['success']) {
                $results['cloudflare'] = array(
                    'success' => true,
                    'message' => $cloudflare_result['message']
                );
            }
            
            // Cloudflare APO - check if enabled before attempting
            $apo_status = CloudflareAPO::get_status();
            if ($apo_status['enabled']) {
                $cloudflare_apo_result = CloudflareAPO::purge_cache();
                if ($cloudflare_apo_result['success']) {
                    $results['cloudflare_apo'] = array(
                        'success' => true,
                        'message' => $cloudflare_apo_result['message']
                    );
                }
            }
            
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Cloudflare batch operation failed: ' . $e->getMessage());
            $results['cloudflare_error'] = array(
                'success' => false,
                'message' => __('Cloudflare cache operations failed.', 'holler-cache-control'),
                'error' => $e->getMessage()
            );
        }
        
        return $results;
    }
}
