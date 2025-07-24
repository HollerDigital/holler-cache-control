<?php
/**
 * Security Tab - Cloudflare Security Settings
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/views/tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Holler\CacheControl\Admin\Cache\Cloudflare;

// Get current security settings
$security_settings = array();
$security_status = array(
    'security_level' => 'unknown',
    'bot_fight_mode' => 'unknown',
    'browser_check' => 'unknown',
    'email_obfuscation' => 'unknown',
    'challenge_passage' => 'unknown'
);

if ($cloudflare_status['status'] === 'active') {
    // Get security settings from Cloudflare API
    $security_data = Cloudflare::get_security_settings();
    if ($security_data && isset($security_data['success']) && $security_data['success']) {
        $security_settings = $security_data['settings'];
        $security_status = array_merge($security_status, $security_settings);
    }
}

?>

<div class="security-tab-content">
    
    <!-- Security Status Overview -->
    <div class="cache-status-grid">
        <div class="cache-card">
            <div class="cache-header">
                <h3>
                    <span class="dashicons dashicons-shield"></span>
                    <?php _e('Security Overview', 'holler-cache-control'); ?>
                </h3>
                <div class="status-badge <?php echo $cloudflare_status['status'] === 'active' ? 'active' : 'inactive'; ?>">
                    <span class="status-indicator"></span>
                    <?php echo $cloudflare_status['status'] === 'active' ? __('Protected', 'holler-cache-control') : __('Not Protected', 'holler-cache-control'); ?>
                </div>
            </div>
            <div class="cache-details">
                <p><?php _e('Cloudflare security features help protect your website from threats, bots, and malicious traffic.', 'holler-cache-control'); ?></p>
                
                <?php if ($cloudflare_status['status'] === 'active'): ?>
                    <div class="details">
                        <p><strong><?php _e('Security Level:', 'holler-cache-control'); ?></strong> 
                            <span class="security-level-display"><?php echo esc_html(ucfirst($security_status['security_level'])); ?></span>
                        </p>
                        <p><strong><?php _e('Bot Fight Mode:', 'holler-cache-control'); ?></strong> 
                            <span class="bot-fight-display"><?php echo $security_status['bot_fight_mode'] === 'on' ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?></span>
                        </p>
                        <p><strong><?php _e('Browser Integrity Check:', 'holler-cache-control'); ?></strong> 
                            <span class="browser-check-display"><?php echo $security_status['browser_check'] === 'on' ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?></span>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="details">
                        <p class="notice-warning"><?php _e('Cloudflare credentials not configured. Security features are not available.', 'holler-cache-control'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($cloudflare_status['status'] === 'active'): ?>
    
    <!-- Security Settings -->
    <div class="security-settings-section">
        <h2><?php _e('Security Settings', 'holler-cache-control'); ?></h2>
        
        <div class="cache-status-grid">
            
            <!-- Security Level -->
            <div class="cache-card">
                <div class="cache-header">
                    <h3>
                        <span class="dashicons dashicons-admin-network"></span>
                        <?php _e('Security Level', 'holler-cache-control'); ?>
                    </h3>
                </div>
                <div class="cache-details">
                    <p><?php _e('Controls how aggressive Cloudflare is in challenging visitors.', 'holler-cache-control'); ?></p>
                    
                    <div class="security-control">
                        <label for="security-level"><?php _e('Current Level:', 'holler-cache-control'); ?></label>
                        <select id="security-level" class="security-setting" data-setting="security_level">
                            <option value="essentially_off" <?php selected($security_status['security_level'], 'essentially_off'); ?>><?php _e('Essentially Off', 'holler-cache-control'); ?></option>
                            <option value="low" <?php selected($security_status['security_level'], 'low'); ?>><?php _e('Low', 'holler-cache-control'); ?></option>
                            <option value="medium" <?php selected($security_status['security_level'], 'medium'); ?>><?php _e('Medium', 'holler-cache-control'); ?></option>
                            <option value="high" <?php selected($security_status['security_level'], 'high'); ?>><?php _e('High', 'holler-cache-control'); ?></option>
                            <option value="under_attack" <?php selected($security_status['security_level'], 'under_attack'); ?>><?php _e('I\'m Under Attack!', 'holler-cache-control'); ?></option>
                        </select>
                    </div>
                    
                    <div class="cache-actions">
                        <button type="button" class="button button-primary update-security-setting" data-setting="security_level">
                            <?php _e('Update Security Level', 'holler-cache-control'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Bot Fight Mode -->
            <div class="cache-card">
                <div class="cache-header">
                    <h3>
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php _e('Bot Fight Mode', 'holler-cache-control'); ?>
                    </h3>
                    <div class="status-badge <?php echo $security_status['bot_fight_mode'] === 'on' ? 'active' : 'inactive'; ?>">
                        <span class="status-indicator"></span>
                        <?php echo $security_status['bot_fight_mode'] === 'on' ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?>
                    </div>
                </div>
                <div class="cache-details">
                    <p><?php _e('Automatically challenges and blocks malicious bots.', 'holler-cache-control'); ?></p>
                    
                    <div class="cache-actions">
                        <button type="button" class="button toggle-security-setting" data-setting="bot_fight_mode" data-current="<?php echo esc_attr($security_status['bot_fight_mode']); ?>">
                            <?php echo $security_status['bot_fight_mode'] === 'on' ? __('Disable Bot Fight Mode', 'holler-cache-control') : __('Enable Bot Fight Mode', 'holler-cache-control'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Browser Integrity Check -->
            <div class="cache-card">
                <div class="cache-header">
                    <h3>
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Browser Integrity Check', 'holler-cache-control'); ?>
                    </h3>
                    <div class="status-badge <?php echo $security_status['browser_check'] === 'on' ? 'active' : 'inactive'; ?>">
                        <span class="status-indicator"></span>
                        <?php echo $security_status['browser_check'] === 'on' ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?>
                    </div>
                </div>
                <div class="cache-details">
                    <p><?php _e('Checks for common HTTP headers abused by spammers.', 'holler-cache-control'); ?></p>
                    
                    <div class="cache-actions">
                        <button type="button" class="button toggle-security-setting" data-setting="browser_check" data-current="<?php echo esc_attr($security_status['browser_check']); ?>">
                            <?php echo $security_status['browser_check'] === 'on' ? __('Disable Browser Check', 'holler-cache-control') : __('Enable Browser Check', 'holler-cache-control'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Email Obfuscation -->
            <div class="cache-card">
                <div class="cache-header">
                    <h3>
                        <span class="dashicons dashicons-email"></span>
                        <?php _e('Email Obfuscation', 'holler-cache-control'); ?>
                    </h3>
                    <div class="status-badge <?php echo $security_status['email_obfuscation'] === 'on' ? 'active' : 'inactive'; ?>">
                        <span class="status-indicator"></span>
                        <?php echo $security_status['email_obfuscation'] === 'on' ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?>
                    </div>
                </div>
                <div class="cache-details">
                    <p><?php _e('Hides email addresses from bots and spammers.', 'holler-cache-control'); ?></p>
                    
                    <div class="cache-actions">
                        <button type="button" class="button toggle-security-setting" data-setting="email_obfuscation" data-current="<?php echo esc_attr($security_status['email_obfuscation']); ?>">
                            <?php echo $security_status['email_obfuscation'] === 'on' ? __('Disable Email Obfuscation', 'holler-cache-control') : __('Enable Email Obfuscation', 'holler-cache-control'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Security Diagnostics -->
    <div class="security-diagnostics-section">
        <h2><?php _e('Security Diagnostics', 'holler-cache-control'); ?></h2>
        
        <div class="cache-status-grid">
            
            <!-- Security Recommendations -->
            <div class="cache-card">
                <div class="cache-header">
                    <h3>
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php _e('Security Recommendations', 'holler-cache-control'); ?>
                    </h3>
                </div>
                <div class="cache-details">
                    <div class="security-recommendations">
                        
                        <?php if ($security_status['security_level'] === 'essentially_off'): ?>
                        <div class="recommendation-item warning">
                            <p><strong><?php _e('⚠️ Low Security Level', 'holler-cache-control'); ?></strong></p>
                            <p><?php _e('Your security level is set to "Essentially Off". Consider increasing to "Low" or "Medium" for better protection.', 'holler-cache-control'); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($security_status['bot_fight_mode'] === 'off'): ?>
                        <div class="recommendation-item info">
                            <p><strong><?php _e('💡 Bot Fight Mode Disabled', 'holler-cache-control'); ?></strong></p>
                            <p><?php _e('Enable Bot Fight Mode to automatically block malicious bots and improve site performance.', 'holler-cache-control'); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($security_status['browser_check'] === 'off'): ?>
                        <div class="recommendation-item info">
                            <p><strong><?php _e('💡 Browser Integrity Check Disabled', 'holler-cache-control'); ?></strong></p>
                            <p><?php _e('Enable Browser Integrity Check to block requests from known malicious sources.', 'holler-cache-control'); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($security_status['security_level'] === 'medium' && $security_status['bot_fight_mode'] === 'on' && $security_status['browser_check'] === 'on'): ?>
                        <div class="recommendation-item success">
                            <p><strong><?php _e('✅ Good Security Configuration', 'holler-cache-control'); ?></strong></p>
                            <p><?php _e('Your security settings provide a good balance of protection and user experience.', 'holler-cache-control'); ?></p>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
            
            <!-- Security Status Summary -->
            <div class="cache-card">
                <div class="cache-header">
                    <h3>
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php _e('Security Status', 'holler-cache-control'); ?>
                    </h3>
                </div>
                <div class="cache-details">
                    <div class="security-status-grid">
                        <div class="status-item">
                            <span class="status-label"><?php _e('Security Level:', 'holler-cache-control'); ?></span>
                            <span class="status-value security-level-<?php echo esc_attr($security_status['security_level']); ?>">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $security_status['security_level']))); ?>
                            </span>
                        </div>
                        <div class="status-item">
                            <span class="status-label"><?php _e('Bot Protection:', 'holler-cache-control'); ?></span>
                            <span class="status-value <?php echo $security_status['bot_fight_mode'] === 'on' ? 'enabled' : 'disabled'; ?>">
                                <?php echo $security_status['bot_fight_mode'] === 'on' ? __('Active', 'holler-cache-control') : __('Inactive', 'holler-cache-control'); ?>
                            </span>
                        </div>
                        <div class="status-item">
                            <span class="status-label"><?php _e('Browser Check:', 'holler-cache-control'); ?></span>
                            <span class="status-value <?php echo $security_status['browser_check'] === 'on' ? 'enabled' : 'disabled'; ?>">
                                <?php echo $security_status['browser_check'] === 'on' ? __('Active', 'holler-cache-control') : __('Inactive', 'holler-cache-control'); ?>
                            </span>
                        </div>
                        <div class="status-item">
                            <span class="status-label"><?php _e('Email Protection:', 'holler-cache-control'); ?></span>
                            <span class="status-value <?php echo $security_status['email_obfuscation'] === 'on' ? 'enabled' : 'disabled'; ?>">
                                <?php echo $security_status['email_obfuscation'] === 'on' ? __('Active', 'holler-cache-control') : __('Inactive', 'holler-cache-control'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <?php else: ?>
    
    <!-- Cloudflare Not Configured -->
    <div class="cache-status-grid">
        <div class="cache-card">
            <div class="cache-header">
                <h3>
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('Cloudflare Not Configured', 'holler-cache-control'); ?>
                </h3>
            </div>
            <div class="cache-details">
                <p><?php _e('To access Cloudflare security features, you need to configure your Cloudflare credentials first.', 'holler-cache-control'); ?></p>
                <div class="cache-actions">
                    <a href="<?php echo esc_url(add_query_arg('tab', 'cloudflare', admin_url('options-general.php?page=settings_page_holler-cache-control'))); ?>" class="button button-primary">
                        <?php _e('Configure Cloudflare', 'holler-cache-control'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
    
</div>

<style>
/* Security Tab Specific Styles */
.security-tab-content {
    margin-top: 20px;
}

