<?php
/**
 * Advanced Tab - Advanced Settings & Developer Options
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/views/tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Developer & Advanced Options', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Advanced configuration options for developers and system administrators.', 'holler-cache-control'); ?></p>
        
        <div class="cache-details">
            <h4><?php _e('Constants & Configuration', 'holler-cache-control'); ?></h4>
            <p><?php _e('The following constants can be defined in wp-config.php to override default behavior:', 'holler-cache-control'); ?></p>
            
            <div style="overflow-x: auto;">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Constant', 'holler-cache-control'); ?></th>
                            <th><?php _e('Purpose', 'holler-cache-control'); ?></th>
                            <th><?php _e('Status', 'holler-cache-control'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>CLOUDFLARE_EMAIL</code></td>
                            <td><?php _e('Cloudflare account email', 'holler-cache-control'); ?></td>
                            <td><?php echo defined('CLOUDFLARE_EMAIL') ? '<span style="color: #46b450;">✓ Defined</span>' : '<span style="color: #dc3232;">✗ Not Defined</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><code>CLOUDFLARE_API_KEY</code></td>
                            <td><?php _e('Cloudflare API key or token', 'holler-cache-control'); ?></td>
                            <td><?php echo defined('CLOUDFLARE_API_KEY') ? '<span style="color: #46b450;">✓ Defined</span>' : '<span style="color: #dc3232;">✗ Not Defined</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><code>CLOUDFLARE_ZONE_ID</code></td>
                            <td><?php _e('Cloudflare zone ID for this domain', 'holler-cache-control'); ?></td>
                            <td><?php echo defined('CLOUDFLARE_ZONE_ID') ? '<span style="color: #46b450;">✓ Defined</span>' : '<span style="color: #dc3232;">✗ Not Defined</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><code>RT_WP_NGINX_HELPER_CACHE_PATH</code></td>
                            <td><?php _e('Override nginx cache path detection', 'holler-cache-control'); ?></td>
                            <td><?php echo defined('RT_WP_NGINX_HELPER_CACHE_PATH') ? '<span style="color: #46b450;">✓ Defined</span>' : '<span style="color: #dc3232;">✗ Not Defined</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><code>RT_WP_NGINX_HELPER_REDIS_*</code></td>
                            <td><?php _e('Redis connection settings', 'holler-cache-control'); ?></td>
                            <td><?php echo (defined('RT_WP_NGINX_HELPER_REDIS_HOSTNAME') || defined('RT_WP_NGINX_HELPER_REDIS_PORT')) ? '<span style="color: #46b450;">✓ Some Defined</span>' : '<span style="color: #dc3232;">✗ Not Defined</span>'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Slack Integration Management', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <?php $slack_status = \Holler\CacheControl\get_slack_integration_status(); ?>
        
        <div class="cache-details">
            <h4><?php _e('Current Status', 'holler-cache-control'); ?></h4>
            <p>
                <span class="<?php echo $slack_status['enabled'] ? 'active' : 'inactive'; ?>">
                    <span class="status-indicator"></span>
                    <?php echo $slack_status['enabled'] ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?>
                </span>
            </p>
            <p><em><?php echo esc_html($slack_status['message']); ?></em></p>
        </div>
        
        <div class="cache-details">
            <h4><?php _e('Management Commands', 'holler-cache-control'); ?></h4>
            <p><?php _e('Use WP-CLI to manage Slack integration:', 'holler-cache-control'); ?></p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
                <div>
                    <strong><?php _e('Check Status:', 'holler-cache-control'); ?></strong><br>
                    <code>wp holler-cache slack status</code>
                </div>
                <div>
                    <strong><?php _e('Enable Integration:', 'holler-cache-control'); ?></strong><br>
                    <code>wp holler-cache slack enable</code>
                </div>
                <div>
                    <strong><?php _e('Disable Integration:', 'holler-cache-control'); ?></strong><br>
                    <code>wp holler-cache slack disable</code>
                </div>
            </div>
        </div>
        
        <?php if (!$slack_status['enabled']): ?>
            <div class="holler-notice notice-info">
                <p><strong><?php _e('Note:', 'holler-cache-control'); ?></strong> 
                <?php _e('Slack integration is disabled by default for security and performance reasons. Enable it only if you need Slack notifications for cache operations.', 'holler-cache-control'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Error Handling & Logging', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <div class="cache-details">
            <h4><?php _e('Error Handling System', 'holler-cache-control'); ?></h4>
            <p><?php _e('The plugin uses a centralized error handling system with the following features:', 'holler-cache-control'); ?></p>
            <ul>
                <li><?php _e('Standardized error logging with severity levels', 'holler-cache-control'); ?></li>
                <li><?php _e('Contextual error information for debugging', 'holler-cache-control'); ?></li>
                <li><?php _e('Retry logic for transient API failures', 'holler-cache-control'); ?></li>
                <li><?php _e('User-friendly error messages', 'holler-cache-control'); ?></li>
                <li><?php _e('Cache path validation and reporting', 'holler-cache-control'); ?></li>
            </ul>
        </div>
        
        <div class="cache-details">
            <h4><?php _e('Log Locations', 'holler-cache-control'); ?></h4>
            <p><?php _e('Error logs are written to the following locations:', 'holler-cache-control'); ?></p>
            <ul>
                <li><strong><?php _e('WordPress Debug Log:', 'holler-cache-control'); ?></strong> <code><?php echo WP_CONTENT_DIR; ?>/debug.log</code></li>
                <li><strong><?php _e('PHP Error Log:', 'holler-cache-control'); ?></strong> <?php echo ini_get('error_log') ?: __('System default', 'holler-cache-control'); ?></li>
                <li><strong><?php _e('Server Error Log:', 'holler-cache-control'); ?></strong> <?php _e('Check your hosting control panel', 'holler-cache-control'); ?></li>
            </ul>
        </div>
    </div>
</div>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Cache Path Detection System', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <div class="cache-details">
            <h4><?php _e('Supported Environments', 'holler-cache-control'); ?></h4>
            <p><?php _e('The plugin automatically detects cache paths for the following hosting environments:', 'holler-cache-control'); ?></p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                <div>
                    <strong><?php _e('GridPane', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Priority: 10 (Highest)', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('EasyEngine', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Priority: 8', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('Plesk', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Priority: 7', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('cPanel', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Priority: 6', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('Cloudways', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Priority: 5', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('WP Engine', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Priority: 4', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('Kinsta', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Priority: 4', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('Docker', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Priority: 3', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('Generic', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Priority: 1-2', 'holler-cache-control'); ?></small>
                </div>
            </div>
        </div>
        
        <div class="cache-details">
            <h4><?php _e('Path Detection Features', 'holler-cache-control'); ?></h4>
            <ul>
                <li><?php _e('Automatic environment detection based on server characteristics', 'holler-cache-control'); ?></li>
                <li><?php _e('Domain-specific cache directory support', 'holler-cache-control'); ?></li>
                <li><?php _e('Path validation (existence, permissions, writability)', 'holler-cache-control'); ?></li>
                <li><?php _e('Nginx configuration file parsing', 'holler-cache-control'); ?></li>
                <li><?php _e('Environment variable detection', 'holler-cache-control'); ?></li>
                <li><?php _e('Priority-based path selection', 'holler-cache-control'); ?></li>
                <li><?php _e('Comprehensive reporting and recommendations', 'holler-cache-control'); ?></li>
            </ul>
        </div>
    </div>
</div>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Plugin Architecture', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <div class="cache-details">
            <h4><?php _e('Core Components', 'holler-cache-control'); ?></h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                <div>
                    <strong><?php _e('ErrorHandler', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Centralized error management', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('CachePathDetector', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Intelligent cache path detection', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('Cache Managers', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Nginx, Redis, Cloudflare handlers', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('WP-CLI Integration', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Command-line management tools', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('Admin Interface', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Tabbed WordPress admin interface', 'holler-cache-control'); ?></small>
                </div>
                <div>
                    <strong><?php _e('Slack Integration', 'holler-cache-control'); ?></strong><br>
                    <small><?php _e('Optional notification system', 'holler-cache-control'); ?></small>
                </div>
            </div>
        </div>
        
        <div class="cache-details">
            <h4><?php _e('Security Features', 'holler-cache-control'); ?></h4>
            <ul>
                <li><?php _e('wp-config.php constant support for sensitive credentials', 'holler-cache-control'); ?></li>
                <li><?php _e('Nonce verification for all AJAX requests', 'holler-cache-control'); ?></li>
                <li><?php _e('Capability checks for admin functions', 'holler-cache-control'); ?></li>
                <li><?php _e('Input sanitization and output escaping', 'holler-cache-control'); ?></li>
                <li><?php _e('Secure credential fallback system', 'holler-cache-control'); ?></li>
            </ul>
        </div>
    </div>
</div>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Performance Considerations', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <div class="cache-details">
            <h4><?php _e('Optimization Features', 'holler-cache-control'); ?></h4>
            <ul>
                <li><?php _e('Lazy loading of cache detection (only when needed)', 'holler-cache-control'); ?></li>
                <li><?php _e('Efficient path validation with caching', 'holler-cache-control'); ?></li>
                <li><?php _e('Minimal database queries', 'holler-cache-control'); ?></li>
                <li><?php _e('Asynchronous AJAX operations', 'holler-cache-control'); ?></li>
                <li><?php _e('Selective feature loading (Slack disabled by default)', 'holler-cache-control'); ?></li>
            </ul>
        </div>
        
        <div class="cache-details">
            <h4><?php _e('Resource Usage', 'holler-cache-control'); ?></h4>
            <p><strong><?php _e('Memory Usage:', 'holler-cache-control'); ?></strong> <?php echo esc_html(size_format(memory_get_usage())); ?></p>
            <p><strong><?php _e('Peak Memory:', 'holler-cache-control'); ?></strong> <?php echo esc_html(size_format(memory_get_peak_usage())); ?></p>
            <p><strong><?php _e('Memory Limit:', 'holler-cache-control'); ?></strong> <?php echo esc_html(ini_get('memory_limit')); ?></p>
        </div>
    </div>
</div>
