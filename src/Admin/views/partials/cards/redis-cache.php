<?php
if (!defined('ABSPATH')) {
    exit;
}

use Holler\CacheControl\Admin\Cache\Redis;

?>
<!-- Redis Object Cache -->
<div class="cache-status-card <?php echo $redis_status['status'] === 'active' ? 'active' : 'inactive'; ?>">
    <div class="cache-status-header">
        <h3><?php _e('Redis Object Cache', 'holler-cache-control'); ?></h3>
        <span class="status-indicator"></span>
    </div>
    <div class="cache-status-content">
        <p><?php echo esc_html($redis_status['message']); ?></p>
        <?php if ($redis_status['status'] === 'active'): ?>
            <?php 
            $redis_info = Redis::get_cache_info();
            if ($redis_info): ?>
                <div class="cache-details">
                    <p>
                        <strong><?php _e('Memory Usage:', 'holler-cache-control'); ?></strong>
                        <?php echo esc_html($redis_info['memory']); ?>
                    </p>
                    <p>
                        <strong><?php _e('Keys:', 'holler-cache-control'); ?></strong>
                        <?php echo esc_html($redis_info['keys']); ?>
                    </p>
                    <p>
                        <strong><?php _e('Connected Clients:', 'holler-cache-control'); ?></strong>
                        <?php echo esc_html($redis_info['clients']); ?>
                    </p>
                    <p>
                        <strong><?php _e('Uptime:', 'holler-cache-control'); ?></strong>
                        <?php echo esc_html($redis_info['uptime']); ?>
                    </p>
                </div>
            <?php endif; ?>
            <p class="details">
                <?php 
                global $wp_object_cache;
                if (method_exists($wp_object_cache, 'info') && ($info = $wp_object_cache->info())) {
                    $hits = is_object($info) ? $info->hits : (isset($info['hits']) ? $info['hits'] : 0);
                    $misses = is_object($info) ? $info->misses : (isset($info['misses']) ? $info['misses'] : 0);
                    $ratio = $hits + $misses > 0 ? number_format(($hits / ($hits + $misses)) * 100, 1) : 0;
                    
                    echo sprintf(
                        __('Hits: %d, Misses: %d, Hit Ratio: %s%%', 'holler-cache-control'),
                        $hits,
                        $misses,
                        $ratio
                    );
                }
                ?>
            </p>
            <div class="cache-actions">
                <button type="button" class="button button-primary purge-cache" data-type="redis">
                    <?php _e('Purge Object Cache', 'holler-cache-control'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>
