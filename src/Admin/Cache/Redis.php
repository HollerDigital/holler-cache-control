<?php
/**
 * Handles Redis cache functionality.
 *
 * @package HollerCacheControl
 */

namespace Holler\CacheControl\Admin\Cache;

class Redis {
    /**
     * Get Redis configuration status
     *
     * @return array Status information
     */
    public static function get_status() {
        return StatusCache::get_status('redis', [__CLASS__, 'get_fresh_status']);
    }
    
    /**
     * Get fresh Redis status (called by StatusCache when cache is expired)
     *
     * @return array Status information
     */
    public static function get_fresh_status() {
        global $wp_object_cache;

        $has_extension = class_exists('Redis');
        $is_connected = false;
        $is_dropin = file_exists(WP_CONTENT_DIR . '/object-cache.php');
        $details = array();

        if ($has_extension && $is_dropin) {
            $is_connected = is_object($wp_object_cache) && method_exists($wp_object_cache, 'redis_status') && $wp_object_cache->redis_status();
            
            // Get detailed Redis statistics if connected
            if ($is_connected && method_exists($wp_object_cache, 'redis_instance')) {
                try {
                    $redis = $wp_object_cache->redis_instance();
                    if ($redis && method_exists($redis, 'info')) {
                        $info = $redis->info();
                        
                        // Extract key statistics
                        $memory_usage = isset($info['used_memory_human']) ? $info['used_memory_human'] : 'Unknown';
                        
                        // Extract keys count from db0 info
                        $keys_count = '0';
                        if (isset($info['db0'])) {
                            if (preg_match('/keys=(\d+)/', $info['db0'], $matches)) {
                                $keys_count = $matches[1];
                            }
                        }
                        
                        $details = array(
                            'Memory Usage' => $memory_usage,
                            'Keys' => $keys_count,
                            'Connected Clients' => isset($info['connected_clients']) ? $info['connected_clients'] : 'Unknown',
                            'Uptime' => isset($info['uptime_in_seconds']) ? self::format_uptime($info['uptime_in_seconds']) : 'Unknown'
                        );
                        
                        // Add hit/miss statistics if available
                        if (isset($info['keyspace_hits']) && isset($info['keyspace_misses'])) {
                            $hits = (int)$info['keyspace_hits'];
                            $misses = (int)$info['keyspace_misses'];
                            $total = $hits + $misses;
                            $hit_ratio = $total > 0 ? round(($hits / $total) * 100, 2) : 0;
                            
                            $details['Hits'] = number_format($hits);
                            $details['Misses'] = number_format($misses);
                            $details['Hit Ratio'] = $hit_ratio . '%';
                        }
                    }
                } catch (Exception $e) {
                    error_log('Holler Cache Control: Error getting Redis info: ' . $e->getMessage());
                }
            }
        }

        $status_info = array(
            'enabled' => true,
            'configured' => $has_extension && $is_dropin && $is_connected,
            'status' => $has_extension && $is_dropin && $is_connected ? 'active' : ($has_extension ? ($is_dropin ? 'not_connected' : 'no_dropin') : 'not_configured'),
            'message' => $has_extension ? 
                ($is_dropin ? 
                    ($is_connected ? __('Redis cache is active.', 'holler-cache-control') : __('Redis extension installed but not connected.', 'holler-cache-control')) 
                    : __('Redis object-cache.php drop-in not installed.', 'holler-cache-control')
                ) : __('Redis extension not installed.', 'holler-cache-control')
        );
        
        // Add details if we have them
        if (!empty($details)) {
            $status_info['details'] = $details;
        }
        
        return $status_info;
    }
    


