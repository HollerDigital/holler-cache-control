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
}
