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
        global $wp_object_cache;

        $has_extension = class_exists('Redis');
        $is_connected = false;
        $is_dropin = file_exists(WP_CONTENT_DIR . '/object-cache.php');

        if ($has_extension && $is_dropin) {
            $is_connected = is_object($wp_object_cache) && method_exists($wp_object_cache, 'redis_status') && $wp_object_cache->redis_status();
        }

        return array(
            'enabled' => true,
            'configured' => $has_extension && $is_dropin && $is_connected,
            'status' => $has_extension && $is_dropin && $is_connected ? 'active' : ($has_extension ? ($is_dropin ? 'not_connected' : 'no_dropin') : 'not_configured'),
            'message' => $has_extension ? 
                ($is_dropin ? 
                    ($is_connected ? __('Redis cache is active.', 'holler-cache-control') : __('Redis extension installed but not connected.', 'holler-cache-control')) 
                    : __('Redis object-cache.php drop-in not installed.', 'holler-cache-control')
                ) : __('Redis extension not installed.', 'holler-cache-control')
        );
    }

    /**
     * Purge Redis cache
     *
     * @return array Result of the purge operation
     */
    public static function purge_cache() {
        try {
            global $wp_object_cache;

            if (!class_exists('Redis')) {
                return array(
                    'success' => false,
                    'message' => __('Redis extension not installed.', 'holler-cache-control')
                );
            }

            if (!is_object($wp_object_cache) || !method_exists($wp_object_cache, 'flush')) {
                return array(
                    'success' => false,
                    'message' => __('Redis object cache not available.', 'holler-cache-control')
                );
            }

            $wp_object_cache->flush();
            return array(
                'success' => true,
                'message' => __('Redis cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
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

            return array(
                'memory' => $memory,
                'keys' => $info['db0'] ? count($info['db0']) : 0,
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
