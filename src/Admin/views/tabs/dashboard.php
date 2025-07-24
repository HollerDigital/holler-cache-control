<?php
/**
 * Dashboard Tab - Cache Status Overview
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/views/tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Global Cache Actions -->
<div class="cache-actions-global">
    <h2><?php _e('Cache Management', 'holler-cache-control'); ?></h2>
    <p><?php _e('Manage all your cache systems from one central location.', 'holler-cache-control'); ?></p>
    <button type="button" class="button button-primary button-hero purge-cache" data-type="all">
        <span class="dashicons dashicons-update"></span>
        <?php _e('Purge All Caches', 'holler-cache-control'); ?>
    </button>
</div>

<!-- Quick Actions -->
<!-- <div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Quick Actions', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <?php if ($nginx_status['status'] === 'active'): ?>
                <button type="button" class="button purge-cache" data-type="nginx">
                    <span class="dashicons dashicons-performance"></span>
                    <?php _e('Purge Page Cache', 'holler-cache-control'); ?>
                </button>
            <?php endif; ?>
            
            <?php if ($redis_status['status'] === 'active'): ?>
                <button type="button" class="button purge-cache" data-type="redis">
                    <span class="dashicons dashicons-database"></span>
                    <?php _e('Purge Object Cache', 'holler-cache-control'); ?>
                </button>
            <?php endif; ?>
            
            <?php if ($cloudflare_status['status'] === 'active'): ?>
                <button type="button" class="button purge-cache" data-type="cloudflare">
                    <span class="dashicons dashicons-cloud"></span>
                    <?php _e('Purge Cloudflare', 'holler-cache-control'); ?>
                </button>
            <?php endif; ?>
            
            <?php if ($cloudflare_apo_status['status'] === 'active'): ?>
                <button type="button" class="button purge-cache" data-type="cloudflare-apo">
                    <span class="dashicons dashicons-cloud"></span>
                    <?php _e('Purge APO Cache', 'holler-cache-control'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div> -->

<!-- Cache Status Grid -->
<div class="cache-status-grid">
    <?php include_once dirname(__DIR__) . '/partials/cache-cards.php'; ?>
</div>


<!-- Recent Activity (Placeholder for future enhancement) -->
<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Recent Activity', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Cache purge activity and system events will be displayed here in a future update.', 'holler-cache-control'); ?></p>
        <p><em><?php _e('For now, you can check your server logs or use WP-CLI commands for detailed diagnostics.', 'holler-cache-control'); ?></em></p>
        <div style="margin-top: 12px;">
            <code>wp holler-cache status</code><br>
            <code>wp holler-cache diagnostics</code>
        </div>
    </div>
</div>
