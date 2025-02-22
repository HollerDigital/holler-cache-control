<?php
/**
 * Handles Nginx cache functionality.
 *
 * @package HollerCacheControl
 */

namespace Holler\CacheControl\Admin\Cache;

use function Holler\CacheControl\get_nginx_cache_method;
use function Holler\CacheControl\get_nginx_purge_method;
use function Holler\CacheControl\get_redis_settings;

class Nginx {
    /**
     * Get status of Nginx cache
     *
     * @return array Status information
     */
    public static function get_status() {
        try {
            $status = array(
                'status' => 'inactive',
                'message' => __('Nginx Page Caching is not configured.', 'holler-cache-control'),
                'type' => null
            );

            $cache_method = get_nginx_cache_method();
            
            if ($cache_method === 'enable_redis') {
                $redis_settings = get_redis_settings();
                if (!empty($redis_settings['hostname']) && !empty($redis_settings['port'])) {
                    $status['status'] = 'active';
                    $status['message'] = __('Nginx Redis Page Caching is enabled and configured.', 'holler-cache-control');
                    $status['type'] = 'redis';
                }
            } elseif ($cache_method === 'enable_fastcgi') {
                if (defined('RT_WP_NGINX_HELPER_CACHE_PATH') && is_dir(RT_WP_NGINX_HELPER_CACHE_PATH)) {
                    $status['status'] = 'active';
                    $status['message'] = __('Nginx FastCGI Page Caching is enabled and configured.', 'holler-cache-control');
                    $status['type'] = 'fastcgi';
                }
            }

            return $status;
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get Nginx status: ' . $e->getMessage());
            return array(
                'status' => 'error',
                'message' => __('Failed to get Nginx cache status.', 'holler-cache-control'),
                'type' => null
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

            // Try GridPane's action hook first
            if (has_action('rt_nginx_helper_purge_all')) {
                do_action('rt_nginx_helper_purge_all');
                return array(
                    'success' => true,
                    'message' => __('Page cache purged via GridPane.', 'holler-cache-control')
                );
            }

            // Fallback: Try to clear cache directly
            $cache_path = '/var/run/nginx-cache';
            if (is_dir($cache_path)) {
                // Use shell_exec to clear cache files
                $result = shell_exec('rm -rf ' . escapeshellarg($cache_path . '/*'));
                if ($result !== false) {
                    return array(
                        'success' => true,
                        'message' => __('Page cache purged directly.', 'holler-cache-control')
                    );
                }
            }

            // If Redis is being used for page caching
            if ($status['type'] === 'redis') {
                if (class_exists('Redis')) {
                    try {
                        $redis_settings = get_redis_settings();
                        $redis = new \Redis();
                        $redis->connect(
                            $redis_settings['hostname'],
                            $redis_settings['port'],
                            1 // 1 second timeout
                        );
                        
                        if (!empty($redis_settings['password'])) {
                            if (!empty($redis_settings['username'])) {
                                $redis->auth([$redis_settings['username'], $redis_settings['password']]);
                            } else {
                                $redis->auth($redis_settings['password']);
                            }
                        }
                        
                        if (!empty($redis_settings['database'])) {
                            $redis->select((int)$redis_settings['database']);
                        }

                        $prefix = defined('RT_WP_NGINX_HELPER_REDIS_PREFIX') ? RT_WP_NGINX_HELPER_REDIS_PREFIX : '';
                        if ($prefix) {
                            $keys = $redis->keys($prefix . '*');
                            if (!empty($keys)) {
                                $redis->del($keys);
                            }
                        } else {
                            $redis->flushDb();
                        }

                        return array(
                            'success' => true,
                            'message' => __('Redis page cache purged directly.', 'holler-cache-control')
                        );
                    } catch (\Exception $e) {
                        error_log('Failed to clear Redis page cache: ' . $e->getMessage());
                    }
                }
            }

            return array(
                'success' => false,
                'message' => __('Failed to purge page cache. No valid cache clearing method available.', 'holler-cache-control')
            );

        } catch (\Exception $e) {
            error_log('Holler Cache Control - Page cache purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to purge page cache: %s', 'holler-cache-control'), $e->getMessage())
            );
        }
    }

    /**
     * Get Redis cache information
     *
     * @return array|false Cache information or false if not available
     */
    private static function get_redis_info() {
        try {
            if (!extension_loaded('redis')) {
                error_log('Redis extension not loaded');
                return false;
            }

            $redis_settings = get_redis_settings();
            if (empty($redis_settings['hostname']) || empty($redis_settings['port'])) {
                error_log('Redis settings not configured');
                return false;
            }

            $redis = new \Redis();
            try {
                $redis->connect(
                    $redis_settings['hostname'],
                    $redis_settings['port'],
                    1 // 1 second timeout
                );
            } catch (\Exception $e) {
                error_log('Failed to connect to Redis: ' . $e->getMessage());
                return false;
            }

            if (!empty($redis_settings['password'])) {
                try {
                    if (!empty($redis_settings['username'])) {
                        $redis->auth([$redis_settings['username'], $redis_settings['password']]);
                    } else {
                        $redis->auth($redis_settings['password']);
                    }
                } catch (\Exception $e) {
                    error_log('Failed to authenticate with Redis: ' . $e->getMessage());
                    return false;
                }
            }

            if (!empty($redis_settings['database'])) {
                try {
                    $redis->select((int)$redis_settings['database']);
                } catch (\Exception $e) {
                    error_log('Failed to select Redis database: ' . $e->getMessage());
                    return false;
                }
            }

            try {
                $info = $redis->info();
                $keys = $redis->dbSize();

                return array(
                    'size' => size_format($info['used_memory']),
                    'files' => $keys,
                    'hit_rate' => isset($info['keyspace_hits']) ? 
                        round(($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses'])) * 100, 2) : 0,
                    'hits' => $info['keyspace_hits'] ?? 0,
                    'misses' => $info['keyspace_misses'] ?? 0,
                    'config' => array(
                        'max_memory' => size_format($info['maxmemory']),
                        'eviction_policy' => $info['maxmemory_policy'],
                        'persistence' => $info['persistence'],
                        'uptime' => self::format_uptime($info['uptime_in_seconds'])
                    )
                );
            } catch (\Exception $e) {
                error_log('Failed to get Redis info: ' . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            error_log('Redis info error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache information
     *
     * @return array|false Cache information or false if not available
     */
    public static function get_cache_info() {
        try {
            $status = self::get_status();
            error_log('Nginx Status: ' . print_r($status, true));
            
            if ($status['status'] !== 'active') {
                return false;
            }

            $cache_info = array(
                'type' => $status['type']
            );

            if ($status['type'] === 'redis') {
                $redis_info = self::get_redis_info();
                if ($redis_info) {
                    $cache_info = array_merge($cache_info, $redis_info);
                } else {
                    // Fallback to basic info if Redis connection fails
                    $cache_info = array_merge($cache_info, array(
                        'size' => 'N/A',
                        'files' => 0,
                        'message' => 'Could not connect to Redis server'
                    ));
                }
            } elseif ($status['type'] === 'fastcgi') {
                $cache_path = RT_WP_NGINX_HELPER_CACHE_PATH;
                if (!is_dir($cache_path)) {
                    return false;
                }

                // Get cache size
                $size_output = shell_exec("du -sh " . escapeshellarg($cache_path) . " 2>/dev/null");
                $size = trim(explode("\t", $size_output)[0]);

                // Get number of files
                $files_output = shell_exec("find " . escapeshellarg($cache_path) . " -type f | wc -l");
                $files = (int)trim($files_output);

                // Get cache stats from nginx status
                $stats = self::get_nginx_stats();
                
                $cache_info = array_merge($cache_info, array(
                    'size' => $size,
                    'files' => $files,
                    'directory' => $cache_path
                ));

                if ($stats) {
                    $cache_info = array_merge($cache_info, $stats);
                }

                $config = self::get_fastcgi_config();
                if ($config) {
                    $cache_info['config'] = $config;
                }
            }

            error_log('Cache Info Result: ' . print_r($cache_info, true));
            return $cache_info;
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get Nginx cache info: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Nginx stats from status page
     *
     * @return array|false Stats or false if not available
     */
    private static function get_nginx_stats() {
        try {
            // Try to get stats from nginx status page
            $status_url = 'http://localhost/nginx_status';
            $response = wp_remote_get($status_url);
            
            if (is_wp_error($response)) {
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return false;
            }

            // Parse nginx status output
            preg_match_all('/Active connections: (\d+).*?(\d+)\s+(\d+)\s+(\d+)/s', $body, $matches);
            
            if (empty($matches[1])) {
                return false;
            }

            $total_requests = (int)$matches[2][0];
            $total_handled = (int)$matches[3][0];
            
            return array(
                'hit_rate' => round(($total_handled / $total_requests) * 100, 2),
                'hits' => $total_handled,
                'misses' => $total_requests - $total_handled,
                'bypasses' => 0
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get FastCGI configuration
     *
     * @return array|false Configuration or false if not available
     */
    private static function get_fastcgi_config() {
        if (!defined('RT_WP_NGINX_HELPER_FASTCGI_PATH') || !file_exists(RT_WP_NGINX_HELPER_FASTCGI_PATH)) {
            return false;
        }

        $config = array();
        
        // Read nginx configuration
        $nginx_conf = file_get_contents(RT_WP_NGINX_HELPER_FASTCGI_PATH);
        
        // Extract common FastCGI cache settings
        if (preg_match('/fastcgi_cache_path\s+([^;]+);/', $nginx_conf, $matches)) {
            $config['cache_path'] = trim($matches[1]);
        }
        
        if (preg_match('/fastcgi_cache_valid\s+([^;]+);/', $nginx_conf, $matches)) {
            $config['cache_valid'] = trim($matches[1]);
        }
        
        if (preg_match('/fastcgi_cache_min_uses\s+(\d+);/', $nginx_conf, $matches)) {
            $config['min_uses'] = (int)$matches[1];
        }
        
        if (preg_match('/fastcgi_cache_use_stale\s+([^;]+);/', $nginx_conf, $matches)) {
            $config['use_stale'] = trim($matches[1]);
        }

        return !empty($config) ? $config : false;
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
