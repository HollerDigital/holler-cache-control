<?php
/**
 * Enhanced Cache Path Detection for Holler Cache Control
 *
 * @package HollerCacheControl
 */

namespace Holler\CacheControl\Core;

use Holler\CacheControl\Core\ErrorHandler;

class CachePathDetector {
    
    /**
     * Known cache path patterns for different hosting environments
     */
    private static $cache_path_patterns = array(
        // GridPane paths
        'gridpane' => array(
            '/var/www/cache',
            '/var/cache/nginx',
            '/var/www/cache/nginx',
            '/var/www/cache/nginx/{domain}',
            '/var/www/cache/nginx/{domain}/cache',
        ),
        
        // Common server paths
        'common' => array(
            '/var/run/nginx-cache',
            '/var/lib/nginx/cache',
            '/tmp/nginx/cache',
            '/usr/share/nginx/cache',
            '/var/cache/nginx',
            '/var/cache/nginx/fastcgi',
            '/var/cache/nginx/proxy',
        ),
        
        // EasyEngine paths
        'easyengine' => array(
            '/var/www/cache/nginx',
            '/var/www/cache/nginx/{domain}',
            '/var/www/cache/nginx/{domain}/cache',
            '/var/www/cache/nginx/{domain}/fastcgi',
            '/var/www/22222/htdocs/cache/nginx',
        ),
        
        // Plesk paths
        'plesk' => array(
            '/var/cache/plesk_nginx',
            '/var/cache/plesk_nginx/{domain}',
            '/var/cache/plesk-nginx',
            '/var/cache/plesk-nginx/{domain}',
            '/opt/psa/var/cache/nginx',
        ),
        
        // cPanel paths
        'cpanel' => array(
            '/var/cache/ea-nginx',
            '/var/cache/ea-nginx/{domain}',
            '/var/cache/cpanel-nginx',
            '/var/cache/cpanel-nginx/{domain}',
            '/usr/local/apache/cache/nginx',
        ),
        
        // Cloudways paths
        'cloudways' => array(
            '/var/www/cache/nginx',
            '/var/www/cache/nginx/{domain}',
            '/var/cache/cloudways-nginx',
            '/var/cache/cloudways-nginx/{domain}',
        ),
        
        // WP Engine paths
        'wpengine' => array(
            '/var/cache/wpengine-nginx',
            '/var/cache/wpengine/{domain}',
            '/tmp/wpengine-cache',
        ),
        
        // Kinsta paths
        'kinsta' => array(
            '/var/cache/kinsta-nginx',
            '/var/cache/kinsta/{domain}',
            '/tmp/kinsta-cache',
        ),
        
        // Custom/Docker paths
        'custom' => array(
            '/app/cache/nginx',
            '/app/var/cache/nginx',
            '/data/cache/nginx',
            '/cache/nginx',
            '/nginx-cache',
        )
    );
    
