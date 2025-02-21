<?php
namespace HollerCacheControl\Cache;

/**
 * Handles Redis Cache operations
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl\Cache
 */
class Redis {
    /**
     * Get Redis connection
     *
     * @return \Redis|null
     */
    private static function get_redis_connection() {
        try {
            $redis = new \Redis();
            $socket = '/var/run/redis/redis-server.sock';
            
            if ($redis->connect($socket)) {
                // Try to select the correct database if configured
                if (defined('WP_REDIS_DATABASE')) {
                    $redis->select(WP_REDIS_DATABASE);
                }
                return $redis;
            }
        } catch (\Exception $e) {
            // Removed error_log call
        }
        return null;
    }

    /**
     * Get Redis status for GridPane environment
     *
     * @return array
     */
    public static function get_status() {
        $result = array(
            'active' => false,
            'message' => '',
            'details' => '',
            'type' => 'redis'
        );

        try {
            // First check if Redis object cache is enabled in WordPress
            global $wp_object_cache;
            
            if (!is_object($wp_object_cache) || 
                !method_exists($wp_object_cache, 'redis_instance') ||
                !method_exists($wp_object_cache, 'redis_status')) {
                $result['message'] = __('Redis Object Cache not enabled', 'holler-cache-control');
                return $result;
            }

            // Get Redis status from wp-redis plugin
            $redis_status = $wp_object_cache->redis_status();
            if (!$redis_status) {
                $result['message'] = __('Redis Object Cache not connected', 'holler-cache-control');
                return $result;
            }

            // Try to get Redis instance
            $redis = $wp_object_cache->redis_instance();
            if (!$redis) {
                $result['message'] = __('Redis Object Cache not connected', 'holler-cache-control');
                return $result;
            }

            // Check if Redis is responding
            if ($redis->ping() !== true && $redis->ping() !== '+PONG') {
                $result['message'] = __('Redis server not responding', 'holler-cache-control');
                return $result;
            }

            // Get info about Redis server
            $info = $redis->info();
            
            // Mark as active and get details
            $result['active'] = true;
            $result['details'] = sprintf(
                __('Redis v%s, Memory: %s, Keys: %d', 'holler-cache-control'),
                isset($info['redis_version']) ? $info['redis_version'] : 'unknown',
                isset($info['used_memory_human']) ? $info['used_memory_human'] : 'unknown',
                isset($info['db0']) ? $info['db0']['keys'] : 0
            );

        } catch (\Exception $e) {
            $result['message'] = sprintf(
                __('Redis Error: %s', 'holler-cache-control'),
                $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Purge Redis cache
     *
     * @return array
     */
    public static function purge_cache() {
        $result = array(
            'success' => false,
            'message' => ''
        );

        try {
            // First try WordPress Redis object cache
            global $wp_object_cache;
            if (is_object($wp_object_cache) && 
                method_exists($wp_object_cache, 'redis_instance') &&
                method_exists($wp_object_cache, 'redis_status')) {
                
                $redis = $wp_object_cache->redis_instance();
                if ($redis && $wp_object_cache->redis_status()) {
                    // Flush WordPress object cache
                    wp_cache_flush();
                    
                    $result['success'] = true;
                    $result['message'] = __('Redis object cache purged successfully', 'holler-cache-control');
                    return $result;
                }
            }

            // Fallback to manual Redis connection
            $redis = self::get_redis_connection();
            if (!$redis) {
                $result['message'] = __('Could not connect to Redis server', 'holler-cache-control');
                return $result;
            }

            // Check if Redis is responding
            if ($redis->ping() !== true && $redis->ping() !== '+PONG') {
                $result['message'] = __('Redis server not responding', 'holler-cache-control');
                return $result;
            }

            // Get Redis settings from wp-config.php
            $redis_settings = \get_redis_settings();
            
            // Select database
            if (!empty($redis_settings['database'])) {
                $redis->select($redis_settings['database']);
            }

            // Get the key prefix
            $prefix = $redis_settings['prefix'];
            if (empty($prefix)) {
                $result['message'] = __('Redis prefix not configured', 'holler-cache-control');
                return $result;
            }

            // Use SCAN instead of KEYS for better performance
            $cursor = null;
            $deleted = 0;
            
            do {
                // Scan for keys matching our prefix
                $scanResult = $redis->scan($cursor, array(
                    'match' => $prefix . '*',
                    'count' => 100
                ));
                
                // Update cursor for next iteration
                $cursor = $scanResult[0];
                $keys = $scanResult[1];
                
                // Delete found keys
                if (!empty($keys)) {
                    foreach ($keys as $key) {
                        if ($redis->del($key)) {
                            $deleted++;
                        }
                    }
                }
            } while ($cursor != 0);

            if ($deleted > 0) {
                $result['success'] = true;
                $result['message'] = sprintf(
                    __('Successfully cleared %d Redis keys', 'holler-cache-control'),
                    $deleted
                );
            } else {
                $result['message'] = __('No Redis keys found to clear', 'holler-cache-control');
            }

        } catch (\Exception $e) {
            $result['message'] = sprintf(
                __('Redis Error: %s', 'holler-cache-control'),
                $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Purge only the WordPress object cache in Redis
     *
     * @return array
     */
    public static function purge_object_cache() {
        // Check if redis object cache plugin is active
        if (!function_exists('is_plugin_active') || !is_plugin_active('redis-cache/redis-cache.php')) {
            return array(
                'success' => false,
                'message' => __('Redis Object Cache plugin is not active', 'holler-cache-control')
            );
        }

        // Get the redis object cache instance
        if (!class_exists('\WP_Redis')) {
            return array(
                'success' => false,
                'message' => __('Redis Object Cache class not found', 'holler-cache-control')
            );
        }

        $redis = \WP_Redis::instance();
        if (!$redis) {
            return array(
                'success' => false,
                'message' => __('Redis connection not available', 'holler-cache-control')
            );
        }

        // Flush only WordPress object cache
        wp_cache_flush();

        return array(
            'success' => true,
            'message' => __('Redis object cache purged successfully', 'holler-cache-control')
        );
    }
}
