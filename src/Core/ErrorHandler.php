<?php
/**
 * Centralized Error Handling for Holler Cache Control
 *
 * @package HollerCacheControl
 */

namespace Holler\CacheControl\Core;

class ErrorHandler {

    /**
     * Log levels for different types of errors
     */
    const LOG_LEVEL_DEBUG = 'debug';
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_CRITICAL = 'critical';

    /**
     * Calculate directory size and file count using PHP native functions
     *
     * @param string $path Directory path
     * @return array Array with 'size_bytes' and 'files' keys
     */
    private static function calculate_directory_stats($path) {
        $size_bytes = 0;
        $files = 0;

        try {
            if (!is_dir($path)) {
                return ['size_bytes' => 0, 'files' => 0];
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size_bytes += $file->getSize();
                    $files++;
                }
            }
        } catch (\Exception $e) {
            // If we can't read the directory, return zeros
            return ['size_bytes' => 0, 'files' => 0];
        }

        return [
            'size_bytes' => $size_bytes,
            'files' => $files
        ];
    }

    /**
     * Log an error with context and proper formatting
     *
     * @param string $message Error message
     * @param string $level Log level
     * @param array $context Additional context information
     * @param \Exception|null $exception Optional exception object
     * @return void
     */
    public static function log($message, $level = self::LOG_LEVEL_ERROR, $context = array(), $exception = null) {
        // Build log message with context
        $log_message = sprintf(
            '[Holler Cache Control] [%s] %s',
            strtoupper($level),
            $message
        );
        
        // Add context information if provided
        if (!empty($context)) {
            $log_message .= ' | Context: ' . wp_json_encode($context);
        }
        
        // Add exception details if provided
        if ($exception instanceof \Exception) {
            $log_message .= sprintf(
                ' | Exception: %s in %s:%d | Trace: %s',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );
        }
        
        // Log to WordPress error log
        error_log($log_message);
        
        // For critical errors, also try to log to a plugin-specific log file
        if ($level === self::LOG_LEVEL_CRITICAL) {
            self::log_to_file($log_message);
        }
    }
    
    /**
     * Handle cache operation errors with standardized response
     *
     * @param string $operation Operation that failed (e.g., 'nginx_purge', 'redis_connect')
     * @param \Exception $exception Exception that occurred
     * @param array $context Additional context
     * @return array Standardized error response
     */
    public static function handle_cache_error($operation, $exception, $context = array()) {
        $error_code = self::generate_error_code($operation, $exception);
        
        self::log(
            sprintf('Cache operation failed: %s', $operation),
            self::LOG_LEVEL_ERROR,
            array_merge($context, array('error_code' => $error_code)),
            $exception
        );
        
        return array(
            'success' => false,
            'message' => self::get_user_friendly_message($operation, $exception),
            'error_code' => $error_code,
            'debug_info' => WP_DEBUG ? $exception->getMessage() : null
        );
    }
    
    /**
     * Handle API errors with retry logic consideration
     *
     * @param string $api_name API name (e.g., 'cloudflare', 'redis')
     * @param \Exception $exception Exception that occurred
     * @param array $context Additional context including retry count
     * @return array Standardized error response with retry information
     */
    public static function handle_api_error($api_name, $exception, $context = array()) {
        $retry_count = isset($context['retry_count']) ? $context['retry_count'] : 0;
        $max_retries = isset($context['max_retries']) ? $context['max_retries'] : 3;
        
        $should_retry = $retry_count < $max_retries && self::is_retryable_error($exception);
        
        self::log(
            sprintf('API operation failed: %s (retry %d/%d)', $api_name, $retry_count, $max_retries),
            $should_retry ? self::LOG_LEVEL_WARNING : self::LOG_LEVEL_ERROR,
            $context,
            $exception
        );
        
        return array(
            'success' => false,
            'message' => self::get_user_friendly_message($api_name, $exception),
            'should_retry' => $should_retry,
            'retry_count' => $retry_count,
            'next_retry_delay' => $should_retry ? self::calculate_retry_delay($retry_count) : 0,
            'debug_info' => WP_DEBUG ? $exception->getMessage() : null
        );
    }
    
    /**
     * Validate cache paths and return detailed validation results
     *
     * @param array $paths Array of cache paths to validate
     * @return array Validation results with detailed information
     */
    public static function validate_cache_paths($paths) {
        $results = array();
        
        foreach ($paths as $path_name => $path) {
            $validation = array(
                'path' => $path,
                'exists' => false,
                'readable' => false,
                'writable' => false,
                'size' => 0,
                'files' => 0,
                'errors' => array()
            );
            
            try {
                if (empty($path)) {
                    $validation['errors'][] = 'Path is empty';
                } elseif (!is_dir($path)) {
                    $validation['errors'][] = 'Path does not exist or is not a directory';
                } else {
                    $validation['exists'] = true;
                    $validation['readable'] = is_readable($path);
                    $validation['writable'] = is_writable($path);

                    if ($validation['readable']) {
                        // Get cache statistics using PHP native functions
                        $dir_stats = self::calculate_directory_stats($path);
                        $validation['size'] = $dir_stats['size_bytes'];
                        $validation['files'] = $dir_stats['files'];
                    } else {
                        $validation['errors'][] = 'Path is not readable';
                    }
                    
                    if (!$validation['writable']) {
                        $validation['errors'][] = 'Path is not writable';
                    }
                }
            } catch (\Exception $e) {
                $validation['errors'][] = $e->getMessage();
                self::log(
                    sprintf('Cache path validation failed: %s', $path_name),
                    self::LOG_LEVEL_WARNING,
                    array('path' => $path),
                    $e
                );
            }
            
            $results[$path_name] = $validation;
        }
        
        return $results;
    }
    
    /**
     * Generate a unique error code for tracking
     *
     * @param string $operation Operation name
     * @param \Exception $exception Exception object
     * @return string Error code
     */
    private static function generate_error_code($operation, $exception) {
        return sprintf(
            'HCC_%s_%s_%d',
            strtoupper(str_replace(' ', '_', $operation)),
            strtoupper(substr(get_class($exception), strrpos(get_class($exception), '\\') + 1)),
            crc32($exception->getMessage()) & 0x7FFFFFFF
        );
    }
    
    /**
     * Get user-friendly error message
     *
     * @param string $operation Operation name
     * @param \Exception $exception Exception object
     * @return string User-friendly message
     */
    private static function get_user_friendly_message($operation, $exception) {
        $generic_messages = array(
            'nginx_purge' => __('Failed to clear Nginx cache. Please check server configuration.', 'holler-cache-control'),
            'redis_connect' => __('Failed to connect to Redis server. Please check Redis configuration.', 'holler-cache-control'),
            'cloudflare' => __('Failed to communicate with Cloudflare API. Please check your credentials.', 'holler-cache-control'),
            'file_operation' => __('Failed to perform file operation. Please check file permissions.', 'holler-cache-control')
        );
        
        // Return specific message if available, otherwise generic
        return isset($generic_messages[$operation]) ? 
            $generic_messages[$operation] : 
            sprintf(__('Operation failed: %s', 'holler-cache-control'), $operation);
    }
    
    /**
     * Determine if an error is retryable
     *
     * @param \Exception $exception Exception to check
     * @return bool Whether the error is retryable
     */
    private static function is_retryable_error($exception) {
        $retryable_patterns = array(
            'timeout',
            'connection refused',
            'temporary failure',
            'rate limit',
            'service unavailable',
            'gateway timeout'
        );
        
        $message = strtolower($exception->getMessage());
        
        foreach ($retryable_patterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate retry delay with exponential backoff
     *
     * @param int $retry_count Current retry count
     * @return int Delay in seconds
     */
    private static function calculate_retry_delay($retry_count) {
        // Exponential backoff: 2^retry_count seconds, max 30 seconds
        return min(pow(2, $retry_count), 30);
    }
    
    /**
     * Log to plugin-specific log file
     *
     * @param string $message Message to log
     * @return void
     */
    private static function log_to_file($message) {
        $log_file = WP_CONTENT_DIR . '/holler-cache-control.log';
        $timestamp = wp_date('Y-m-d H:i:s');
        $formatted_message = sprintf("[%s] %s\n", $timestamp, $message);
        
        // Attempt to write to log file (fail silently if not possible)
        @file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
    }
}