    /**
     * Detect all available cache paths
     *
     * @return array Array of detected cache paths with metadata
     */
    public static function detect_all_paths() {
        $detected_paths = array();
        $domain = self::get_current_domain();
        
        foreach (self::$cache_path_patterns as $environment => $paths) {
            foreach ($paths as $path_pattern) {
                // Replace domain placeholder
                $path = str_replace('{domain}', $domain, $path_pattern);
                
                if (is_dir($path)) {
                    $detected_paths[] = array(
                        'path' => $path,
                        'environment' => $environment,
                        'pattern' => $path_pattern,
                        'priority' => self::get_path_priority($environment),
                        'metadata' => self::analyze_cache_path($path)
                    );
                }
            }
        }
        
        // Sort by priority (higher priority first)
        usort($detected_paths, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        return $detected_paths;
    }
    
    /**
     * Get the best cache path for the current environment
     *
     * @return array|null Best cache path with metadata, or null if none found
     */
    public static function get_best_cache_path() {
        $paths = self::detect_all_paths();
        
        if (empty($paths)) {
            return null;
        }
        
        // Return the highest priority path that's also writable
        foreach ($paths as $path_info) {
            if ($path_info['metadata']['writable']) {
                return $path_info;
            }
        }
        
        // If no writable paths, return the highest priority one
        return $paths[0];
    }
    
    /**
     * Detect cache paths based on server configuration
     *
     * @return array Array of paths detected from server config
     */
    public static function detect_from_config() {
        $config_paths = array();
        
        // Check WordPress constants
        if (defined('RT_WP_NGINX_HELPER_CACHE_PATH')) {
            $config_paths['wp_constant'] = array(
                'path' => RT_WP_NGINX_HELPER_CACHE_PATH,
                'source' => 'WordPress constant',
                'priority' => 100,
                'metadata' => self::analyze_cache_path(RT_WP_NGINX_HELPER_CACHE_PATH)
            );
        }
        
        // Check Nginx configuration files
        $nginx_paths = self::parse_nginx_config();
        foreach ($nginx_paths as $path) {
            $config_paths['nginx_config_' . md5($path)] = array(
                'path' => $path,
                'source' => 'Nginx configuration',
                'priority' => 90,
                'metadata' => self::analyze_cache_path($path)
            );
        }
        
        // Check environment variables
        $env_paths = self::check_environment_variables();
        foreach ($env_paths as $var_name => $path) {
            $config_paths['env_' . $var_name] = array(
                'path' => $path,
                'source' => 'Environment variable: ' . $var_name,
                'priority' => 80,
                'metadata' => self::analyze_cache_path($path)
            );
        }
        
        return $config_paths;
    }
    
    /**
     * Validate and analyze a cache path
     *
     * @param string $path Path to analyze
     * @return array Analysis results
     */
    public static function analyze_cache_path($path) {
        $analysis = array(
            'exists' => false,
            'readable' => false,
            'writable' => false,
            'size_bytes' => 0,
            'size_human' => '0 B',
            'file_count' => 0,
            'last_modified' => null,
            'permissions' => null,
            'owner' => null,
            'errors' => array()
        );
        
        try {
            if (empty($path)) {
                $analysis['errors'][] = 'Path is empty';
                return $analysis;
            }
            
            if (!is_dir($path)) {
                $analysis['errors'][] = 'Path does not exist or is not a directory';
                return $analysis;
            }
            
            $analysis['exists'] = true;
            $analysis['readable'] = is_readable($path);
            $analysis['writable'] = is_writable($path);
            
            // Get permissions
            $perms = fileperms($path);
            if ($perms !== false) {
                $analysis['permissions'] = substr(sprintf('%o', $perms), -4);
            }
            
            // Get owner information (if possible)
            if (function_exists('posix_getpwuid')) {
                $owner_info = posix_getpwuid(fileowner($path));
                if ($owner_info) {
                    $analysis['owner'] = $owner_info['name'];
                }
            }
            
            if ($analysis['readable']) {
                // Get cache size
                $size_output = \shell_exec("du -sb " . escapeshellarg($path) . " 2>/dev/null");
                if ($size_output && preg_match('/^(\d+)/', $size_output, $matches)) {
                    $analysis['size_bytes'] = (int) $matches[1];
                    $analysis['size_human'] = size_format($analysis['size_bytes']);
                }
                
                // Get file count
                $files_output = \shell_exec("find " . escapeshellarg($path) . " -type f 2>/dev/null | wc -l");
                if ($files_output) {
                    $analysis['file_count'] = (int) trim($files_output);
                }
                
                // Get last modified time
                $mtime = filemtime($path);
                if ($mtime !== false) {
                    $analysis['last_modified'] = wp_date('Y-m-d H:i:s', $mtime);
                }
            } else {
                $analysis['errors'][] = 'Path is not readable';
            }
            
            if (!$analysis['writable']) {
                $analysis['errors'][] = 'Path is not writable (cache clearing may fail)';
            }
            
        } catch (\Exception $e) {
            $analysis['errors'][] = $e->getMessage();
            ErrorHandler::log(
                'Cache path analysis failed',
                ErrorHandler::LOG_LEVEL_WARNING,
                array('path' => $path),
                $e
            );
        }
        
        return $analysis;
    }
    
    /**
     * Get current domain for path detection
     *
     * @return string Current domain
     */
    private static function get_current_domain() {
        $domain = '';
        
        if (defined('DOMAIN_CURRENT_SITE')) {
            $domain = DOMAIN_CURRENT_SITE;
        } elseif (isset($_SERVER['HTTP_HOST'])) {
            $domain = $_SERVER['HTTP_HOST'];
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $domain = $_SERVER['SERVER_NAME'];
        } else {
            // Fallback to site URL
            $site_url = get_site_url();
            $parsed = parse_url($site_url);
            $domain = isset($parsed['host']) ? $parsed['host'] : 'localhost';
        }
        
        // Clean domain (remove www, ports, etc.)
        $domain = preg_replace('/^www\./', '', $domain);
        $domain = preg_replace('/:\d+$/', '', $domain);
        
        return $domain;
    }
    
    /**
     * Get priority for different hosting environments
     *
     * @param string $environment Environment name
     * @return int Priority (higher = better)
     */
    private static function get_path_priority($environment) {
        $priorities = array(
            'gridpane' => 100,    // Highest priority for GridPane
            'common' => 90,       // Common server paths
            'easyengine' => 80,   // EasyEngine
            'plesk' => 70,        // Plesk
            'cpanel' => 70,       // cPanel
            'cloudways' => 60,    // Cloudways
            'wpengine' => 60,     // WP Engine
            'kinsta' => 60,       // Kinsta
            'custom' => 50        // Custom/Docker paths
        );
        
        return isset($priorities[$environment]) ? $priorities[$environment] : 40;
    }
    
    /**
     * Parse Nginx configuration files for cache paths
     *
     * @return array Array of cache paths found in config
     */
    private static function parse_nginx_config() {
        $paths = array();
        $config_files = array(
            '/etc/nginx/nginx.conf',
            '/etc/nginx/conf.d/*.conf',
            '/etc/nginx/sites-enabled/*',
            '/usr/local/nginx/conf/nginx.conf',
            '/opt/nginx/conf/nginx.conf'
        );
        
        foreach ($config_files as $pattern) {
            $files = glob($pattern);
            if ($files) {
                foreach ($files as $file) {
                    if (is_readable($file)) {
                        $content = file_get_contents($file);
                        if ($content) {
                            // Look for fastcgi_cache_path directives
                            if (preg_match_all('/fastcgi_cache_path\s+([^\s;]+)/', $content, $matches)) {
                                $paths = array_merge($paths, $matches[1]);
                            }
                            
                            // Look for proxy_cache_path directives
                            if (preg_match_all('/proxy_cache_path\s+([^\s;]+)/', $content, $matches)) {
                                $paths = array_merge($paths, $matches[1]);
                            }
                        }
                    }
                }
            }
        }
        
        return array_unique($paths);
    }
    
    /**
     * Check environment variables for cache paths
     *
     * @return array Array of paths from environment variables
     */
    private static function check_environment_variables() {
        $env_vars = array(
            'NGINX_CACHE_PATH',
            'FASTCGI_CACHE_PATH',
            'PROXY_CACHE_PATH',
            'CACHE_DIR',
            'NGINX_CACHE_DIR'
        );
        
        $paths = array();
        
        foreach ($env_vars as $var) {
            $value = getenv($var);
            if ($value && is_dir($value)) {
                $paths[$var] = $value;
            }
        }
        
        return $paths;
    }
    
    /**
     * Get comprehensive cache path report
     *
     * @return array Detailed report of all cache paths
     */
    public static function get_comprehensive_report() {
        return array(
            'detected_paths' => self::detect_all_paths(),
            'config_paths' => self::detect_from_config(),
            'best_path' => self::get_best_cache_path(),
            'recommendations' => self::get_recommendations(),
            'system_info' => self::get_system_info()
        );
    }
    
    /**
     * Get recommendations based on detected paths
     *
     * @return array Array of recommendations
     */
    private static function get_recommendations() {
        $recommendations = array();
        $paths = self::detect_all_paths();
        $config_paths = self::detect_from_config();
        
        if (empty($paths) && empty($config_paths)) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => 'No cache paths detected. Nginx page caching may not be configured.'
            );
        }
        
        $writable_paths = array_filter($paths, function($path) {
            return $path['metadata']['writable'];
        });
        
        if (empty($writable_paths)) {
            $recommendations[] = array(
                'type' => 'error',
                'message' => 'No writable cache paths found. Cache clearing will not work properly.'
            );
        }
        
        if (count($paths) > 3) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => 'Multiple cache paths detected. Consider consolidating for better performance.'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Get system information relevant to cache detection
     *
     * @return array System information
     */
    private static function get_system_info() {
        return array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
            'current_user' => get_current_user(),
            'document_root' => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'Unknown',
            'wp_content_dir' => WP_CONTENT_DIR,
            'domain' => self::get_current_domain()
        );
    }
}
