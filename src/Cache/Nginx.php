<?php
namespace HollerCacheControl\Cache;

/**
 * The Nginx cache handler class.
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl\Cache
 */
class Nginx {
    /**
     * Get Nginx cache status for GridPane environment
     *
     * @return array
     */
    public static function get_status() {
        $result = array(
            'active' => false,
            'details' => '',
            'type' => 'none'
        );

        // Check wp-config.php settings first
        $cache_method = \get_nginx_cache_method();
        if ($cache_method === 'enable_fastcgi') {
            $result['active'] = true;
            $result['type'] = 'nginx';
            $result['details'] = __('FastCGI Page Caching (Enabled via wp-config.php)', 'holler-cache-control');
            return $result;
        } elseif ($cache_method === 'enable_redis') {
            $result['active'] = true;
            $result['type'] = 'redis';
            $result['details'] = __('Redis Page Caching (Enabled via wp-config.php)', 'holler-cache-control');
            return $result;
        } elseif ($cache_method === 'disable_redis') {
            $result['active'] = false;
            $result['type'] = 'none';
            $result['details'] = __('Page Caching Disabled (via wp-config.php)', 'holler-cache-control');
            return $result;
        }
        
        // If no wp-config.php settings, check headers
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => \home_url(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 5
        ));
        
        $response = curl_exec($ch);
        if ($response === false) {
            $result['details'] = __('Could not check cache status', 'holler-cache-control');
            curl_close($ch);
            return $result;
        }
        
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        curl_close($ch);
        
        // Parse headers
        $headers_array = array();
        foreach (explode("\n", $headers) as $line) {
            $parts = explode(':', $line, 2);
            if (isset($parts[1])) {
                $headers_array[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }
        
        // Check for standard Nginx cache headers
        $nginx_headers = array(
            'x-nginx-cache',
            'x-fastcgi-cache',
            'x-proxy-cache'
        );
        
        foreach ($nginx_headers as $header) {
            if (isset($headers_array[$header])) {
                $result['active'] = true;
                $result['type'] = 'nginx';
                $result['details'] = sprintf(
                    __('Nginx Page Caching (Status: %s)', 'holler-cache-control'),
                    $headers_array[$header]
                );
                return $result;
            }
        }
        
        // Check for Redis cache headers
        $redis_headers = array(
            'x-grid-srcache-ttl',
            'x-grid-srcache-fetch',
            'x-grid-srcache-store',
            'x-grid-srcache-skip'
        );
        
        $has_redis_headers = false;
        foreach ($redis_headers as $header) {
            if (isset($headers_array[$header])) {
                $has_redis_headers = true;
                break;
            }
        }
        
        if ($has_redis_headers) {
            $result['active'] = true;
            $result['type'] = 'redis';
            
            // Get cache status if available
            if (isset($headers_array['x-grid-srcache-skip']) && !empty($headers_array['x-grid-srcache-skip'])) {
                $result['details'] = sprintf(
                    __('Redis Page Caching (Skipped: %s)', 'holler-cache-control'),
                    $headers_array['x-grid-srcache-skip']
                );
            } elseif (isset($headers_array['x-grid-srcache-ttl'])) {
                $result['details'] = sprintf(
                    __('Redis Page Caching (TTL: %s)', 'holler-cache-control'),
                    $headers_array['x-grid-srcache-ttl']
                );
            } else {
                $result['details'] = __('Redis Page Caching (Active)', 'holler-cache-control');
            }
        }
        
        return $result;
    }

    /**
     * Purge Nginx cache
     *
     * @return array
     */
    public static function purge_cache() {
        $result = array(
            'success' => false,
            'message' => ''
        );

        // Get cache method and purge method from wp-config.php
        $cache_method = \get_nginx_cache_method();
        $purge_method = \get_nginx_purge_method();

        // If cache is disabled, return success
        if ($cache_method === 'disable_redis') {
            $result['success'] = true;
            $result['message'] = __('Cache is disabled, nothing to purge', 'holler-cache-control');
            return $result;
        }

        $success = false;
        $messages = array();

        // Only attempt FastCGI purge if Redis is not enabled
        if ($cache_method !== 'enable_redis') {
            if ($purge_method === 'get_request_torden') {
                // Use GET request with torden parameter
                $purge_url = \home_url('?torden=true');
                $ch = curl_init($purge_url);
                curl_setopt_array($ch, array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT => 5
                ));
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code >= 200 && $http_code < 300) {
                    $success = true;
                    $messages[] = __('FastCGI cache purged successfully', 'holler-cache-control');
                }
            } else {
                // Use DELETE request
                $purge_url = \home_url();
                $ch = curl_init($purge_url);
                curl_setopt_array($ch, array(
                    CURLOPT_CUSTOMREQUEST => 'DELETE',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT => 5
                ));
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code >= 200 && $http_code < 300) {
                    $success = true;
                    $messages[] = __('FastCGI cache purged successfully', 'holler-cache-control');
                }
            }
        }

        // Try Redis cache purge if enabled
        if ($cache_method === 'enable_redis') {
            $redis_settings = \get_redis_settings();
            try {
                $redis = new \Redis();
                $redis->connect($redis_settings['hostname'], $redis_settings['port']);
                
                // Authenticate if credentials are provided
                if (!empty($redis_settings['username']) && !empty($redis_settings['password'])) {
                    $redis->auth(array($redis_settings['username'], $redis_settings['password']));
                } elseif (!empty($redis_settings['password'])) {
                    $redis->auth($redis_settings['password']);
                }
                
                // Select database
                $redis->select($redis_settings['database']);
                
                // Clear cache keys with prefix
                $prefix = $redis_settings['prefix'];
                $keys = $redis->keys($prefix . '*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
                
                $success = true;
                $messages[] = __('Redis cache purged successfully', 'holler-cache-control');
            } catch (\Exception $e) {
                $messages[] = sprintf(
                    __('Failed to purge Redis cache: %s', 'holler-cache-control'),
                    $e->getMessage()
                );
            }
        }

        $result['success'] = $success;
        $result['message'] = implode("\n", array_filter($messages));
        
        return $result;
    }

    /**
     * Run a shell command safely
     *
     * @param string $command
     * @return string|null
     */
    private static function run_command($command) {
        if (!function_exists('\shell_exec')) {
            return null;
        }
        return \shell_exec($command . ' 2>&1');
    }

    /**
     * Delete directory contents recursively
     *
     * @param string $path
     */
    private static function delete_directory_contents($path) {
        $files = scandir($path);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $full_path = $path . '/' . $file;
            if (is_dir($full_path)) {
                self::delete_directory_contents($full_path);
                rmdir($full_path);
            } else {
                unlink($full_path);
            }
        }
    }

    /**
     * Get directory size recursively
     *
     * @param string $path
     * @return int
     */
    private static function get_directory_size($path) {
        $size = 0;
        $files = scandir($path);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $full_path = $path . '/' . $file;
            if (is_dir($full_path)) {
                $size += self::get_directory_size($full_path);
            } else {
                $size += filesize($full_path);
            }
        }

        return $size;
    }

    /**
     * Log error message
     *
     * @param string $message
     */
    private static function log_error($message) {
        error_log('[Holler Cache Control] ' . $message);
    }
}