.security-settings-section,
.security-diagnostics-section {
    margin: 30px 0;
}

.security-control {
    margin: 15px 0;
}

.security-control label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.security-control select {
    width: 100%;
    max-width: 300px;
}

.security-recommendations {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.recommendation-item {
    padding: 12px;
    border-radius: 4px;
    border-left: 4px solid;
}

.recommendation-item.success {
    border-left-color: #00a32a;
    background: #f6ffed;
}

.recommendation-item.warning {
    border-left-color: #ffb900;
    background: #fffbf0;
}

.recommendation-item.info {
    border-left-color: #72aee6;
    background: #f0f6fc;
}

.recommendation-item p {
    margin: 0 0 8px 0;
}

.recommendation-item p:last-child {
    margin-bottom: 0;
}

.security-status-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e2e4e7;
}

.status-item:last-child {
    border-bottom: none;
}

.status-label {
    font-weight: 600;
    color: #333;
}

.status-value {
    font-weight: 500;
}

.status-value.enabled {
    color: #00a32a;
}

.status-value.disabled {
    color: #dc3232;
}

.security-level-essentially_off {
    color: #dc3232;
}

.security-level-low {
    color: #ffb900;
}

.security-level-medium {
    color: #00a32a;
}

.security-level-high {
    color: #0073aa;
}

