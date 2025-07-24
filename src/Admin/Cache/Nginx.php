<?php
/**
 * Handles Nginx cache functionality.
 *
 * @package HollerCacheControl
 */

namespace Holler\CacheControl\Admin\Cache;

use Holler\CacheControl\Core\ErrorHandler;
use Holler\CacheControl\Core\CachePathDetector;
use function Holler\CacheControl\{get_nginx_cache_method, get_nginx_purge_method, get_redis_settings};

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
     * Purge Nginx cache with enhanced error handling and path detection
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

            // Try GridPane's action hook first (highest priority)
            if (has_action('rt_nginx_helper_purge_all')) {
                do_action('rt_nginx_helper_purge_all');
                ErrorHandler::log(
                    'Nginx cache purged via GridPane action hook',
                    ErrorHandler::LOG_LEVEL_INFO,
                    array('method' => 'gridpane_hook')
                );
                return array(
                    'success' => true,
                    'message' => __('Page cache purged via GridPane.', 'holler-cache-control')
                );
            }

            // Enhanced cache path detection and clearing
            $purge_results = self::purge_detected_cache_paths();
            if ($purge_results['success']) {
                return $purge_results;
            }

            // If Redis is being used for page caching
            if ($status['type'] === 'redis') {
                $redis_result = self::purge_redis_page_cache();
                if ($redis_result['success']) {
                    return $redis_result;
                }
            }

            // If all methods failed, return detailed error information
            ErrorHandler::log(
                'All Nginx cache purge methods failed',
                ErrorHandler::LOG_LEVEL_ERROR,
                array(
                    'status_type' => $status['type'],
                    'gridpane_hook_available' => has_action('rt_nginx_helper_purge_all'),
                    'redis_available' => class_exists('Redis')
                )
            );

            return array(
                'success' => false,
                'message' => __('Failed to purge page cache. No valid cache clearing method available.', 'holler-cache-control'),
                'debug_info' => WP_DEBUG ? 'Check error logs for detailed information' : null
            );

        } catch (\Exception $e) {
            return ErrorHandler::handle_cache_error('nginx_purge', $e, array(
                'cache_status' => isset($status) ? $status : 'unknown'
            ));
        }
    }

    /**
     * Purge cache using detected cache paths
     *
     * @return array Result of the purge operation
     */
    private static function purge_detected_cache_paths() {
        try {
            // Get all detected cache paths
            $detected_paths = CachePathDetector::detect_all_paths();
            $config_paths = CachePathDetector::detect_from_config();
            
            // Combine and prioritize paths
            $all_paths = array_merge($detected_paths, array_values($config_paths));
            
            if (empty($all_paths)) {
                return array(
                    'success' => false,
                    'message' => __('No cache paths detected for clearing.', 'holler-cache-control')
                );
            }

            $cleared_paths = array();
            $failed_paths = array();

            foreach ($all_paths as $path_info) {
                $path = $path_info['path'];
                
                if (!$path_info['metadata']['writable']) {
                    $failed_paths[] = array(
                        'path' => $path,
                        'reason' => 'Not writable'
                    );
                    continue;
                }

                try {
                    // Clear cache files
                    $result = shell_exec('rm -rf ' . escapeshellarg($path . '/*') . ' 2>&1');
                    
                    if ($result === null || strpos($result, 'Permission denied') !== false) {
                        $failed_paths[] = array(
                            'path' => $path,
                            'reason' => 'Permission denied or command failed'
                        );
                    } else {
                        $cleared_paths[] = $path;
                        ErrorHandler::log(
                            'Cache path cleared successfully',
                            ErrorHandler::LOG_LEVEL_INFO,
                            array('path' => $path, 'method' => 'direct_file_removal')
                        );
                    }
                } catch (\Exception $e) {
                    $failed_paths[] = array(
                        'path' => $path,
                        'reason' => $e->getMessage()
                    );
                }
            }

            if (!empty($cleared_paths)) {
                $message = sprintf(
                    __('Page cache cleared from %d path(s): %s', 'holler-cache-control'),
                    count($cleared_paths),
                    implode(', ', array_map('basename', $cleared_paths))
                );
                
                if (!empty($failed_paths)) {
                    $message .= sprintf(
                        __(' (%d path(s) failed)', 'holler-cache-control'),
                        count($failed_paths)
                    );
                }

                return array(
                    'success' => true,
                    'message' => $message,
                    'cleared_paths' => $cleared_paths,
                    'failed_paths' => $failed_paths
                );
            }

            return array(
                'success' => false,
                'message' => __('Failed to clear cache from any detected paths.', 'holler-cache-control'),
                'failed_paths' => $failed_paths
            );

        } catch (\Exception $e) {
            return ErrorHandler::handle_cache_error('nginx_path_detection', $e);
        }
    }

    /**
     * Purge Redis page cache with enhanced error handling
     *
     * @return array Result of the purge operation
     */
    private static function purge_redis_page_cache() {
        if (!class_exists('Redis')) {
            return array(
                'success' => false,
                'message' => __('Redis PHP extension not available.', 'holler-cache-control')
            );
        }

        try {
            $redis_settings = get_redis_settings();
            $redis = new \Redis();
            
            // Connect with timeout
            $connected = $redis->connect(
                $redis_settings['hostname'],
                (int) $redis_settings['port'],
                2 // 2 second timeout
            );
            
            if (!$connected) {
                throw new \Exception('Failed to connect to Redis server');
            }
            
            // Authenticate if needed
            if (!empty($redis_settings['password'])) {
                if (!empty($redis_settings['username'])) {
                    $auth_result = $redis->auth([$redis_settings['username'], $redis_settings['password']]);
                } else {
                    $auth_result = $redis->auth($redis_settings['password']);
                }
                
                if (!$auth_result) {
                    throw new \Exception('Redis authentication failed');
                }
            }
            
            // Select database
            if (!empty($redis_settings['database'])) {
                $redis->select((int)$redis_settings['database']);
            }

            // Clear cache with prefix awareness
            $prefix = defined('RT_WP_NGINX_HELPER_REDIS_PREFIX') ? RT_WP_NGINX_HELPER_REDIS_PREFIX : '';
            $keys_cleared = 0;
            
            if ($prefix) {
                $keys = $redis->keys($prefix . '*');
                if (!empty($keys)) {
                    $keys_cleared = $redis->del($keys);
                }
            } else {
                $keys_cleared = $redis->flushDb();
            }

            ErrorHandler::log(
                'Redis page cache cleared successfully',
                ErrorHandler::LOG_LEVEL_INFO,
                array(
                    'keys_cleared' => $keys_cleared,
                    'prefix' => $prefix,
                    'database' => $redis_settings['database']
                )
            );

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Redis page cache purged successfully (%d keys cleared).', 'holler-cache-control'),
                    $keys_cleared
                )
            );

        } catch (\Exception $e) {
            return ErrorHandler::handle_cache_error('redis_page_cache', $e, array(
                'redis_settings' => array(
                    'hostname' => $redis_settings['hostname'] ?? 'not_set',
                    'port' => $redis_settings['port'] ?? 'not_set',
                    'database' => $redis_settings['database'] ?? 'not_set'
                )
            ));
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
                        'max_memory' => size_format($info['maxmemory'] ?? 0),
                        'eviction_policy' => $info['maxmemory_policy'] ?? 'noeviction',
                        'persistence' => $info['persistence'] ?? 'none',
                        'uptime' => self::format_uptime($info['uptime_in_seconds'] ?? 0)
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
