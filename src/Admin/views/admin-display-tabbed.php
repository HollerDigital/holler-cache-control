<?php
/**
 * Unified Tabbed Admin Display
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/views
 */

use Holler\CacheControl\Admin\Cache\Nginx;
use Holler\CacheControl\Admin\Cache\Redis;
use Holler\CacheControl\Admin\Cache\Cloudflare;
use Holler\CacheControl\Admin\Cache\CloudflareAPO;
use Holler\CacheControl\Admin\Tools;
use Holler\CacheControl\Core\CachePathDetector;

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

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';

// Define tabs
$tabs = array(
    'dashboard' => array(
        'title' => __('Dashboard', 'holler-cache-control'),
        'icon' => 'dashicons-dashboard'
    ),
    'settings' => array(
        'title' => __('Settings', 'holler-cache-control'),
        'icon' => 'dashicons-admin-settings'
    ),
    'cloudflare' => array(
        'title' => __('Cloudflare', 'holler-cache-control'),
        'icon' => 'dashicons-cloud'
    ),
    'diagnostics' => array(
        'title' => __('Diagnostics', 'holler-cache-control'),
        'icon' => 'dashicons-admin-tools'
    ),
    'advanced' => array(
        'title' => __('Advanced', 'holler-cache-control'),
        'icon' => 'dashicons-admin-generic'
    )
);

?>

<div class="wrap holler-cache-control-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <?php foreach ($tabs as $tab_key => $tab_info): ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $tab_key, admin_url('options-general.php?page=settings_page_holler-cache-control'))); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons <?php echo esc_attr($tab_info['icon']); ?>"></span>
                <?php echo esc_html($tab_info['title']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Tab Content -->
    <div class="tab-content">
        <?php
        switch ($current_tab) {
            case 'dashboard':
                include_once 'tabs/dashboard.php';
                break;
            case 'settings':
                include_once 'tabs/settings.php';
                break;
            case 'cloudflare':
                include_once 'tabs/cloudflare.php';
                break;
            case 'diagnostics':
                include_once 'tabs/diagnostics.php';
                break;
            case 'advanced':
                include_once 'tabs/advanced.php';
                break;
            default:
                include_once 'tabs/dashboard.php';
                break;
        }
        ?>
    </div>
</div>

<style>
/* Tab Navigation Styles */
.holler-cache-control-admin .nav-tab-wrapper {
    margin-bottom: 20px;
    border-bottom: 1px solid #ccd0d4;
}

.holler-cache-control-admin .nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    text-decoration: none;
    border: 1px solid transparent;
    border-bottom: none;
    background: #f1f1f1;
    color: #555;
    font-weight: 600;
    transition: all 0.2s ease;
}

.holler-cache-control-admin .nav-tab:hover {
    background: #e8e8e8;
    color: #333;
}

.holler-cache-control-admin .nav-tab-active {
    background: #fff;
    color: #333;
    border-color: #ccd0d4;
    border-bottom: 1px solid #fff;
    margin-bottom: -1px;
}

.holler-cache-control-admin .nav-tab .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Tab Content Styles */
.tab-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: none;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
}

/* Grid Layout for Cards */
.cache-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

@media screen and (max-width: 782px) {
    .cache-status-grid {
        grid-template-columns: 1fr;
    }
    
    .holler-cache-control-admin .nav-tab {
        padding: 8px 12px;
        font-size: 14px;
    }
    
    .holler-cache-control-admin .nav-tab .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }
}

/* Card Styles */
.cache-status-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    margin: 0;
    padding: 0;
    border-radius: 4px;
}

.cache-status-header {
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8f9fa;
    border-radius: 4px 4px 0 0;
}

.cache-status-header h3 {
    margin: 0;
    font-size: 14px;
    line-height: 1.4;
    font-weight: 600;
}

.cache-status-content {
    padding: 16px;
}

.cache-details {
    margin-top: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #e2e4e7;
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
    margin-right: 8px;
}

.active .status-indicator {
    background: #46b450;
}

.inactive .status-indicator {
    background: #dc3232;
}

.not_configured .status-indicator {
    background: #ffb900;
}

/* Global Actions */
.cache-actions-global {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    text-align: center;
    border-radius: 4px;
}

.full-width {
    margin: 20px 0;
    width: 100%;
}

/* Form Styles */
.form-table th {
    width: 200px;
    font-weight: 600;
}

.form-table td {
    vertical-align: top;
    padding-top: 15px;
}

.description {
    margin-top: 5px;
    color: #646970;
    font-style: italic;
}

/* Notice Styles */
.holler-notice {
    padding: 12px 16px;
    margin: 16px 0;
    border-left: 4px solid #00a32a;
    background: #f0f6fc;
    border-radius: 0 4px 4px 0;
}

.holler-notice.notice-warning {
    border-left-color: #ffb900;
    background: #fffbf0;
}

.holler-notice.notice-error {
    border-left-color: #dc3232;
    background: #fef7f7;
}

.holler-notice.notice-info {
    border-left-color: #72aee6;
    background: #f0f6fc;
}

/* Button Styles */
.button-hero {
    padding: 12px 24px !important;
    font-size: 16px !important;
    height: auto !important;
    line-height: 1.4 !important;
}

