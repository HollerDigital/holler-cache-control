<?php
/**
 * Admin area display
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/views
 */

use Holler\CacheControl\Admin\Cache\Nginx;
use Holler\CacheControl\Admin\Cache\Redis;
use Holler\CacheControl\Admin\Cache\Cloudflare;
use Holler\CacheControl\Admin\Cache\CloudflareAPO;
use Holler\CacheControl\Admin\Tools;

// Get cache statuses from Tools class
$cache_status = Tools::get_cache_systems_status();

// Extract individual cache statuses
$nginx_status = $cache_status['nginx'] ?? array('status' => 'not_configured');
$page_cache_status = $cache_status['nginx'] ?? array('status' => 'not_configured');
$redis_status = $cache_status['redis'] ?? array('status' => 'not_configured');
$cloudflare_status = $cache_status['cloudflare'] ?? array('status' => 'not_configured');
$cloudflare_apo_status = $cache_status['cloudflare-apo'] ?? array('status' => 'not_configured');

// Get additional Cloudflare settings
$cloudflare_settings = array();
if ($cloudflare_status['status'] === 'active') {
    $settings_check = Cloudflare::check_and_configure_settings();
    if ($settings_check['success']) {
        $cloudflare_settings = $settings_check['settings'];
    }
}

// Check if using Cloudflare constants
$using_constants = defined('CLOUDFLARE_API_KEY') && defined('CLOUDFLARE_EMAIL');

?>

