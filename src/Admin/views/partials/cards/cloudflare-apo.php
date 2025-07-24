<?php
if (!defined('ABSPATH')) {
    exit;
}

use Holler\CacheControl\Admin\Cache\Cloudflare;

?>
<!-- APO Card -->
<div class="cache-status-card <?php echo $cloudflare_apo_status['status'] === 'active' ? 'active' : 'inactive'; ?>">
    <div class="cache-status-header">
        <h3><?php _e('Automatic Platform Optimization', 'holler-cache-control'); ?></h3>
        <span class="status-indicator"></span>
    </div>
    <div class="cache-status-content">
        <p><?php echo esc_html($cloudflare_apo_status['message']); ?></p>
        <?php 
        $apo_info = Cloudflare::get_apo_info();
        if ($apo_info): ?>
            <div class="cache-details">
                <p>
                    <strong><?php _e('Cache by Device Type:', 'holler-cache-control'); ?></strong>
                    <?php echo $apo_info['cache_by_device_type'] ? 
                        esc_html__('Enabled', 'holler-cache-control') : 
                        esc_html__('Disabled', 'holler-cache-control'); ?>
                </p>
                <p>
                    <strong><?php _e('Cache by Location:', 'holler-cache-control'); ?></strong>
                    <?php echo $apo_info['cache_by_location'] ? 
                        esc_html__('Enabled', 'holler-cache-control') : 
                        esc_html__('Disabled', 'holler-cache-control'); ?>
                </p>
                <?php if (!empty($apo_info['cache_stats'])): ?>
                    <div class="details">
                        <p><strong><?php _e('Last 24 Hours:', 'holler-cache-control'); ?></strong></p>
                        <p>
                            <strong><?php _e('Total Requests:', 'holler-cache-control'); ?></strong>
                            <?php echo number_format($apo_info['cache_stats']['requests']); ?>
                        </p>
                        <p>
                            <strong><?php _e('Cached Requests:', 'holler-cache-control'); ?></strong>
                            <?php echo number_format($apo_info['cache_stats']['cached_requests']); ?>
                        </p>
                        <p>
                            <strong><?php _e('Cache Hit Rate:', 'holler-cache-control'); ?></strong>
                            <?php echo esc_html($apo_info['cache_stats']['cache_hit_rate']); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($cloudflare_apo_status['status'] === 'active'): ?>
            <div class="cache-actions">
                <button type="button" class="button button-primary purge-cache" data-type="cloudflare_apo">
                    <?php _e('Purge APO Cache', 'holler-cache-control'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>
