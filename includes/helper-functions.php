<?php
/**
 * Helper functions for Holler Cache Control
 *
 * @package HollerCacheControl
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Get cache method from wp-config.php settings
 *
 * @return string Cache method (enable_fastcgi, enable_redis, or disable_redis)
 */
function get_nginx_cache_method() {
    if (defined('RT_WP_NGINX_HELPER_CACHE_METHOD')) {
        return RT_WP_NGINX_HELPER_CACHE_METHOD;
    }
    return 'disable_redis'; // Default to disabled
}

/**
 * Get cache purge method from wp-config.php settings
 *
 * @return string Purge method (get_request_torden or delete_request)
 */
function get_nginx_purge_method() {
    if (defined('RT_WP_NGINX_HELPER_PURGE_METHOD')) {
        return RT_WP_NGINX_HELPER_PURGE_METHOD;
    }
    return 'delete_request'; // Default to delete request
}

/**
 * Get Redis connection settings from wp-config.php
 *
 * @return array Redis connection settings
 */
function get_redis_settings() {
    return array(
        'database' => defined('RT_WP_NGINX_HELPER_REDIS_DATABASE') ? RT_WP_NGINX_HELPER_REDIS_DATABASE : '1',
        'hostname' => defined('RT_WP_NGINX_HELPER_REDIS_HOSTNAME') ? RT_WP_NGINX_HELPER_REDIS_HOSTNAME : '127.0.0.1',
        'port' => defined('RT_WP_NGINX_HELPER_REDIS_PORT') ? RT_WP_NGINX_HELPER_REDIS_PORT : '6378',
        'username' => defined('RT_WP_NGINX_HELPER_REDIS_USERNAME') ? RT_WP_NGINX_HELPER_REDIS_USERNAME : '',
        'password' => defined('RT_WP_NGINX_HELPER_REDIS_PASSWORD') ? RT_WP_NGINX_HELPER_REDIS_PASSWORD : '',
        'prefix' => defined('RT_WP_NGINX_HELPER_REDIS_PREFIX') ? RT_WP_NGINX_HELPER_REDIS_PREFIX : 'db1:'
    );
}
