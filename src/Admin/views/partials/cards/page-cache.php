<?php
if (!defined('ABSPATH')) {
    exit;
}

use Holler\CacheControl\Admin\Cache\Nginx;

?>
<!-- Page Cache -->
<div class="cache-status-card <?php echo $page_cache_status['status'] === 'active' ? 'active' : 'inactive'; ?>">
    <div class="cache-status-header">
        <h3><?php _e('Page Cache', 'holler-cache-control'); ?></h3>
        <span class="status-indicator"></span>
    </div>
    <div class="cache-status-content">
        <p><?php echo esc_html($page_cache_status['message']); ?></p>
        <?php 
        if ($page_cache_status['status'] === 'active'): 
            $cache_info = Nginx::get_cache_info();
            error_log('Cache Info: ' . print_r($cache_info, true));
            if ($cache_info && is_array($cache_info)): ?>
                <div class="cache-details">
                    <?php if (isset($cache_info['type']) && $cache_info['type'] === 'redis'): ?>
                        <p>
                            <strong><?php _e('Cache Type:', 'holler-cache-control'); ?></strong>
                            <?php _e('Redis', 'holler-cache-control'); ?>
                        </p>
                        <p>
                            <strong><?php _e('Memory Usage:', 'holler-cache-control'); ?></strong>
                            <?php echo esc_html($cache_info['size']); ?>
                        </p>
                        <p>
                            <strong><?php _e('Total Keys:', 'holler-cache-control'); ?></strong>
                            <?php echo isset($cache_info['files']) ? number_format($cache_info['files']) : '0'; ?>
                        </p>
                        <?php if (isset($cache_info['hit_rate'])): ?>
                            <div class="details">
                                <p>
                                    <strong><?php _e('Cache Hit Rate:', 'holler-cache-control'); ?></strong>
                                    <?php echo esc_html($cache_info['hit_rate']); ?>%
                                </p>
                                <p>
                                    <strong><?php _e('Cache Hits:', 'holler-cache-control'); ?></strong>
                                    <?php echo number_format($cache_info['hits']); ?>
                                </p>
                                <p>
                                    <strong><?php _e('Cache Misses:', 'holler-cache-control'); ?></strong>
                                    <?php echo number_format($cache_info['misses']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($cache_info['config'])): ?>
                            <div class="details">
                                <p>
                                    <strong><?php _e('Max Memory:', 'holler-cache-control'); ?></strong>
                                    <?php echo esc_html($cache_info['config']['max_memory']); ?>
                                </p>
                                <p>
                                    <strong><?php _e('Eviction Policy:', 'holler-cache-control'); ?></strong>
                                    <?php echo esc_html($cache_info['config']['eviction_policy']); ?>
                                </p>
                                <p>
                                    <strong><?php _e('Uptime:', 'holler-cache-control'); ?></strong>
                                    <?php echo esc_html($cache_info['config']['uptime']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>
                            <strong><?php _e('Cache Type:', 'holler-cache-control'); ?></strong>
                            <?php _e('FastCGI', 'holler-cache-control'); ?>
                        </p>
                        <p>
                            <strong><?php _e('Cache Size:', 'holler-cache-control'); ?></strong>
                            <?php echo esc_html($cache_info['size']); ?>
                        </p>
                        <p>
                            <strong><?php _e('Cached Files:', 'holler-cache-control'); ?></strong>
                            <?php echo isset($cache_info['files']) ? number_format($cache_info['files']) : '0'; ?>
                        </p>
                        <?php if (isset($cache_info['hit_rate'])): ?>
                            <div class="details">
                                <p>
                                    <strong><?php _e('Cache Hit Rate:', 'holler-cache-control'); ?></strong>
                                    <?php echo esc_html($cache_info['hit_rate']); ?>%
                                </p>
                                <p>
                                    <strong><?php _e('Cache Hits:', 'holler-cache-control'); ?></strong>
                                    <?php echo number_format($cache_info['hits']); ?>
                                </p>
                                <p>
                                    <strong><?php _e('Cache Misses:', 'holler-cache-control'); ?></strong>
                                    <?php echo number_format($cache_info['misses']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($cache_info['config'])): ?>
                            <div class="details">
                                <p>
                                    <strong><?php _e('Cache Valid:', 'holler-cache-control'); ?></strong>
                                    <?php echo esc_html($cache_info['config']['cache_valid']); ?>
                                </p>
                                <p>
                                    <strong><?php _e('Min Uses:', 'holler-cache-control'); ?></strong>
                                    <?php echo esc_html($cache_info['config']['min_uses']); ?>
                                </p>
                                <?php if (isset($cache_info['config']['use_stale'])): ?>
                                    <p>
                                        <strong><?php _e('Use Stale:', 'holler-cache-control'); ?></strong>
                                        <?php echo esc_html($cache_info['config']['use_stale']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="cache-actions">
                <button type="button" class="button button-primary purge-cache" data-type="nginx">
                <span class="dashicons dashicons-performance"></span>
                    <?php _e('Purge Page Cache', 'holler-cache-control'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>