.security-level-under_attack {
    color: #d63638;
    font-weight: 600;
}

@media screen and (max-width: 782px) {
    .security-status-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Handle security setting updates
    $('.update-security-setting, .toggle-security-setting').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var setting = $button.data('setting');
        var value;
        
        if ($button.hasClass('toggle-security-setting')) {
            // Toggle setting
            var current = $button.data('current');
            value = current === 'on' ? 'off' : 'on';
        } else {
            // Get value from select
            value = $('#' + setting.replace('_', '-')).val();
        }
        
        // Show loading state
        var originalText = $button.text();
        $button.prop('disabled', true).text('<?php echo esc_js(__('Updating...', 'holler-cache-control')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'holler_update_security_setting',
                setting: setting,
                value: value,
                nonce: '<?php echo wp_create_nonce('holler_cache_control_security'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('<?php echo esc_js(__('Security setting updated successfully!', 'holler-cache-control')); ?>', 'success');
                    // Refresh page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('<?php echo esc_js(__('Failed to update security setting: ', 'holler-cache-control')); ?>' + (response.data ? response.data.message : ''), 'error');
                }
            },
            error: function() {
                showNotice('<?php echo esc_js(__('Failed to update security setting. Please try again.', 'holler-cache-control')); ?>', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Show notice function (reuse from main admin)
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
    
});
</script>
