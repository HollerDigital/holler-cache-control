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
        return StatusCache::get_status('nginx', [__CLASS__, 'get_fresh_status']);
    }
    
    /**
     * Get fresh Nginx status (called by StatusCache when cache is expired)
     *
     * @return array Status information
     */
    public static function get_fresh_status() {
        try {
            $status = array(
                'status' => 'inactive',
                'message' => __('Nginx Page Caching is not configured.', 'holler-cache-control'),
                'type' => null
            );
            $details = array();

            // Get GridPane cache method
            $cache_method = get_nginx_cache_method();
            
            // Debug logging for cache detection
            error_log('Holler Cache Control: Detected cache method: ' . $cache_method);
            error_log('Holler Cache Control: RT_WP_NGINX_HELPER_CACHE_METHOD constant: ' . (defined('RT_WP_NGINX_HELPER_CACHE_METHOD') ? RT_WP_NGINX_HELPER_CACHE_METHOD : 'not defined'));
            
            // Check for Redis Page Caching (GridPane method)
            if ($cache_method === 'enable_redis') {
                $redis_settings = get_redis_settings();
                error_log('Holler Cache Control: Redis settings - hostname: ' . $redis_settings['hostname'] . ', port: ' . $redis_settings['port']);
                
                if (!empty($redis_settings['hostname']) && !empty($redis_settings['port'])) {
                    $status['status'] = 'active';
                    $status['message'] = __('🚀 GridPane Redis Page Caching is enabled and configured.', 'holler-cache-control');
                    $status['type'] = 'redis';
                    
                    // Get Redis page cache statistics
                    $details = self::get_redis_page_cache_stats($redis_settings);
                } else {
                    $status['message'] = __('⚠️ GridPane Redis Page Caching is enabled but not fully configured (missing hostname/port).', 'holler-cache-control');
                }
            } 
            // Check for FastCGI Page Caching (GridPane method)
            elseif ($cache_method === 'enable_fastcgi') {
                $status['status'] = 'active';
                $status['message'] = __('🚀 GridPane Nginx FastCGI Page Caching is enabled.', 'holler-cache-control');
                $status['type'] = 'fastcgi';
                
                // Get FastCGI cache statistics
                $details = self::get_fastcgi_cache_stats();
                
                // Add cache path info if available
                if (defined('RT_WP_NGINX_HELPER_CACHE_PATH')) {
                    $details['Cache Path'] = RT_WP_NGINX_HELPER_CACHE_PATH;
                    if (!is_dir(RT_WP_NGINX_HELPER_CACHE_PATH)) {
                        $details['Cache Path Status'] = '⚠️ Directory not found';
                    } elseif (!is_writable(RT_WP_NGINX_HELPER_CACHE_PATH)) {
                        $details['Cache Path Status'] = '⚠️ Not writable';
                    } else {
                        $details['Cache Path Status'] = '✅ Ready';
                    }
                }
            }
            // Handle unknown or disabled cache methods
            else {
                if ($cache_method === 'disable_redis') {
                    $status['message'] = __('❌ GridPane Page Caching is disabled.', 'holler-cache-control');
                } else {
                    $status['message'] = sprintf(
                        __('❓ Unknown cache method detected: %s', 'holler-cache-control'),
                        $cache_method
                    );
                }
                
                // Add diagnostic information
                $details['Detected Method'] = $cache_method;
                $details['RT_WP_NGINX_HELPER_CACHE_METHOD'] = defined('RT_WP_NGINX_HELPER_CACHE_METHOD') ? RT_WP_NGINX_HELPER_CACHE_METHOD : 'Not defined';
            }
            
            // Add details if we have them
            if (!empty($details)) {
                $status['details'] = $details;
            }

            return $status;
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get Nginx status: ' . $e->getMessage());
            error_log('Holler Cache Control - Exception details: ' . $e->getTraceAsString());
            
            // Provide more detailed error information
            $error_details = array(
                'Exception' => $e->getMessage(),
                'Cache Method' => get_nginx_cache_method(),
                'RT_WP_NGINX_HELPER_CACHE_METHOD' => defined('RT_WP_NGINX_HELPER_CACHE_METHOD') ? RT_WP_NGINX_HELPER_CACHE_METHOD : 'Not defined'
            );
            
            return array(
                'status' => 'error',
                'message' => sprintf(
                    __('Failed to get Nginx cache status: %s', 'holler-cache-control'),
                    $e->getMessage()
                ),
                'type' => null,
                'details' => $error_details
            );
        }
    }
    
    /**
     * Get Redis page cache statistics
     */
    private static function get_redis_page_cache_stats($redis_settings) {
        $details = array(
            'Cache Type' => 'Redis Page Cache',
            'Method' => 'GridPane Redis'
        );
        
        try {
            if (!class_exists('Redis')) {
                $details['Error'] = 'Redis PHP extension not available';
                return $details;
            }
            
            $redis = new \Redis();
            
            // Enhanced connection logic with timeout and error handling
            $connected = false;
            $connection_timeout = 2; // 2 seconds
            
            error_log('Holler Cache Control: Attempting Redis connection to ' . $redis_settings['hostname'] . ':' . $redis_settings['port']);
            
            try {
                $connected = $redis->connect(
                    $redis_settings['hostname'], 
                    (int) $redis_settings['port'],
                    $connection_timeout
                );
            } catch (\Exception $e) {
                error_log('Holler Cache Control: Redis connection failed: ' . $e->getMessage());
                $details['Connection'] = '❌ Failed: ' . $e->getMessage();
                return $details;
            }
            
            if (!$connected) {
                $details['Connection'] = '❌ Failed to connect to Redis server';
                $details['Settings'] = $redis_settings['hostname'] . ':' . $redis_settings['port'];
                return $details;
            }
            
            // Authenticate if password is provided
            if (!empty($redis_settings['password'])) {
                try {
                    $auth_result = false;
                    
                    // GridPane uses username-password authentication
                    if (!empty($redis_settings['username'])) {
                        error_log('Holler Cache Control: Attempting Redis auth with username: ' . $redis_settings['username']);
                        // Redis 6.0+ ACL authentication with username and password
                        $auth_result = $redis->auth([$redis_settings['username'], $redis_settings['password']]);
                    } else {
                        error_log('Holler Cache Control: Attempting Redis auth with password only');
                        // Traditional password-only authentication
                        $auth_result = $redis->auth($redis_settings['password']);
                    }
                    
                    if (!$auth_result) {
                        $details['Connection'] = '❌ Authentication failed';
                        $details['Auth Method'] = !empty($redis_settings['username']) ? 'Username + Password' : 'Password only';
                        $redis->close();
                        return $details;
                    }
                    
                    error_log('Holler Cache Control: Redis authentication successful');
                    
                } catch (\Exception $e) {
                    error_log('Holler Cache Control: Redis auth failed: ' . $e->getMessage());
                    $details['Connection'] = '❌ Auth error: ' . $e->getMessage();
                    $details['Auth Method'] = !empty($redis_settings['username']) ? 'Username + Password' : 'Password only';
                    $details['Username'] = $redis_settings['username'] ?? 'Not provided';
                    $redis->close();
                    return $details;
                }
            }
            
            // Select database if specified
            if (!empty($redis_settings['database'])) {
                try {
                    $redis->select((int) $redis_settings['database']);
                } catch (\Exception $e) {
                    error_log('Holler Cache Control: Redis database selection failed: ' . $e->getMessage());
                    $details['Connection'] = '❌ Database selection failed: ' . $e->getMessage();
                    $redis->close();
                    return $details;
                }
            }
            
            $details['Connection'] = '✅ Connected successfully';
            
            if ($connected) {
                $info = $redis->info();
                
                // Get memory usage
                $memory_usage = isset($info['used_memory_human']) ? $info['used_memory_human'] : 'Unknown';
                
                // Get key count for page cache (assuming nginx page cache uses a specific pattern)
                $page_cache_keys = 0;
                try {
                    $keys = $redis->keys('nginx-cache:*');
                    $page_cache_keys = is_array($keys) ? count($keys) : 0;
                } catch (Exception $e) {
                    // Fallback to total keys if pattern search fails
                    if (isset($info['db0'])) {
                        if (preg_match('/keys=(\d+)/', $info['db0'], $matches)) {
                            $page_cache_keys = (int)$matches[1];
                        }
                    }
                }
                
                // Format uptime inline
                $uptime_formatted = 'Unknown';
                if (isset($info['uptime_in_seconds'])) {
                    $seconds = (int)$info['uptime_in_seconds'];
                    $days = floor($seconds / 86400);
                    $hours = floor(($seconds % 86400) / 3600);
                    $minutes = floor(($seconds % 3600) / 60);
                    
                    if ($days > 0) {
                        $uptime_formatted = $days . ' days, ' . $hours . ' hours';
                    } elseif ($hours > 0) {
                        $uptime_formatted = $hours . ' hours, ' . $minutes . ' minutes';
                    } else {
                        $uptime_formatted = $minutes . ' minutes';
                    }
                }
                
                $details = array(
                    'Cache Type' => 'Redis',
                    'Memory Usage' => $memory_usage,
                    'Total Keys' => $page_cache_keys,
                    'Max Memory' => isset($info['maxmemory_human']) ? $info['maxmemory_human'] : 'No limit',
                    'Eviction Policy' => isset($info['maxmemory_policy']) ? $info['maxmemory_policy'] : 'Unknown',
                    'Uptime' => $uptime_formatted
                );
                
                // Add hit/miss statistics if available
                if (isset($info['keyspace_hits']) && isset($info['keyspace_misses'])) {
                    $hits = (int)$info['keyspace_hits'];
                    $misses = (int)$info['keyspace_misses'];
                    $total = $hits + $misses;
                    $hit_rate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;
                    
                    $details['Cache Hit Rate'] = $hit_rate . '%';
                    $details['Cache Hits'] = number_format($hits);
                    $details['Cache Misses'] = number_format($misses);
                }
                
                $redis->close();
            }
        } catch (Exception $e) {
            error_log('Holler Cache Control: Error getting Redis page cache stats: ' . $e->getMessage());
        }
        
        return $details;
    }
    
    /**
     * Get FastCGI cache statistics
     */
    private static function get_fastcgi_cache_stats() {
        $details = array(
            'Cache Type' => 'FastCGI',
            'Method' => 'GridPane Nginx FastCGI'
        );
        
        // Try to find cache path
        $cache_path = null;
        
        // First check for GridPane constant
        if (defined('RT_WP_NGINX_HELPER_CACHE_PATH')) {
            $cache_path = RT_WP_NGINX_HELPER_CACHE_PATH;
            $details['Path Source'] = 'GridPane constant';
        } else {
            // Fallback: Try common GridPane cache paths
            $common_paths = array(
                '/var/run/nginx-cache',
                '/var/cache/nginx',
                '/var/www/cache/nginx',
                '/tmp/nginx/cache'
            );
            
            foreach ($common_paths as $path) {
                if (is_dir($path)) {
                    $cache_path = $path;
                    $details['Path Source'] = 'Auto-detected';
                    break;
                }
            }
        }
        
        if ($cache_path && is_dir($cache_path)) {
            $details['Cache Path'] = $cache_path;
            
            try {
                // Get cache directory size and file count using shell commands for better performance
                $size_output = \shell_exec("du -sh " . escapeshellarg($cache_path) . " 2>/dev/null");
                if ($size_output) {
                    $size = trim(explode("\t", $size_output)[0]);
                    $details['Cache Size'] = $size;
                }
                
                $files_output = \shell_exec("find " . escapeshellarg($cache_path) . " -type f | wc -l");
                if ($files_output) {
                    $file_count = (int)trim($files_output);
                    $details['Cache Files'] = number_format($file_count);
                }
                
                // Check permissions
                $details['Status'] = is_writable($cache_path) ? '✅ Writable' : '⚠️ Read-only';
                
                // Add cache age information
                $oldest_file_output = \shell_exec("find " . escapeshellarg($cache_path) . " -type f -printf '%T@ %p\n' 2>/dev/null | sort -n | head -1");
                if ($oldest_file_output) {
                    $parts = explode(' ', trim($oldest_file_output), 2);
                    if (count($parts) >= 2) {
                        $oldest_time = (float)$parts[0];
                        $age = time() - $oldest_time;
                        if ($age > 0) {
                            $details['Oldest Cache'] = self::format_time_ago($age);
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $details['Error'] = 'Could not read cache statistics';
                error_log('Holler Cache Control: Error getting FastCGI cache stats: ' . $e->getMessage());
            }
        } else {
            $details['Status'] = '❌ Cache directory not found';
            $details['Note'] = 'FastCGI caching may still be active via nginx configuration';
            
            // Add diagnostic info
            if (defined('RT_WP_NGINX_HELPER_CACHE_PATH')) {
                $details['Configured Path'] = RT_WP_NGINX_HELPER_CACHE_PATH . ' (not accessible)';
            } else {
                $details['Configuration'] = 'RT_WP_NGINX_HELPER_CACHE_PATH not defined';
            }
        }
        
        return $details;
    }
    
    /**
     * Format time ago in human readable format
     */
    private static function format_time_ago($seconds) {
        if ($seconds < 60) {
            return $seconds . ' seconds ago';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($seconds / 86400);
            return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
        }
    }
    
    /**
     * Clear status cache to force fresh detection
     * Useful when configuration changes or for debugging
     */
    public static function clear_status_cache() {
        StatusCache::clear_cache('nginx');
        error_log('Holler Cache Control: Nginx status cache cleared');
    }
    
    /**
     * Get comprehensive GridPane cache diagnostics
     * Useful for debugging cache detection issues
     */
    public static function get_gridpane_diagnostics() {
        $diagnostics = array(
            'GridPane Constants' => array(),
            'Cache Method Detection' => array(),
            'Redis Settings' => array(),
            'FastCGI Settings' => array(),
            'Cache Paths' => array()
        );
        
        // Check all GridPane constants
        $gridpane_constants = array(
            'RT_WP_NGINX_HELPER_CACHE_METHOD',
            'RT_WP_NGINX_HELPER_PURGE_METHOD', 
            'RT_WP_NGINX_HELPER_CACHE_PATH',
            'RT_WP_NGINX_HELPER_REDIS_DATABASE',
            'RT_WP_NGINX_HELPER_REDIS_HOSTNAME',
            'RT_WP_NGINX_HELPER_REDIS_PORT',
            'RT_WP_NGINX_HELPER_REDIS_USERNAME',
            'RT_WP_NGINX_HELPER_REDIS_PASSWORD',
            'RT_WP_NGINX_HELPER_REDIS_PREFIX'
        );
        
        foreach ($gridpane_constants as $constant) {
            $diagnostics['GridPane Constants'][$constant] = defined($constant) ? constant($constant) : 'Not defined';
        }
        
        // Cache method detection
        $cache_method = get_nginx_cache_method();
        $diagnostics['Cache Method Detection']['Detected Method'] = $cache_method;
        $diagnostics['Cache Method Detection']['Is FastCGI'] = ($cache_method === 'enable_fastcgi') ? 'Yes' : 'No';
        $diagnostics['Cache Method Detection']['Is Redis'] = ($cache_method === 'enable_redis') ? 'Yes' : 'No';
        $diagnostics['Cache Method Detection']['Is Disabled'] = ($cache_method === 'disable_redis') ? 'Yes' : 'No';
        
        // Redis settings
        if ($cache_method === 'enable_redis') {
            $redis_settings = get_redis_settings();
            $diagnostics['Redis Settings'] = $redis_settings;
            
            // Test Redis connection
            try {
                $redis = new \Redis();
                $connected = $redis->connect(
                    $redis_settings['hostname'],
                    (int) $redis_settings['port'],
                    2 // 2 second timeout
                );
                
                if ($connected && !empty($redis_settings['password'])) {
                    $redis->auth($redis_settings['password']);
                }
                
                if ($connected && !empty($redis_settings['database'])) {
                    $redis->select((int) $redis_settings['database']);
                }
                
                $diagnostics['Redis Settings']['Connection Test'] = $connected ? '✅ Success' : '❌ Failed';
                
                if ($connected) {
                    $info = $redis->info();
                    $diagnostics['Redis Settings']['Server Version'] = $info['redis_version'] ?? 'Unknown';
                    $diagnostics['Redis Settings']['Memory Usage'] = $info['used_memory_human'] ?? 'Unknown';
                    $redis->close();
                }
                
            } catch (\Exception $e) {
                $diagnostics['Redis Settings']['Connection Test'] = '❌ Error: ' . $e->getMessage();
            }
        }
        
        // FastCGI settings and paths
        if ($cache_method === 'enable_fastcgi') {
            $common_paths = array(
                '/var/run/nginx-cache',
                '/var/cache/nginx', 
                '/var/www/cache/nginx',
                '/tmp/nginx/cache'
            );
            
            foreach ($common_paths as $path) {
                $status = 'Not found';
                if (is_dir($path)) {
                    $status = is_writable($path) ? '✅ Exists & Writable' : '⚠️ Exists but Read-only';
                }
                $diagnostics['Cache Paths'][$path] = $status;
            }
            
            if (defined('RT_WP_NGINX_HELPER_CACHE_PATH')) {
                $configured_path = RT_WP_NGINX_HELPER_CACHE_PATH;
                $status = 'Not found';
                if (is_dir($configured_path)) {
                    $status = is_writable($configured_path) ? '✅ Exists & Writable' : '⚠️ Exists but Read-only';
                }
                $diagnostics['FastCGI Settings']['Configured Cache Path'] = $configured_path;
                $diagnostics['FastCGI Settings']['Path Status'] = $status;
            }
        }
        
        return $diagnostics;
    }
    
    /**
     * Get directory size recursively
     */
    private static function get_directory_size($directory) {
        $size = 0;
        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        return $size;
    }
    
    /**
     * Count cache files in directory
     */
    private static function count_cache_files($directory) {
        $count = 0;
        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        }
        return $count;
    }
    
    /**
     * Get oldest cache file
     */
    private static function get_oldest_cache_file($directory) {
        $oldest_file = null;
        $oldest_time = time();
        
        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile() && $file->getMTime() < $oldest_time) {
                    $oldest_time = $file->getMTime();
                    $oldest_file = $file->getPathname();
                }
            }
        }
        
        return $oldest_file;
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
                    $result = \shell_exec('rm -rf ' . escapeshellarg($path . '/*') . ' 2>&1');
                    
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
                $size_output = \shell_exec("du -sh " . escapeshellarg($cache_path) . " 2>/dev/null");
                $size = trim(explode("\t", $size_output)[0]);

                // Get number of files
                $files_output = \shell_exec("find " . escapeshellarg($cache_path) . " -type f | wc -l");
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


}
