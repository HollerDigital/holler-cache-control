<?php
/**
 * Admin display template for the cache control page
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/Admin\views
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!current_user_can('manage_options')) {
    return;
}

use HollerCacheControl\Cache\Cloudflare;
use HollerCacheControl\Cache\CloudflareAPO;
use HollerCacheControl\Admin\Tools;

// Get cache statuses from Tools class
$cache_status = Tools::get_cache_systems_status();

// Set default values for cache status variables
$nginx_status = $cache_status['nginx'] ?? array('active' => false, 'details' => __('Not Available', 'holler-cache-control'), 'type' => '');
$redis_status = $cache_status['redis'] ?? array('active' => false, 'details' => __('Not Available', 'holler-cache-control'), 'type' => '');
$cloudflare_status = $cache_status['cloudflare'] ?? array(
    'active' => false,
    'cache_level' => __('Not Available', 'holler-cache-control'),
    'dev_mode' => false,
    'always_online' => false,
    'ssl' => __('Not Available', 'holler-cache-control'),
    'type' => ''
);
$cloudflare_apo_status = $cache_status['cloudflare-apo'] ?? array(
    'active' => false,
    'cache_by_device' => false,
    'cache_by_location' => false,
    'no_html_minify' => false,
    'type' => ''
);

// Get admin bar settings
$hide_nginx = get_option('hide_nginx_purge_button', '0') === '1';
$hide_redis = get_option('hide_redis_purge_button', '0') === '1';

// Check if Cloudflare is using constants
$using_constants = (
    defined('CLOUDFLARE_EMAIL') || 
    defined('CLOUDFLARE_API_KEY') || 
    defined('CLOUDFLARE_ZONE_ID')
);
?>

<div class="wrap">
    <h1>Holler Cache Control</h1>
    <?php settings_errors('cache_control'); ?>

    <?php if (isset($_POST['check_cloudflare_settings']) && check_admin_referer('holler_cache_control_check_settings')): ?>
        <?php 
        $settings_check = \HollerCacheControl\Cache\Cloudflare::check_and_configure_settings();
        if ($settings_check['success']): 
            foreach ($settings_check['settings'] as $setting => $status):
                if ($setting === 'page_rules'): ?>
                    <div class="notice notice-warning">
                        <p><strong><?php _e('Page Rules Check:', 'holler-cache-control'); ?></strong></p>
                        <p><?php echo esc_html($status['message']); ?></p>
                        <?php if (!empty($status['rules'])): ?>
                            <ul>
                            <?php foreach ($status['rules'] as $rule): ?>
                                <li>URL: <?php echo esc_html($rule['url']); ?> - 
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $rule['action']))); ?>: 
                                    <?php echo esc_html($rule['value']); ?></li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="notice notice-<?php echo $status['status'] === 'optimal' ? 'success' : ($status['status'] === 'updated' ? 'info' : 'warning'); ?>">
                        <p><?php echo esc_html($status['message']); ?></p>
                    </div>
                <?php endif;
            endforeach;
        else: ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($settings_check['message']); ?></p>
            </div>
        <?php endif;
    endif; ?>

    <div class="card">
        <h3><?php _e('Cloudflare Settings Check', 'holler-cache-control'); ?></h3>
        <p><?php _e('Check and optimize your Cloudflare cache settings.', 'holler-cache-control'); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field('holler_cache_control_check_settings'); ?>
            <p>
                <input type="submit" name="check_cloudflare_settings" class="button button-primary" 
                    value="<?php _e('Check Settings', 'holler-cache-control'); ?>" />
            </p>
        </form>
    </div>

    <!-- Cache Status Grid -->
    <div class="cache-status-grid">
        <!-- Nginx FastCGI Cache -->
        <div class="cache-status-card <?php echo $nginx_status['active'] ? 'active' : 'inactive'; ?>">
            <h2><?php _e('Nginx Page Cache', 'holler-cache-control'); ?></h2>
            <div class="holler-cache-control-box-content">
                <?php
                $nginx_class = $nginx_status['active'] ? 'active' : 'inactive';
                $nginx_text = $nginx_status['active'] ? __('Running', 'holler-cache-control') : __('Not Active', 'holler-cache-control');
                $nginx_details = $nginx_status['details'] ?? '';
                $nginx_type = $nginx_status['type'] ?? '';
                ?>
                <p>
                    <strong><?php _e('Status', 'holler-cache-control'); ?>:</strong>
                    <span class="<?php echo esc_attr($nginx_class); ?>"><?php echo esc_html($nginx_text); ?></span>
                </p>
                <?php if (!empty($nginx_details)): ?>
                    <p><strong><?php _e('Details', 'holler-cache-control'); ?>:</strong> <?php echo esc_html($nginx_details); ?></p>
                <?php endif; ?>
                <?php if ($nginx_status['active']): ?>
                    <?php if ($nginx_type): ?>
                        <p><strong><?php _e('Type', 'holler-cache-control'); ?>:</strong> <?php echo esc_html($nginx_type); ?></p>
                    <?php endif; ?>
                    <button class="button purge-cache" data-cache-type="nginx">
                        <?php _e('Purge Nginx Cache', 'holler-cache-control'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Redis Object Cache -->
        <div class="cache-status-card <?php echo $redis_status['active'] ? 'active' : 'inactive'; ?>">
            <h2><?php _e('Redis Object Cache', 'holler-cache-control'); ?></h2>
            <div class="holler-cache-control-box-content">
                <?php
                $redis_class = $redis_status['active'] ? 'active' : 'inactive';
                $redis_text = $redis_status['active'] ? __('Running', 'holler-cache-control') : __('Not Active', 'holler-cache-control');
                $redis_details = $redis_status['details'] ?? '';
                $redis_type = $redis_status['type'] ?? '';
                ?>
                <p>
                    <strong><?php _e('Status', 'holler-cache-control'); ?>:</strong>
                    <span class="<?php echo esc_attr($redis_class); ?>"><?php echo esc_html($redis_text); ?></span>
                </p>
                <?php if (!empty($redis_details)): ?>
                    <p><strong><?php _e('Details', 'holler-cache-control'); ?>:</strong> <?php echo esc_html($redis_details); ?></p>
                <?php endif; ?>
                <?php if ($redis_status['active']): ?>
                    <?php if ($redis_type): ?>
                        <p><strong><?php _e('Type', 'holler-cache-control'); ?>:</strong> <?php echo esc_html($redis_type); ?></p>
                    <?php endif; ?>
                    <button class="button purge-cache" data-cache-type="redis">
                        <?php _e('Purge Redis Cache', 'holler-cache-control'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cloudflare Cache -->
        <div class="cache-status-card <?php echo $cloudflare_status['active'] ? 'active' : 'inactive'; ?>">
            <h2><?php _e('Cloudflare Cache', 'holler-cache-control'); ?></h2>
            <?php if ($using_constants): ?>
                <div class="notice notice-info inline">
                    <p>
                        <strong><?php _e('Using Cloudflare API credentials from wp-config.php constants.', 'holler-cache-control'); ?></strong>
                    </p>
                </div>
            <?php endif; ?>
            <div class="holler-cache-control-box-content">
                <?php
                $cloudflare_class = $cloudflare_status['active'] ? 'active' : 'inactive';
                $cloudflare_text = $cloudflare_status['active'] ? __('Running', 'holler-cache-control') : __('Not Active', 'holler-cache-control');
                $cloudflare_details = $cloudflare_status['details'] ?? '';
                $cloudflare_type = $cloudflare_status['type'] ?? '';
                ?>
                <p>
                    <strong><?php _e('Status', 'holler-cache-control'); ?>:</strong>
                    <span class="<?php echo esc_attr($cloudflare_class); ?>"><?php echo esc_html($cloudflare_text); ?></span>
                </p>
                <?php if (!empty($cloudflare_details)): ?>
                    <p><strong><?php _e('Details', 'holler-cache-control'); ?>:</strong> <?php echo esc_html($cloudflare_details); ?></p>
                <?php endif; ?>
                <?php if ($cloudflare_status['active']): ?>
                    <?php if ($cloudflare_type): ?>
                        <p><strong><?php _e('Type', 'holler-cache-control'); ?>:</strong> <?php echo esc_html($cloudflare_type); ?></p>
                    <?php endif; ?>
                    <p class="status">
                        <span class="status-label"><?php _e('Cache Level:', 'holler-cache-control'); ?></span>
                        <?php echo esc_html($cloudflare_status['cache_level']); ?>
                    </p>
                    <p class="status">
                        <span class="status-label"><?php _e('Development Mode:', 'holler-cache-control'); ?></span>
                        <?php echo esc_html($cloudflare_status['dev_mode']); ?>
                    </p>
                    <p class="status">
                        <span class="status-label"><?php _e('Always Online:', 'holler-cache-control'); ?></span>
                        <?php echo isset($cloudflare_status['always_online']) && $cloudflare_status['always_online'] ? __('On', 'holler-cache-control') : __('Off', 'holler-cache-control'); ?>
                    </p>
                    <p class="status">
                        <span class="status-label"><?php _e('SSL:', 'holler-cache-control'); ?></span>
                        <?php echo isset($cloudflare_status['ssl']) ? esc_html($cloudflare_status['ssl']) : __('Not Available', 'holler-cache-control'); ?>
                    </p>
                    <button class="button purge-cache" data-cache-type="cloudflare">
                        <?php _e('Purge Cloudflare Cache', 'holler-cache-control'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cloudflare APO -->
        <div class="cache-status-card <?php echo $cloudflare_apo_status['active'] ? 'active' : 'inactive'; ?>">
            <h2><?php _e('Cloudflare APO', 'holler-cache-control'); ?></h2>
            <div class="holler-cache-control-box-content">
                <?php
                $cloudflare_apo_class = $cloudflare_apo_status['active'] ? 'active' : 'inactive';
                $cloudflare_apo_text = $cloudflare_apo_status['active'] ? __('Running', 'holler-cache-control') : __('Not Active', 'holler-cache-control');
                $cloudflare_apo_details = $cloudflare_apo_status['details'] ?? '';
                $cloudflare_apo_type = $cloudflare_apo_status['type'] ?? '';
                ?>
                <p>
                    <strong><?php _e('Status', 'holler-cache-control'); ?>:</strong>
                    <span class="<?php echo esc_attr($cloudflare_apo_class); ?>"><?php echo esc_html($cloudflare_apo_text); ?></span>
                </p>
                <?php if (!empty($cloudflare_apo_details)): ?>
                    <p><strong><?php _e('Details', 'holler-cache-control'); ?>:</strong> <?php echo esc_html($cloudflare_apo_details); ?></p>
                <?php endif; ?>
                <?php if ($cloudflare_apo_status['active']): ?>
                    <?php if ($cloudflare_apo_type): ?>
                        <p><strong><?php _e('Type', 'holler-cache-control'); ?>:</strong> <?php echo esc_html($cloudflare_apo_type); ?></p>
                    <?php endif; ?>
                    <p class="status">
                        <span class="status-label"><?php _e('Cache by Device:', 'holler-cache-control'); ?></span>
                        <?php echo isset($cloudflare_apo_status['cache_by_device']) && $cloudflare_apo_status['cache_by_device'] ? __('Yes', 'holler-cache-control') : __('No', 'holler-cache-control'); ?>
                    </p>
                    <p class="status">
                        <span class="status-label"><?php _e('Cache by Location:', 'holler-cache-control'); ?></span>
                        <?php echo isset($cloudflare_apo_status['cache_by_location']) && $cloudflare_apo_status['cache_by_location'] ? __('Yes', 'holler-cache-control') : __('No', 'holler-cache-control'); ?>
                    </p>
                    <p class="status">
                        <span class="status-label"><?php _e('No HTML Minify:', 'holler-cache-control'); ?></span>
                        <?php echo isset($cloudflare_apo_status['no_html_minify']) && $cloudflare_apo_status['no_html_minify'] ? __('Yes', 'holler-cache-control') : __('No', 'holler-cache-control'); ?>
                    </p>
                    <button class="button purge-cache" data-cache-type="cloudflare-apo">
                        <?php _e('Purge APO Cache', 'holler-cache-control'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Global Cache Actions -->
    <div class="cache-actions">
        <button class="button button-primary button-hero purge-cache" data-cache-type="all">
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
                            <?php _e('Hide Nginx Cache Purge Button', 'holler-cache-control'); ?>
                        </label>
                        <br>
                        <label for="hide_redis_purge_button">
                            <input type="checkbox" name="hide_redis_purge_button" id="hide_redis_purge_button" value="1" <?php checked(get_option('hide_redis_purge_button'), '1'); ?>>
                            <?php _e('Hide Redis Cache Purge Button', 'holler-cache-control'); ?>
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
                            <label>
                                <span class="label-text"><?php _e('Email Address', 'holler-cache-control'); ?></span>
                                <input type="email" 
                                       name="cloudflare_email" 
                                       value="<?php echo esc_attr(get_option('cloudflare_email')); ?>" 
                                       class="regular-text" />
                            </label>
                        </p>
                        <p>
                            <label>
                                <span class="label-text"><?php _e('API Key', 'holler-cache-control'); ?></span>
                                <input type="password" 
                                       name="cloudflare_api_key" 
                                       value="<?php echo esc_attr(get_option('cloudflare_api_key')); ?>" 
                                       class="regular-text" />
                            </label>
                        </p>
                        <p>
                            <label>
                                <span class="label-text"><?php _e('Zone ID', 'holler-cache-control'); ?></span>
                                <input type="text" 
                                       name="cloudflare_zone_id" 
                                       value="<?php echo esc_attr(get_option('cloudflare_zone_id')); ?>" 
                                       class="regular-text" />
                            </label>
                        </p>
                    </fieldset>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
