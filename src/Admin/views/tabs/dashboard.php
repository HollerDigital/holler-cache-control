<?php
/**
 * Dashboard Tab - Real-time Status Dashboard
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/views/tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Holler\CacheControl\Admin\Cache\Nginx;
use Holler\CacheControl\Admin\Cache\Redis;
use Holler\CacheControl\Admin\Cache\Cloudflare;
use Holler\CacheControl\Admin\Cache\CloudflareAPO;

// Display admin notices for form actions
if (isset($_GET['cache_cleared'])) {
    $cache_type = sanitize_text_field($_GET['cache_cleared']);
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'success';
    
    $messages = array(
        'all' => __('All caches have been successfully cleared.', 'holler-cache-control'),
        'nginx' => __('Nginx cache has been cleared.', 'holler-cache-control'),
        'redis' => __('Redis cache has been cleared.', 'holler-cache-control'),
        'cloudflare' => __('Cloudflare cache has been cleared.', 'holler-cache-control'),
        'cloudflare_apo' => __('Cloudflare APO cache has been cleared.', 'holler-cache-control')
    );
    
    $message = isset($messages[$cache_type]) ? $messages[$cache_type] : __('Cache operation completed.', 'holler-cache-control');
    $notice_class = ($status === 'error') ? 'notice-error' : 'notice-success';
    
    echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
}

if (isset($_GET['permissions_fixed'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . 
         esc_html__('GridPane permissions fix command has been executed. Check the debug logs for details.', 'holler-cache-control') . 
         '</p></div>';
}

// Get initial cache status data
$nginx_status = Nginx::get_status();
$redis_status = Redis::get_status();
$cloudflare_status = Cloudflare::get_status();
$cloudflare_apo_status = CloudflareAPO::get_status();

// Clear any cached status to force fresh data retrieval
delete_transient('holler_cache_status_nginx');
delete_transient('holler_cache_status_redis');
delete_transient('holler_cache_status_cloudflare');
delete_transient('holler_cache_status_cloudflare_apo');

// Get cache status directly from server-side using static methods
try {
    $cache_statuses = [
        'nginx' => \Holler\CacheControl\Admin\Cache\Nginx::get_fresh_status(),
        'redis' => \Holler\CacheControl\Admin\Cache\Redis::get_fresh_status(),
        'cloudflare' => \Holler\CacheControl\Admin\Cache\Cloudflare::get_fresh_status(),
        'cloudflare_apo' => \Holler\CacheControl\Admin\Cache\CloudflareAPO::get_fresh_status()
    ];
} catch (Exception $e) {
    error_log('Holler Cache Control: Dashboard cache status error: ' . $e->getMessage());
    $cache_statuses = [
        'nginx' => ['status' => 'error', 'message' => 'Unable to check status: ' . $e->getMessage()],
        'redis' => ['status' => 'error', 'message' => 'Unable to check status: ' . $e->getMessage()],
        'cloudflare' => ['status' => 'error', 'message' => 'Unable to check status: ' . $e->getMessage()],
        'cloudflare_apo' => ['status' => 'error', 'message' => 'Unable to check status: ' . $e->getMessage()]
    ];
}

?>

<div class="holler-dashboard static-dashboard">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="header-content">
            <h2 class="dashboard-title">
                <span class="dashicons dashicons-dashboard"></span>
                Cache Status Dashboard
            </h2>
            <div class="dashboard-controls">
                <button type="button" onclick="location.reload()" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    Refresh Page
                </button>
            </div>
        </div>
    </div>

    <!-- Cache Status Cards -->
    <div class="dashboard-section cache-status-section">
        <h3 class="section-title">
            <span class="dashicons dashicons-performance"></span>
            Cache Services Status
        </h3>
        <div class="cache-cards-grid">
            
            <!-- Nginx Cache Card -->
            <div class="cache-card nginx-card">
                <div class="card-header">
                    <h4>Nginx FastCGI Cache</h4>
                    <span class="status-indicator status-<?php echo esc_attr($cache_statuses['nginx']['status']); ?>"></span>
                </div>
                <div class="card-content">
                    <p class="status-message"><?php echo esc_html($cache_statuses['nginx']['message'] ?? 'Status unknown'); ?></p>
                    <?php if (isset($cache_statuses['nginx']['details'])): ?>
                        <div class="cache-details">
                            <?php foreach ($cache_statuses['nginx']['details'] as $key => $value): ?>
                                <div class="detail-item">
                                    <span class="detail-label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</span>
                                    <span class="detail-value"><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-actions">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('holler_cache_action', 'holler_nonce'); ?>
                        <input type="hidden" name="cache_action" value="purge_nginx">
                        <button type="submit" class="button button-small">Purge Nginx Cache</button>
                    </form>
                </div>
            </div>

            <!-- Redis Cache Card -->
            <div class="cache-card redis-card">
                <div class="card-header">
                    <h4>Redis Object Cache</h4>
                    <span class="status-indicator status-<?php echo esc_attr($cache_statuses['redis']['status']); ?>"></span>
                </div>
                <div class="card-content">
                    <p class="status-message"><?php echo esc_html($cache_statuses['redis']['message'] ?? 'Status unknown'); ?></p>
                    <?php if (isset($cache_statuses['redis']['details'])): ?>
                        <div class="cache-details">
                            <?php foreach ($cache_statuses['redis']['details'] as $key => $value): ?>
                                <div class="detail-item">
                                    <span class="detail-label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</span>
                                    <span class="detail-value"><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-actions">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('holler_cache_action', 'holler_nonce'); ?>
                        <input type="hidden" name="cache_action" value="purge_redis">
                        <button type="submit" class="button button-small">Flush Redis Cache</button>
                    </form>
                </div>
            </div>

            <!-- Cloudflare Cache Card -->
            <div class="cache-card cloudflare-card">
                <div class="card-header">
                    <h4>Cloudflare Cache</h4>
                    <span class="status-indicator status-<?php echo esc_attr($cache_statuses['cloudflare']['status']); ?>"></span>
                </div>
                <div class="card-content">
                    <p class="status-message"><?php echo esc_html($cache_statuses['cloudflare']['message'] ?? 'Status unknown'); ?></p>
                    <?php if (isset($cache_statuses['cloudflare']['details'])): ?>
                        <div class="cache-details">
                            <?php foreach ($cache_statuses['cloudflare']['details'] as $key => $value): ?>
                                <div class="detail-item">
                                    <span class="detail-label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</span>
                                    <span class="detail-value"><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-actions">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('holler_cache_action', 'holler_nonce'); ?>
                        <input type="hidden" name="cache_action" value="purge_cloudflare">
                        <button type="submit" class="button button-small">Purge Cloudflare</button>
                    </form>

                </div>
            </div>

            <!-- Cloudflare APO Card -->
            <div class="cache-card cloudflare-apo-card">
                <div class="card-header">
                    <h4>Cloudflare APO</h4>
                    <span class="status-indicator status-<?php echo esc_attr($cache_statuses['cloudflare_apo']['status']); ?>"></span>
                </div>
                <div class="card-content">
                    <p class="status-message"><?php echo esc_html($cache_statuses['cloudflare_apo']['message'] ?? 'Status unknown'); ?></p>
                    <?php if (isset($cache_statuses['cloudflare_apo']['details'])): ?>
                        <div class="cache-details">
                            <?php foreach ($cache_statuses['cloudflare_apo']['details'] as $key => $value): ?>
                                <div class="detail-item">
                                    <span class="detail-label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</span>
                                    <span class="detail-value"><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-actions">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('holler_cache_action', 'holler_nonce'); ?>
                        <input type="hidden" name="cache_action" value="purge_cloudflare_apo">
                        <button type="submit" class="button button-small">Purge APO Cache</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="dashboard-section actions-section">
        <h3 class="section-title">
            <span class="dashicons dashicons-controls-play"></span>
            Quick Actions
        </h3>
        <div class="quick-actions-grid">
            <form method="post" class="action-form">
                <?php wp_nonce_field('holler_cache_action', 'holler_nonce'); ?>
                <input type="hidden" name="cache_action" value="purge_all">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-trash"></span>
                    Purge All Caches
                </button>
            </form>
            
            <button type="button" onclick="location.reload()" class="button button-secondary button-large">
                <span class="dashicons dashicons-update"></span>
                Refresh Status
            </button>
            
            <form method="post" class="action-form">
                <?php wp_nonce_field('holler_cache_action', 'holler_nonce'); ?>
                <input type="hidden" name="cache_action" value="fix_permissions">
                <button type="submit" class="button button-secondary button-large" title="Run GridPane CLI command: gp fix perms <?php echo esc_attr(home_url()); ?>">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Fix Permissions
                </button>
            </form>
        </div>
    </div>

    <!-- System Information -->
    <div class="dashboard-section info-section">
        <h3 class="section-title">
            <span class="dashicons dashicons-info"></span>
            System Information
        </h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Plugin Version:</span>
                <span class="info-value"><?php echo HOLLER_CACHE_CONTROL_VERSION; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">WordPress Version:</span>
                <span class="info-value"><?php echo get_bloginfo('version'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">PHP Version:</span>
                <span class="info-value"><?php echo PHP_VERSION; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Server Software:</span>
                <span class="info-value"><?php echo isset($_SERVER['SERVER_SOFTWARE']) ? esc_html($_SERVER['SERVER_SOFTWARE']) : 'Unknown'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Memory Limit:</span>
                <span class="info-value"><?php echo ini_get('memory_limit'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Max Execution Time:</span>
                <span class="info-value"><?php echo ini_get('max_execution_time'); ?>s</span>
            </div>
            <div class="info-item">
                <span class="info-label">Redis Extension:</span>
                <span class="info-value"><?php echo extension_loaded('redis') ? 'Installed' : 'Not Available'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">cURL Extension:</span>
                <span class="info-value"><?php echo extension_loaded('curl') ? 'Installed' : 'Not Available'; ?></span>
            </div>
        </div>
    </div>

    <!-- Performance Overview -->
    <div class="dashboard-section performance-section">
        <h3 class="section-title">
            <span class="dashicons dashicons-chart-line"></span>
            Performance Overview
        </h3>
        <div class="performance-grid">
            <div class="performance-card">
                <div class="performance-metric-value">N/A</div>
                <div class="performance-metric-label">Cache Hit Rate</div>
                <div class="performance-metric-note">Data not available in static mode</div>
            </div>
            <div class="performance-card">
                <div class="performance-metric-value">N/A</div>
                <div class="performance-metric-label">Avg Response Time</div>
                <div class="performance-metric-note">Data not available in static mode</div>
            </div>
            <div class="performance-card">
                <div class="performance-metric-value">—</div>
                <div class="performance-metric-label">Total Cache Size</div>
                <div class="performance-metric-note">Requires real-time dashboard</div>
            </div>
            <div class="performance-card">
                <div class="performance-metric-value">—</div>
                <div class="performance-metric-label">Purges Today</div>
                <div class="performance-metric-note">Requires real-time dashboard</div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="dashboard-section activity-section">
        <h3 class="section-title">
            <span class="dashicons dashicons-clock"></span>
            Recent Activity
        </h3>
        <div class="activity-content">
            <p class="no-activity"><?php _e('No recent activity', 'holler-cache-control'); ?></p>
        </div>
    </div>

    <!-- Dashboard Footer -->
    <div class="dashboard-footer">
        <div class="dashboard-stats">
            <div class="dashboard-stat">
                <span class="dashicons dashicons-clock"></span>
                <span><?php _e('Last Updated:', 'holler-cache-control'); ?> <span class="last-refresh-time">-</span></span>
            </div>
            <div class="dashboard-stat">
                <span class="dashicons dashicons-performance"></span>
                <span><?php _e('Load Time:', 'holler-cache-control'); ?> <span class="dashboard-load-time">-</span></span>
            </div>
            <div class="dashboard-stat">
                <span class="dashicons dashicons-database"></span>
                <span><?php _e('Status Cache Hits:', 'holler-cache-control'); ?> <span class="status-cache-hits">-</span></span>
            </div>
        </div>
        <div class="dashboard-version">
            <?php printf(__('Holler Cache Control v%s', 'holler-cache-control'), HOLLER_CACHE_CONTROL_VERSION); ?>
        </div>
    </div>
</div>
