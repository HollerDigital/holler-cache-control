<?php

namespace Holler\CacheControl\Admin\Cache;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache Status Optimization
 * 
 * Implements intelligent caching of cache service status checks to improve
 * admin performance and reduce redundant API calls.
 */
class StatusCache {
    
    /**
     * Cache duration for different status types (in seconds)
     */
    const CACHE_DURATIONS = [
        'nginx' => 300,      // 5 minutes - local service
        'redis' => 300,      // 5 minutes - local service  
        'cloudflare' => 600, // 10 minutes - external API
        'cloudflare_apo' => 600, // 10 minutes - external API
    ];
    
    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'holler_cache_status_';
    
    /**
     * Get cached status or fetch fresh if expired
     *
     * @param string $service Service name (nginx, redis, cloudflare, cloudflare_apo)
     * @param callable $fetch_callback Callback to fetch fresh status
     * @return array Status data
     */
    public static function get_status($service, $fetch_callback) {
        $cache_key = self::CACHE_PREFIX . $service;
        $cached_status = get_transient($cache_key);
        
        // Return cached status if valid and not expired
        if ($cached_status !== false && self::is_cache_valid($cached_status)) {
            error_log("Holler Cache Control: Using cached status for {$service}");
            return $cached_status['data'];
        }
        
        // Fetch fresh status
        error_log("Holler Cache Control: Fetching fresh status for {$service}");
        $fresh_status = call_user_func($fetch_callback);
        
        // Cache the fresh status
        self::cache_status($service, $fresh_status);
        
        return $fresh_status;
    }
    
    /**
     * Cache status data with metadata
     *
     * @param string $service Service name
     * @param array $status Status data
     */
    private static function cache_status($service, $status) {
        $cache_key = self::CACHE_PREFIX . $service;
        $cache_duration = self::CACHE_DURATIONS[$service] ?? 300;
        
        $cache_data = [
            'data' => $status,
            'timestamp' => time(),
            'service' => $service,
            'version' => HOLLER_CACHE_CONTROL_VERSION
        ];
        
        set_transient($cache_key, $cache_data, $cache_duration);
        error_log("Holler Cache Control: Cached status for {$service} (expires in {$cache_duration}s)");
    }
    
    /**
     * Check if cached data is still valid
     *
     * @param array $cached_data Cached status data
     * @return bool True if cache is valid
     */
    private static function is_cache_valid($cached_data) {
        // Check if cache structure is valid
        if (!is_array($cached_data) || !isset($cached_data['data'], $cached_data['timestamp'])) {
            return false;
        }
        
        // Check if plugin version has changed (invalidate cache on updates)
        if (isset($cached_data['version']) && $cached_data['version'] !== HOLLER_CACHE_CONTROL_VERSION) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Invalidate cache for specific service
     *
     * @param string $service Service name
     */
    public static function invalidate($service) {
        $cache_key = self::CACHE_PREFIX . $service;
        delete_transient($cache_key);
        error_log("Holler Cache Control: Invalidated cache for {$service}");
    }
    
    /**
     * Invalidate all status caches
     */
    public static function invalidate_all() {
        foreach (array_keys(self::CACHE_DURATIONS) as $service) {
            self::invalidate($service);
        }
        error_log("Holler Cache Control: Invalidated all status caches");
    }
    
    /**
     * Get cache statistics for debugging
     *
     * @return array Cache statistics
     */
    public static function get_cache_stats() {
        $stats = [];
        
        foreach (array_keys(self::CACHE_DURATIONS) as $service) {
            $cache_key = self::CACHE_PREFIX . $service;
            $cached_data = get_transient($cache_key);
            
            if ($cached_data !== false) {
                $age = time() - $cached_data['timestamp'];
                $ttl = self::CACHE_DURATIONS[$service] - $age;
                
                $stats[$service] = [
                    'cached' => true,
                    'age' => $age,
                    'ttl' => max(0, $ttl),
                    'timestamp' => $cached_data['timestamp'],
                    'version' => $cached_data['version'] ?? 'unknown'
                ];
            } else {
                $stats[$service] = [
                    'cached' => false,
                    'age' => null,
                    'ttl' => null,
                    'timestamp' => null,
                    'version' => null
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * Warm up all caches by fetching fresh status
     * Useful after plugin updates or configuration changes
     */
    public static function warm_up_caches() {
        error_log("Holler Cache Control: Warming up status caches");
        
        // Import cache classes
        $cache_classes = [
            'nginx' => '\\Holler\\CacheControl\\Admin\\Cache\\Nginx',
            'redis' => '\\Holler\\CacheControl\\Admin\\Cache\\Redis',
            'cloudflare' => '\\Holler\\CacheControl\\Admin\\Cache\\Cloudflare',
            'cloudflare_apo' => '\\Holler\\CacheControl\\Admin\\Cache\\CloudflareAPO'
        ];
        
        foreach ($cache_classes as $service => $class) {
            if (class_exists($class)) {
                try {
                    $status = call_user_func([$class, 'get_status']);
                    self::cache_status($service, $status);
                } catch (\Exception $e) {
                    error_log("Holler Cache Control: Failed to warm up cache for {$service}: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Hook into WordPress events that should invalidate caches
     */
    public static function register_invalidation_hooks() {
        // Invalidate when plugin settings are updated
        add_action('update_option_holler_cache_control_settings', [__CLASS__, 'invalidate_all']);
        add_action('update_option_holler_cache_control_cloudflare', [__CLASS__, 'invalidate_cloudflare_caches']);
        
        // Invalidate when cache operations are performed
        add_action('holler_cache_control_cache_purged', [__CLASS__, 'handle_cache_purged'], 10, 2);
        
        // Warm up caches after plugin activation
        add_action('holler_cache_control_activated', [__CLASS__, 'warm_up_caches']);
    }
    
    /**
     * Handle cache purged event
     *
     * @param string $cache_type Type of cache purged
     * @param array $result Purge result
     */
    public static function handle_cache_purged($cache_type, $result) {
        // Invalidate status cache for the purged service
        if (in_array($cache_type, ['nginx', 'redis', 'cloudflare', 'cloudflare_apo'])) {
            self::invalidate($cache_type);
        } elseif ($cache_type === 'all') {
            self::invalidate_all();
        }
    }
    
    /**
     * Invalidate Cloudflare-related caches
     */
    public static function invalidate_cloudflare_caches() {
        self::invalidate('cloudflare');
        self::invalidate('cloudflare_apo');
    }
}
