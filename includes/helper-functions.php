<?php
/**
 * Helper functions for Holler Cache Control
 *
 * @package HollerCacheControl
 */

namespace Holler\CacheControl;

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

/**
 * Get Cloudflare configuration guidance for wp-config.php
 *
 * @return string Configuration instructions
 */
function get_cloudflare_config_guidance() {
    return '
/* Cloudflare Cache Control Configuration (Recommended Method)
 * Add these constants to your wp-config.php file for secure credential management.
 * These will take priority over admin UI settings.
 */
define("CLOUDFLARE_EMAIL", "your-cloudflare-email@example.com");
define("CLOUDFLARE_API_KEY", "your-cloudflare-global-api-key");
define("CLOUDFLARE_ZONE_ID", "your-cloudflare-zone-id");

/* How to find your Cloudflare credentials:
 * 1. Email: Your Cloudflare account email
 * 2. API Key: Cloudflare Dashboard > My Profile > API Tokens > Global API Key
 * 3. Zone ID: Cloudflare Dashboard > Select Domain > Overview (right sidebar)
 */
';
}

/**
 * Check if Cloudflare credentials are properly configured
 *
 * @return array Configuration status with recommendations
 */
function get_cloudflare_config_status() {
    $has_config_email = defined('CLOUDFLARE_EMAIL') && !empty(CLOUDFLARE_EMAIL);
    $has_config_key = defined('CLOUDFLARE_API_KEY') && !empty(CLOUDFLARE_API_KEY);
    $has_config_zone = defined('CLOUDFLARE_ZONE_ID') && !empty(CLOUDFLARE_ZONE_ID);
    
    $has_admin_email = !empty(get_option('cloudflare_email'));
    $has_admin_key = !empty(get_option('cloudflare_api_key'));
    $has_admin_zone = !empty(get_option('cloudflare_zone_id'));
    
    $all_in_config = $has_config_email && $has_config_key && $has_config_zone;
    $all_in_admin = $has_admin_email && $has_admin_key && $has_admin_zone;
    $mixed_sources = (!$all_in_config && !$all_in_admin) && 
                    (($has_config_email || $has_config_key || $has_config_zone) || 
                     ($has_admin_email || $has_admin_key || $has_admin_zone));
    
    return array(
        'all_in_config' => $all_in_config,
        'all_in_admin' => $all_in_admin,
        'mixed_sources' => $mixed_sources,
        'fully_configured' => $all_in_config || $all_in_admin || 
                             (($has_config_email || $has_admin_email) && 
                              ($has_config_key || $has_admin_key) && 
                              ($has_config_zone || $has_admin_zone)),
        'recommendation' => $all_in_config ? 'optimal' : 
                           ($all_in_admin ? 'use_config' : 
                           ($mixed_sources ? 'consolidate' : 'configure'))
    );
}

/**
 * Check if Slack integration is disabled
 *
 * @return bool True if Slack is disabled, false if enabled
 */
function is_slack_disabled() {
    return get_option('holler_cache_slack_disabled', true); // Default to disabled
}

/**
 * Enable Slack integration
 *
 * @return bool True on success, false on failure
 */
function enable_slack_integration() {
    return update_option('holler_cache_slack_disabled', false);
}

/**
 * Disable Slack integration
 *
 * @return bool True on success, false on failure
 */
function disable_slack_integration() {
    return update_option('holler_cache_slack_disabled', true);
}

/**
 * Get Slack integration status with details
 *
 * @return array Status information about Slack integration
 */
function get_slack_integration_status() {
    $is_disabled = is_slack_disabled();
    $webhook_url = get_option('holler_cache_control_slack_webhook', '');
    $has_webhook = !empty($webhook_url);
    
    return array(
        'enabled' => !$is_disabled,
        'disabled' => $is_disabled,
        'webhook_configured' => $has_webhook,
        'fully_configured' => !$is_disabled && $has_webhook,
        'status' => $is_disabled ? 'disabled' : ($has_webhook ? 'active' : 'enabled_no_webhook'),
        'message' => $is_disabled ? 
            __('Slack integration is disabled.', 'holler-cache-control') : 
            ($has_webhook ? 
                __('Slack integration is active and configured.', 'holler-cache-control') : 
                __('Slack integration is enabled but webhook URL not configured.', 'holler-cache-control')
            )
    );
}