/* Loading States */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #555;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Plugin Recommendations Styles */
.optimization-score {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.score-label {
    color: #666;
    font-weight: 500;
}

.score-value {
    padding: 4px 12px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 13px;
}

.score-value.good {
    background: #d1e7dd;
    color: #0f5132;
}

.score-value.warning {
    background: #fff3cd;
    color: #664d03;
}

.score-value.poor {
    background: #f8d7da;
    color: #721c24;
}

.priority-recommendations {
    margin: 24px 0;
}

.recommendations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.recommendation-card {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 16px;
    background: #fff;
}

.recommendation-card.success {
    border-left: 4px solid #00a32a;
    background: #f6ffed;
}

.recommendation-card.warning {
    border-left: 4px solid #ffb900;
    background: #fffbf0;
}

.recommendation-card.info {
    border-left: 4px solid #72aee6;
    background: #f0f6fc;
}

.rec-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.rec-plugin {
    font-size: 12px;
    color: #666;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rec-impact {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.impact-high {
    background: #f8d7da;
    color: #721c24;
}

.impact-medium {
    background: #fff3cd;
    color: #664d03;
}

.impact-low {
    background: #d1ecf1;
    color: #0c5460;
}

.recommendation-card h5 {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.rec-reason {
    font-size: 13px;
    color: #666;
    margin: 8px 0;
    line-height: 1.4;
}

.rec-action {
    font-size: 12px;
    color: #333;
    background: rgba(0, 0, 0, 0.05);
    padding: 8px;
    border-radius: 4px;
    margin-top: 8px;
}

.plugin-analysis {
    margin-top: 24px;
}

.plugin-analysis-card {
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 20px;
    background: #fff;
}

.plugin-header {
    padding: 16px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
}

.plugin-header h5 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.plugin-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
}

.plugin-meta .version {
    color: #666;
}

.plugin-meta .score {
    padding: 4px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.plugin-recommendations {
    padding: 0;
}

.recommendation-item {
    padding: 16px;
    border-bottom: 1px solid #eee;
}

.recommendation-item:last-child {
    border-bottom: none;
}

.recommendation-item.success {
    background: #f6ffed;
    border-left: 3px solid #00a32a;
}

.recommendation-item.warning {
    background: #fffbf0;
    border-left: 3px solid #ffb900;
}

.recommendation-item.info {
    background: #f0f6fc;
    border-left: 3px solid #72aee6;
}

.rec-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 8px;
    font-size: 12px;
}

.rec-setting {
    font-weight: 600;
    color: #333;
}

.rec-current {
    color: #666;
}

.rec-recommended {
    color: #0073aa;
    font-weight: 500;
}

.no-recommendations {
    padding: 24px;
    text-align: center;
    color: #666;
}

.no-recommendations .dashicons {
    font-size: 24px;
    color: #00a32a;
    margin-bottom: 8px;
}

.other-plugins-analysis {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.other-plugins-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.other-plugin-item {
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
}

.other-plugin-item.success {
    border-left: 3px solid #00a32a;
    background: #f6ffed;
}

.other-plugin-item.warning {
    border-left: 3px solid #ffb900;
    background: #fffbf0;
}

.other-plugin-item h6 {
    margin: 0 0 6px 0;
    font-size: 13px;
    font-weight: 600;
    color: #333;
}

.other-plugin-item p {
    margin: 0 0 8px 0;
    font-size: 12px;
    color: #666;
}

.plugin-recommendation {
    font-size: 11px;
    color: #333;
    background: rgba(0, 0, 0, 0.05);
    padding: 6px;
    border-radius: 3px;
}

.no-plugins-detected {
    padding: 24px;
    text-align: center;
}

@media screen and (max-width: 782px) {
    .recommendations-grid {
        grid-template-columns: 1fr;
    }
    
    .other-plugins-grid {
        grid-template-columns: 1fr;
    }
    
    .rec-summary {
        flex-direction: column;
        gap: 4px;
    }
    
    .plugin-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle cache purge buttons
    $('.purge-cache').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var type = $button.data('type') || $button.data('cache-type') || 'all';
        
        // Confirm for purge all
        if (type === 'all' && !confirm('<?php echo esc_js(__('Are you sure you want to purge all caches?', 'holler-cache-control')); ?>')) {
            return;
        }
        
        // Show loading state
        var originalText = $button.text();
        $button.prop('disabled', true).text('<?php echo esc_js(__('Purging...', 'holler-cache-control')); ?>');
        
        // Map cache types to correct AJAX actions
        var actionMap = {
            'all': 'holler_purge_all',
            'nginx': 'holler_purge_nginx',
            'redis': 'holler_purge_redis',
            'cloudflare': 'holler_purge_cloudflare',
            'cloudflare_apo': 'holler_purge_cloudflare_apo'
        };
        
        var action = actionMap[type] || 'holler_purge_all';
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: action,
                type: type,
                nonce: '<?php echo wp_create_nonce('holler_cache_control_purge_all'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Show detailed message from server response
                    var message = response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Cache purged successfully!', 'holler-cache-control')); ?>';
                    showNotice(message, 'success');
                    // Refresh cache status after successful purge
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('<?php echo esc_js(__('Failed to purge cache: ', 'holler-cache-control')); ?>' + (response.data ? response.data.message : ''), 'error');
                }
            },
            error: function() {
                showNotice('<?php echo esc_js(__('Failed to purge cache. Please try again.', 'holler-cache-control')); ?>', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Show notice function
    function showNotice(message, type) {
        var noticeClass = 'notice-' + type;
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        $('.holler-cache-control-admin h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Settings form now uses standard WordPress form submission (no AJAX)
    // The form will submit normally to options.php and redirect back with success/error messages
});
</script>
