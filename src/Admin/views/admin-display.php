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

// Get APO information if Cloudflare is active
$apo_info = null;
if ($cloudflare_status['status'] === 'active') {
    $apo_info = Cloudflare::get_apo_info();
}

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
     <!-- Global Cache Actions -->
    <div class="cache-actions-global">
        <button type="button" class="button button-primary button-hero purge-cache" data-type="all">
            <?php _e('Purge All Caches', 'holler-cache-control'); ?>
        </button>
    </div>
    <div class="cache-status-grid">
       <?php include_once 'partials/cache-cards.php'; ?>
    </div>

    <!-- Plugin & Feature Visibility Card -->
    <div class="cache-status-card settings-card full-width">
        <div class="cache-status-header">
            <h3><?php _e('Plugin & Feature Visibility', 'holler-cache-control'); ?></h3>
        </div>
        <div class="cache-status-content">
            <form method="post" action="options.php">
                <?php
                settings_fields('holler_cache_control_settings');
                do_settings_sections('holler-cache-control');
                submit_button();
                ?>
            </form>
        </div>
    </div>
</div>

<style>
.cache-status-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin: 20px 0;
}

@media screen and (max-width: 782px) {
    .cache-status-grid {
        grid-template-columns: 1fr;
    }
}

.cache-status-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    margin: 0;
    padding: 0;
}

.cache-status-header {
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
}

.cache-status-header h3 {
    margin: 0;
    font-size: 14px;
    line-height: 1.4;
}

.cache-status-content {
    padding: 12px;
}

.cache-details {
    margin-top: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 4px;
}

.cache-details p {
    margin: 0 0 8px;
}

.cache-details .details {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e2e4e7;
}

.cache-actions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e2e4e7;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.active .status-indicator {
    background: #46b450;
}

.inactive .status-indicator {
    background: #dc3232;
}

.cache-actions-global {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    text-align: center;
}

.full-width {
    margin: 20px 0;
    width: 100%;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.purge-cache').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var type = button.data('type');
        
        button.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'holler_purge_cache',
                type: type,
                nonce: '<?php echo wp_create_nonce('holler_cache_control'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Cache purged successfully!');
                } else {
                    alert('Failed to purge cache: ' + response.data.message);
                }
            },
            error: function() {
                alert('Failed to purge cache. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});
</script>
