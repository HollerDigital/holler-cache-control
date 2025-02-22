<?php
if (!defined('ABSPATH')) {
    exit;
}

use Holler\CacheControl\Admin\Cache\Cloudflare;

?>
<!-- Cloudflare Cache -->
<div class="cache-status-card <?php echo $cloudflare_status['status'] === 'active' ? 'active' : 'inactive'; ?>">
    <div class="cache-status-header">
        <h3><?php _e('Cloudflare Cache', 'holler-cache-control'); ?></h3>
        <span class="status-indicator"></span>
    </div>
    <div class="cache-status-content">
        <p><?php echo esc_html($cloudflare_status['message']); ?></p>
        <?php if ($cloudflare_status['status'] === 'active'): ?>
            <?php if (!empty($cloudflare_settings)): ?>
                <div class="cache-details">
                    <?php foreach ($cloudflare_settings as $setting => $status):
                        if ($setting !== 'optimization_results'): ?>
                            <p>
                                <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $setting))); ?>:</strong>
                                <?php echo esc_html($status['message']); ?>
                                <?php if (isset($status['recommended']) && $status['value'] !== $status['recommended']): ?>
                                    <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                <?php endif; ?>
                            </p>
                        <?php endif;
                    endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="cache-actions">
                <button type="button" class="button button-primary purge-cache" data-type="cloudflare">
                    <?php _e('Purge Cloudflare Cache', 'holler-cache-control'); ?>
                </button>
                <form method="post" style="display: inline-block; margin-left: 10px;">
                    <?php wp_nonce_field('holler_cache_control_check_settings'); ?>
                    <input type="hidden" name="check_cloudflare_settings" value="1">
                    <button type="submit" class="button">
                        <?php _e('Check Settings', 'holler-cache-control'); ?>
                    </button>
                </form>
            </div>

            <?php if (isset($_POST['check_cloudflare_settings']) && check_admin_referer('holler_cache_control_check_settings')): ?>
                <?php 
                $settings_check = Cloudflare::check_and_configure_settings();
                if ($settings_check['success']): 
                    foreach ($settings_check['settings'] as $setting => $status):
                        if ($setting === 'optimization_results'): ?>
                            <div class="notice notice-info">
                                <p><strong><?php _e('Applied Recommended Settings:', 'holler-cache-control'); ?></strong></p>
                                <ul>
                                    <?php foreach ($status as $opt_setting => $result): ?>
                                        <li>
                                            <?php echo esc_html($result['message']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="notice <?php echo isset($status['recommended']) && $status['value'] !== $status['recommended'] ? 'notice-warning' : 'notice-success'; ?>">
                                <p>
                                    <?php if ($setting === 'development_mode'): ?>
                                        <strong><?php _e('Development Mode:', 'holler-cache-control'); ?></strong>
                                    <?php else: ?>
                                        <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $setting))); ?>:</strong>
                                    <?php endif; ?>
                                    <?php echo esc_html($status['message']); ?>
                                    <?php if (isset($status['recommended']) && $status['value'] !== $status['recommended']): ?>
                                        <?php if ($setting === 'browser_cache'): ?>
                                            <form method="post" style="display: inline-block; margin-left: 10px;">
                                                <?php wp_nonce_field('holler_cache_control_update_browser_cache'); ?>
                                                <input type="hidden" name="update_browser_cache" value="1">
                                                <button type="submit" class="button button-small">
                                                    <?php _e('Update to Recommended', 'holler-cache-control'); ?>
                                                </button>
                                            </form>
                                        <?php elseif ($setting === 'always_online'): ?>
                                            <form method="post" style="display: inline-block; margin-left: 10px;">
                                                <?php wp_nonce_field('holler_cache_control_update_always_online'); ?>
                                                <input type="hidden" name="update_always_online" value="1">
                                                <button type="submit" class="button button-small">
                                                    <?php _e('Enable Always Online', 'holler-cache-control'); ?>
                                                </button>
                                            </form>
                                        <?php elseif ($setting === 'rocket_loader'): ?>
                                            <form method="post" style="display: inline-block; margin-left: 10px;">
                                                <?php wp_nonce_field('holler_cache_control_update_rocket_loader'); ?>
                                                <input type="hidden" name="update_rocket_loader" value="1">
                                                <button type="submit" class="button button-small">
                                                    <?php _e('Enable Rocket Loader', 'holler-cache-control'); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif;
                    endforeach;
                else: ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html($settings_check['message']); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
