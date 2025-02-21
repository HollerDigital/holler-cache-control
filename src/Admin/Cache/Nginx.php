<?php
/**
 * Handles Nginx cache functionality.
 *
 * @package HollerCacheControl
 */

namespace Holler\CacheControl\Admin\Cache;

class Nginx {
    /**
     * Get Nginx cache status
     *
     * @return array Status information
     */
    public static function get_status() {
        try {
            // Check for GridPane Nginx Helper settings
            $has_redis = defined('RT_WP_NGINX_HELPER_CACHE_METHOD') && 
                        RT_WP_NGINX_HELPER_CACHE_METHOD === 'enable_redis' &&
                        defined('RT_WP_NGINX_HELPER_REDIS_DATABASE') &&
                        defined('RT_WP_NGINX_HELPER_REDIS_HOSTNAME') &&
                        defined('RT_WP_NGINX_HELPER_REDIS_PORT') &&
                        defined('RT_WP_NGINX_HELPER_REDIS_USERNAME') &&
                        defined('RT_WP_NGINX_HELPER_REDIS_PASSWORD') &&
                        defined('RT_WP_NGINX_HELPER_REDIS_PREFIX');

            $has_fastcgi = defined('RT_WP_NGINX_HELPER_CACHE_METHOD') && 
                          RT_WP_NGINX_HELPER_CACHE_METHOD === 'enable_fastcgi' &&
                          defined('RT_WP_NGINX_HELPER_PURGE_METHOD');

            if ($has_redis) {
                return array(
                    'status' => 'active',
                    'message' => __('Nginx Redis Page Caching is enabled and configured.', 'holler-cache-control'),
                    'type' => 'redis'
                );
            } elseif ($has_fastcgi) {
                return array(
                    'status' => 'active',
                    'message' => __('Nginx FastCGI Page Caching is enabled and configured.', 'holler-cache-control'),
                    'type' => 'fastcgi'
                );
            } else {
                return array(
                    'status' => 'inactive',
                    'message' => __('Page Cache is disabled. Neither Redis nor FastCGI caching is configured.', 'holler-cache-control'),
                    'type' => 'none'
                );
            }
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get Nginx cache status: ' . $e->getMessage());
            return array(
                'status' => 'error',
                'message' => __('Failed to check Nginx cache status.', 'holler-cache-control'),
                'type' => 'none'
            );
        }
    }

    /**
     * Get status message based on configuration
     */
    private static function get_status_message($has_cache, $is_dropin, $is_wp_cache, $cache_type) {
        if (!$has_cache) {
            return __('Page caching is not configured. Please configure either FastCGI or Redis caching in plugin settings.', 'holler-cache-control');
        }

        if (!$is_dropin) {
            return __('Advanced-cache.php drop-in not installed.', 'holler-cache-control');
        }

        if (!$is_wp_cache) {
            return __('WP_CACHE constant not enabled in wp-config.php.', 'holler-cache-control');
        }

        if ($cache_type === 'fastcgi') {
            return __('Nginx FastCGI Page Caching is active.', 'holler-cache-control');
        }

        if ($cache_type === 'redis') {
            return __('Nginx Redis Page Caching is active.', 'holler-cache-control');
        }

        return __('Page caching is not properly configured.', 'holler-cache-control');
    }

    /**
     * Purge Nginx cache
     *
     * @return array Result of the purge operation
     */
    public static function purge_cache() {
        try {
            $status = self::get_status();
            if ($status['status'] !== 'active') {
                return array(
                    'success' => false,
                    'message' => __('Page cache is not active.', 'holler-cache-control')
                );
            }

            if ($status['type'] === 'redis') {
                // For Redis, we'll use the RT_WP_NGINX_HELPER plugin's purge method
                if (class_exists('\\rt_wp_nginx_helper')) {
                    global $rt_wp_nginx_helper;
                    if (method_exists($rt_wp_nginx_helper, 'purge_all')) {
                        $rt_wp_nginx_helper->purge_all();
                        return array(
                            'success' => true,
                            'message' => __('Redis page cache purged successfully.', 'holler-cache-control')
                        );
                    }
                }
                
                // Fallback to direct Redis purge if RT plugin not available
                if (function_exists('wp_cache_flush')) {
                    wp_cache_flush();
                    return array(
                        'success' => true,
                        'message' => __('Redis page cache purged successfully.', 'holler-cache-control')
                    );
                }
            } else if ($status['type'] === 'fastcgi') {
                // For FastCGI, we'll use the RT_WP_NGINX_HELPER plugin's purge method
                if (class_exists('\\rt_wp_nginx_helper')) {
                    global $rt_wp_nginx_helper;
                    if (method_exists($rt_wp_nginx_helper, 'purge_all')) {
                        $rt_wp_nginx_helper->purge_all();
                        return array(
                            'success' => true,
                            'message' => __('FastCGI page cache purged successfully.', 'holler-cache-control')
                        );
                    }
                }
            }

            return array(
                'success' => false,
                'message' => __('Failed to purge page cache. Cache helper not available.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Page cache purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to purge page cache: %s', 'holler-cache-control'), $e->getMessage())
            );
        }
    }
}