    /**
     * Execute a command safely in WordPress environment
     *
     * @param string $command Command to execute
     * @return array Result of the operation
     */
    private static function execute_command($command) {
        if (function_exists('wp_remote_post')) {
            // Use WordPress HTTP API as a fallback
            $response = wp_remote_post(admin_url('admin-ajax.php'), array(
                'blocking' => true,
                'timeout' => 30,
                'body' => array(
                    'action' => 'holler_cache_execute_command',
                    'command' => $command
                )
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }

            return array(
                'success' => true,
                'message' => wp_remote_retrieve_body($response)
            );
        }

        return array(
            'success' => false,
            'message' => __('Command execution not available', 'holler-cache-control')
        );
    }

    /**
     * Purge Redis page cache
     *
     * @return array Result of the purge operation
     */
    public static function purge_page_cache() {
        try {
            global $wp_object_cache;
            
            // Try to flush using WordPress object cache first
            if (is_object($wp_object_cache) && method_exists($wp_object_cache, 'flush')) {
                $wp_object_cache->flush();
            }

            // Use WordPress cache clearing functions
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            return array(
                'success' => true,
                'message' => __('Redis page cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to purge Redis page cache: %s', 'holler-cache-control'), $e->getMessage())
            );
        }
    }

    /**
     * Purge Redis object cache
     *
     * @return array Result of the purge operation
     */
    public static function purge_object_cache() {
        try {
            global $wp_object_cache;
            
            // Try to flush using WordPress object cache
            if (is_object($wp_object_cache) && method_exists($wp_object_cache, 'flush')) {
                $wp_object_cache->flush();
            }

            // Use WordPress cache clearing functions
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            return array(
                'success' => true,
                'message' => __('Redis object cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to purge Redis object cache: %s', 'holler-cache-control'), $e->getMessage())
            );
        }
    }

    /**
     * Purge all Redis caches
     *
     * @return array Result of the purge operation
     */
    public static function purge_cache() {
        $page_result = self::purge_page_cache();
        $object_result = self::purge_object_cache();

        if ($page_result['success'] && $object_result['success']) {
            return array(
                'success' => true,
                'message' => __('All Redis caches purged successfully.', 'holler-cache-control')
            );
        }

        return array(
            'success' => false,
            'message' => sprintf(
                __('Failed to purge Redis caches: %s', 'holler-cache-control'),
                implode(', ', array_filter([
                    !$page_result['success'] ? $page_result['message'] : null,
                    !$object_result['success'] ? $object_result['message'] : null
                ]))
            )
        );
    }

    /**
     * Get Redis cache information
     *
     * @return array|false Cache information or false if not available
     */
    public static function get_cache_info() {
        try {
            global $wp_object_cache;

            if (!class_exists('Redis') || !is_object($wp_object_cache) || !method_exists($wp_object_cache, 'redis_instance')) {
                return false;
            }

            $redis = $wp_object_cache->redis_instance();
            if (!$redis) {
                return false;
            }

            $info = $redis->info();
            if (!$info) {
                return false;
            }

            // Format memory size
            $memory = size_format($info['used_memory']);

            // Get uptime in a human-readable format
            $uptime = self::format_uptime($info['uptime_in_seconds']);

            // Parse database info to get key count
            $keys = 0;
            if (isset($info['db0']) && is_string($info['db0'])) {
                // Parse db0 string like "keys=123,expires=45,avg_ttl=0"
                if (preg_match('/keys=(\d+)/', $info['db0'], $matches)) {
                    $keys = (int) $matches[1];
                }
            } elseif (isset($info['db0']) && is_array($info['db0'])) {
                // Handle case where db0 is already an array
                $keys = count($info['db0']);
            }

            return array(
                'memory' => $memory,
                'keys' => $keys,
                'clients' => $info['connected_clients'],
                'uptime' => $uptime
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get Redis cache info: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format uptime into a human-readable string
     *
     * @param int $seconds Uptime in seconds
     * @return string Formatted uptime
     */
    private static function format_uptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = array();
        if ($days > 0) {
            $parts[] = $days . ' ' . _n('day', 'days', $days, 'holler-cache-control');
        }
        if ($hours > 0) {
            $parts[] = $hours . ' ' . _n('hour', 'hours', $hours, 'holler-cache-control');
        }
        if ($minutes > 0 && count($parts) < 2) {
            $parts[] = $minutes . ' ' . _n('minute', 'minutes', $minutes, 'holler-cache-control');
        }

        return implode(', ', $parts);
    }
}