<div class="wrap">
    <h1>Holler Cache Control</h1>
    <?php settings_errors('cache_control'); ?>

    <?php if (isset($_POST['check_cloudflare_settings']) && check_admin_referer('holler_cache_control_check_settings')): ?>
        <?php 
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

    <?php
    // Handle browser cache TTL update
    if (isset($_POST['update_browser_cache']) && check_admin_referer('holler_cache_control_update_browser_cache')) {
        $result = Cloudflare::update_browser_cache_ttl();
        if ($result['success']): ?>
            <div class="notice notice-success">
                <p><?php echo esc_html($result['message']); ?></p>
            </div>
        <?php else: ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($result['message']); ?></p>
            </div>
        <?php endif;
    }

    // Handle Always Online update
    if (isset($_POST['update_always_online']) && check_admin_referer('holler_cache_control_update_always_online')) {
        $result = Cloudflare::update_always_online();
        if ($result['success']): ?>
            <div class="notice notice-success">
                <p><?php echo esc_html($result['message']); ?></p>
            </div>
        <?php else: ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($result['message']); ?></p>
            </div>
        <?php endif;
    }

    // Handle Rocket Loader update
    if (isset($_POST['update_rocket_loader']) && check_admin_referer('holler_cache_control_update_rocket_loader')) {
        $result = Cloudflare::update_rocket_loader();
        if ($result['success']): ?>
            <div class="notice notice-success">
                <p><?php echo esc_html($result['message']); ?></p>
            </div>
        <?php else: ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($result['message']); ?></p>
            </div>
        <?php endif;
    }
    ?>

    <div class="cache-status-grid">
        <!-- Page Cache -->
        <div class="cache-status-card <?php echo $page_cache_status['status'] === 'active' ? 'active' : 'inactive'; ?>">
            <div class="cache-status-header">
                <h3><?php _e('Page Cache', 'holler-cache-control'); ?></h3>
                <span class="status-indicator"></span>
            </div>
            <div class="cache-status-content">
                <p><?php echo esc_html($page_cache_status['message']); ?></p>
                <?php if ($page_cache_status['status'] === 'active'): ?>
                    <div class="cache-actions">
                        <button type="button" class="button button-primary purge-cache" data-type="nginx">
                            <?php _e('Purge Page Cache', 'holler-cache-control'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Redis Object Cache -->
        <div class="cache-status-card <?php echo $redis_status['status'] === 'active' ? 'active' : 'inactive'; ?>">
            <h2><?php _e('Redis Object Cache', 'holler-cache-control'); ?></h2>
            <div class="holler-cache-control-box-content">
                <p class="status-message">
                    <?php echo esc_html($redis_status['message']); ?>
                </p>
                <?php if ($redis_status['status'] === 'active'): ?>
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
                    <button class="button purge-cache" data-type="redis_object">
                        <?php _e('Purge Object Cache', 'holler-cache-control'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cloudflare Cache -->
        <div class="cache-status-card <?php echo $cloudflare_status['status'] === 'active' ? 'active' : 'inactive'; ?>">
            <h2><?php _e('Cloudflare Cache', 'holler-cache-control'); ?></h2>
            <div class="holler-cache-control-box-content">
                <p class="status-message">
                    <?php echo esc_html($cloudflare_status['message']); ?>
                </p>
                <?php if ($cloudflare_status['status'] === 'active'): ?>
                    <?php if (!empty($cloudflare_settings)): ?>
                        <div class="details">
                            <?php if (isset($cloudflare_settings['development_mode'])): ?>
                                <p>
                                    <?php echo sprintf(
                                        __('Development Mode: %s', 'holler-cache-control'),
                                        $cloudflare_settings['development_mode']['enabled'] ? 'On' : 'Off'
                                    ); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (isset($cloudflare_settings['cache_level'])): ?>
                                <p>
                                    <?php echo sprintf(
                                        __('Cache Level: %s', 'holler-cache-control'),
                                        esc_html($cloudflare_settings['cache_level']['value'])
                                    ); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (isset($cloudflare_settings['browser_cache'])): ?>
                                <p>
                                    <?php echo sprintf(
                                        __('Browser Cache TTL: %s seconds', 'holler-cache-control'),
                                        esc_html($cloudflare_settings['browser_cache']['value'])
                                    ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <button class="button purge-cache" data-type="cloudflare">
                        <?php _e('Purge Cloudflare Cache', 'holler-cache-control'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cloudflare Settings Check -->
        <?php if ($cloudflare_status['status'] === 'active'): ?>
        <div class="cache-status-card active">
            <h2><?php _e('Cloudflare Settings', 'holler-cache-control'); ?></h2>
            <div class="holler-cache-control-box-content">
                <p class="status-message">
                    <?php _e('Check and configure Cloudflare settings for optimal performance.', 'holler-cache-control'); ?>
                </p>
                <form method="post">
                    <?php wp_nonce_field('holler_cache_control_check_settings'); ?>
                    <input type="submit" name="check_cloudflare_settings" class="button button-primary" value="<?php _e('Check Settings', 'holler-cache-control'); ?>">
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cloudflare APO -->
        <div class="cache-status-card <?php echo $cloudflare_apo_status['status'] === 'active' ? 'active' : 'inactive'; ?>">
            <h2><?php _e('Cloudflare APO', 'holler-cache-control'); ?></h2>
            <div class="holler-cache-control-box-content">
                <p class="status-message">
                    <?php echo esc_html($cloudflare_apo_status['message']); ?>
                </p>
                <?php if ($cloudflare_apo_status['status'] === 'active'): ?>
                    <p class="details">
                        <?php _e('Automatic Platform Optimization (APO) is enabled and actively caching your pages.', 'holler-cache-control'); ?>
                    </p>
                    <button class="button purge-cache" data-type="cloudflare_apo">
                        <?php _e('Purge APO Cache', 'holler-cache-control'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Global Cache Actions -->
    <div class="cache-actions">
        <button class="button button-primary button-hero purge-cache" data-type="all">
            <?php _e('Purge All Caches', 'holler-cache-control'); ?>
        </button>
    </div>

    <hr>

    <!-- Settings Form -->
    <form method="post" action="options.php">
        <?php 
        settings_fields('holler_cache_control_settings');
        do_settings_sections('holler_cache_control_settings');
        ?>
        
        <h2><?php _e('Plugin Settings', 'holler-cache-control'); ?></h2>
        
        <table class="form-table">
            <!-- Admin Bar Settings -->
            <tr>
                <th scope="row"><?php _e('Admin Bar Settings', 'holler-cache-control'); ?></th>
                <td>
                    <fieldset>
                        <label for="hide_nginx_purge_button">
                            <input type="checkbox" name="hide_nginx_purge_button" id="hide_nginx_purge_button" value="1" <?php checked(get_option('hide_nginx_purge_button'), '1'); ?>>
                            <?php _e('Hide Page Cache Purge Button', 'holler-cache-control'); ?>
                        </label>
                        <br>
                        <label for="hide_redis_purge_button">
                            <input type="checkbox" name="hide_redis_purge_button" id="hide_redis_purge_button" value="1" <?php checked(get_option('hide_redis_purge_button'), '1'); ?>>
                            <?php _e('Hide Object Cache Purge Button', 'holler-cache-control'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <!-- Cloudflare Settings -->
            <?php if (!$using_constants): ?>
            <tr>
                <th scope="row"><?php _e('Cloudflare Settings', 'holler-cache-control'); ?></th>
                <td>
                    <fieldset>
                        <p>
                            <label for="cloudflare_email">
                                <span class="label-text"><?php _e('Email', 'holler-cache-control'); ?></span>
                                <input type="email" name="cloudflare_email" id="cloudflare_email" value="<?php echo esc_attr(get_option('cloudflare_email')); ?>" class="regular-text">
                            </label>
                        </p>
                        <p>
                            <label for="cloudflare_api_key">
                                <span class="label-text"><?php _e('API Key', 'holler-cache-control'); ?></span>
                                <input type="password" name="cloudflare_api_key" id="cloudflare_api_key" value="<?php echo esc_attr(get_option('cloudflare_api_key')); ?>" class="regular-text">
                            </label>
                        </p>
                        <p>
                            <label for="cloudflare_zone_id">
                                <span class="label-text"><?php _e('Zone ID', 'holler-cache-control'); ?></span>
                                <input type="text" name="cloudflare_zone_id" id="cloudflare_zone_id" value="<?php echo esc_attr(get_option('cloudflare_zone_id')); ?>" class="regular-text">
                            </label>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <!-- Cloudflare Optimization Settings -->
            <tr>
                <th scope="row"><?php _e('Cloudflare Optimization', 'holler-cache-control'); ?></th>
                <td>
                    <fieldset>
                        <label for="cloudflare_auto_optimize">
                            <input type="checkbox" name="cloudflare_auto_optimize" id="cloudflare_auto_optimize" value="1" <?php checked(get_option('cloudflare_auto_optimize'), '1'); ?>>
                            <?php _e('Automatically apply recommended settings when checking Cloudflare status', 'holler-cache-control'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, checking Cloudflare settings will automatically apply our recommended optimization settings for the best performance.', 'holler-cache-control'); ?>
                        </p>
                        <p class="description">
                            <?php _e('Recommended settings include:', 'holler-cache-control'); ?>
                            <ul>
                                <li><?php _e('Cache Level: Aggressive', 'holler-cache-control'); ?></li>
                                <li><?php _e('Browser Cache TTL: 4 hours', 'holler-cache-control'); ?></li>
                                <li><?php _e('Always Online: Enabled', 'holler-cache-control'); ?></li>
                                <li><?php _e('Auto Minify: HTML, CSS, JS', 'holler-cache-control'); ?></li>
                                <li><?php _e('Rocket Loader: Enabled', 'holler-cache-control'); ?></li>
                                <li><?php _e('Brotli Compression: Enabled', 'holler-cache-control'); ?></li>
                                <li><?php _e('Early Hints: Enabled', 'holler-cache-control'); ?></li>
                            </ul>
                        </p>
                    </fieldset>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
