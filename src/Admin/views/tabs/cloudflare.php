<?php
/**
 * Cloudflare Tab - Cloudflare Configuration & Management
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/views/tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check Cloudflare credential configuration
$credentials = array(
    'email' => defined('CLOUDFLARE_EMAIL'),
    'api_key' => defined('CLOUDFLARE_API_KEY'),
    'zone_id' => defined('CLOUDFLARE_ZONE_ID')
);

// Get Cloudflare configuration guidance
$config_guidance = \Holler\CacheControl\get_cloudflare_config_guidance();
$config_status = \Holler\CacheControl\get_cloudflare_config_status();

// Get development mode status
$dev_mode_status = null;
if ($cloudflare_status['status'] === 'active') {
    $dev_mode_status = \Holler\CacheControl\Admin\Cache\Cloudflare::get_development_mode();
}
?>

<!-- Cloudflare Status Overview -->
<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Cloudflare Status', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
            <div class="cache-details">
                <h4><?php _e('Cache Status', 'holler-cache-control'); ?></h4>
                <p>
                    <span class="<?php echo esc_attr($cloudflare_status['status']); ?>">
                        <span class="status-indicator"></span>
                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $cloudflare_status['status']))); ?>
                    </span>
                </p>
                <p><em><?php echo esc_html($cloudflare_status['message']); ?></em></p>
            </div>
            
            <div class="cache-details">
                <h4><?php _e('APO Status', 'holler-cache-control'); ?></h4>
                <p>
                    <span class="<?php echo esc_attr($cloudflare_apo_status['status']); ?>">
                        <span class="status-indicator"></span>
                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $cloudflare_apo_status['status']))); ?>
                    </span>
                </p>
                <p><em><?php echo esc_html($cloudflare_apo_status['message']); ?></em></p>
            </div>
            
            <?php if ($dev_mode_status): ?>
            <div class="cache-details">
                <h4><?php _e('Development Mode', 'holler-cache-control'); ?></h4>
                <p>
                    <span class="<?php echo $dev_mode_status['value'] === 'on' ? 'active' : 'inactive'; ?>">
                        <span class="status-indicator"></span>
                        <?php echo $dev_mode_status['value'] === 'on' ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?>
                    </span>
                </p>
                <p><em><?php echo esc_html($dev_mode_status['message']); ?></em></p>
                
                <div style="margin-top: 12px;">
                    <button type="button" class="button button-secondary" id="toggle-dev-mode" 
                            data-current-status="<?php echo esc_attr($dev_mode_status['value']); ?>">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php echo $dev_mode_status['value'] === 'on' ? __('Disable Dev Mode', 'holler-cache-control') : __('Enable Dev Mode', 'holler-cache-control'); ?>
                    </button>
                </div>
                
                <?php if ($dev_mode_status['value'] === 'on'): ?>
                <div class="holler-notice notice-warning" style="margin-top: 8px; padding: 8px 12px;">
                    <p style="margin: 0; font-size: 12px;">
                        <strong><?php _e('Note:', 'holler-cache-control'); ?></strong> 
                        <?php _e('Development mode bypasses cache for 3 hours, then automatically disables.', 'holler-cache-control'); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($cloudflare_status['status'] === 'active' && $apo_info): ?>
            <div class="cache-details">
                <h4><?php _e('APO Information', 'holler-cache-control'); ?></h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <?php foreach ($apo_info as $key => $value): ?>
                        <div>
                            <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</strong><br>
                            <?php echo esc_html($value); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cloudflare Configuration -->
<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Cloudflare Configuration', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <?php if (array_filter($credentials)): ?>
            <div class="holler-notice notice-info">
                <p><strong><?php _e('Configuration Status:', 'holler-cache-control'); ?></strong> 
                <?php _e('Some settings are defined in wp-config.php or user-configs.php and will override any values set here:', 'holler-cache-control'); ?></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <?php foreach ($credentials as $key => $is_constant): ?>
                        <?php if ($is_constant): ?>
                            <li><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?> <?php _e('is defined in configuration file', 'holler-cache-control'); ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php" id="cloudflare-settings-form">
            <?php
            settings_fields('agnt_cache_settings');
            do_settings_sections('agnt_cache_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Cloudflare Email', 'holler-cache-control'); ?></th>
                    <td>
                        <?php if ($credentials['email']): ?>
                            <input type="text" disabled value="<?php _e('Defined in configuration file', 'holler-cache-control'); ?>" class="regular-text">
                            <p class="description"><?php _e('This value is set via CLOUDFLARE_EMAIL constant.', 'holler-cache-control'); ?></p>
                        <?php else: ?>
                            <input type="email" name="agnt_cloudflare_email" value="<?php echo esc_attr(get_option('agnt_cloudflare_email')); ?>" class="regular-text">
                            <p class="description"><?php _e('Enter your Cloudflare account email address.', 'holler-cache-control'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Cloudflare API Key', 'holler-cache-control'); ?></th>
                    <td>
                        <?php if ($credentials['api_key']): ?>
                            <input type="text" disabled value="<?php _e('Defined in configuration file', 'holler-cache-control'); ?>" class="regular-text">
                            <p class="description"><?php _e('This value is set via CLOUDFLARE_API_KEY constant.', 'holler-cache-control'); ?></p>
                        <?php else: ?>
                            <input type="password" name="agnt_cloudflare_api_key" value="<?php echo esc_attr(get_option('agnt_cloudflare_api_key')); ?>" class="regular-text">
                            <p class="description"><?php _e('Enter your Cloudflare Global API Key or API Token.', 'holler-cache-control'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Cloudflare Zone ID', 'holler-cache-control'); ?></th>
                    <td>
                        <?php if ($credentials['zone_id']): ?>
                            <input type="text" disabled value="<?php _e('Defined in configuration file', 'holler-cache-control'); ?>" class="regular-text">
                            <p class="description"><?php _e('This value is set via CLOUDFLARE_ZONE_ID constant.', 'holler-cache-control'); ?></p>
                        <?php else: ?>
                            <input type="text" name="agnt_cloudflare_zone_id" value="<?php echo esc_attr(get_option('agnt_cloudflare_zone_id')); ?>" class="regular-text">
                            <p class="description"><?php _e('Enter your Cloudflare Zone ID for this domain.', 'holler-cache-control'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php 
            // Only show submit button if there are editable fields
            if (!empty(array_filter($credentials, function($v) { return !$v; }))): 
                submit_button(__('Save Cloudflare Settings', 'holler-cache-control'));
            endif;
            ?>
        </form>
    </div>
</div>

<!-- Configuration File Method -->
<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Recommended Configuration Method', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <p><?php _e('For enhanced security, it\'s recommended to define these credentials in your wp-config.php or user-configs.php file using the following constants:', 'holler-cache-control'); ?></p>
        
        <div class="cache-details">
            <h4><?php _e('wp-config.php Configuration', 'holler-cache-control'); ?></h4>
            <pre style="background: #f0f0f1; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; overflow-x: auto;">
define('CLOUDFLARE_EMAIL', 'your-email@example.com');
define('CLOUDFLARE_API_KEY', 'your-api-key');
define('CLOUDFLARE_ZONE_ID', 'your-zone-id');</pre>
            <p><?php _e('Constants defined in configuration files will take precedence over values set in the form above.', 'holler-cache-control'); ?></p>
        </div>
        
        <?php if (!empty($config_guidance)): ?>
            <div class="cache-details">
                <h4><?php _e('Configuration Guidance', 'holler-cache-control'); ?></h4>
                <div><?php echo wp_kses_post($config_guidance); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($config_status) && is_array($config_status)): ?>
            <div class="cache-details">
                <h4><?php _e('Current Configuration Status', 'holler-cache-control'); ?></h4>
                <div>
                    <?php if ($config_status['fully_configured']): ?>
                        <p style="color: #46b450;">✓ <?php _e('Cloudflare is fully configured', 'holler-cache-control'); ?></p>
                    <?php else: ?>
                        <p style="color: #dc3232;">✗ <?php _e('Cloudflare configuration is incomplete', 'holler-cache-control'); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($config_status['all_in_config']): ?>
                        <p><em><?php _e('All credentials are defined in wp-config.php (recommended)', 'holler-cache-control'); ?></em></p>
                    <?php elseif ($config_status['all_in_admin']): ?>
                        <p><em><?php _e('All credentials are stored in admin settings', 'holler-cache-control'); ?></em></p>
                    <?php elseif ($config_status['mixed_sources']): ?>
                        <p style="color: #ffb900;">⚠️ <?php _e('Credentials are from mixed sources - consider consolidating to wp-config.php', 'holler-cache-control'); ?></p>
                    <?php endif; ?>
                    
                    <p><strong><?php _e('Recommendation:', 'holler-cache-control'); ?></strong> 
                    <?php 
                    switch ($config_status['recommendation']) {
                        case 'optimal':
                            echo __('Configuration is optimal', 'holler-cache-control');
                            break;
                        case 'use_config':
                            echo __('Consider moving credentials to wp-config.php for better security', 'holler-cache-control');
                            break;
                        case 'consolidate':
                            echo __('Consolidate all credentials to wp-config.php', 'holler-cache-control');
                            break;
                        case 'configure':
                            echo __('Complete the Cloudflare configuration', 'holler-cache-control');
                            break;
                    }
                    ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cloudflare Actions -->
<?php if ($cloudflare_status['status'] === 'active'): ?>
<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Cloudflare Actions', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <button type="button" class="button button-secondary purge-cache" data-type="cloudflare">
                <span class="dashicons dashicons-cloud"></span>
                <?php _e('Purge Cloudflare Cache', 'holler-cache-control'); ?>
            </button>
            
            <?php if ($cloudflare_apo_status['status'] === 'active'): ?>
                <button type="button" class="button button-secondary purge-cache" data-type="cloudflare-apo">
                <span class="dashicons dashicons-admin-network"></span>    
                <span class="dashicons dashicons-cloud"></span>
                    <?php _e('Purge APO Cache', 'holler-cache-control'); ?>
                </button>
            <?php endif; ?>
            
            <button type="button" class="button button-secondary" id="test-cloudflare-connection">
                <span class="dashicons dashicons-admin-network"></span>
                <?php _e('Test Connection', 'holler-cache-control'); ?>
            </button>
        </div>
        
        <div id="cloudflare-test-results" style="margin-top: 16px; display: none;"></div>
    </div>
</div>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
    // Handle Cloudflare connection test
    $('#test-cloudflare-connection').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $results = $('#cloudflare-test-results');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'holler-cache-control')); ?>');
        $results.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'holler_test_cloudflare_connection',
                nonce: '<?php echo wp_create_nonce('holler_cache_control'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $results.html('<div class="holler-notice notice-success"><p><strong><?php echo esc_js(__('Connection Successful!', 'holler-cache-control')); ?></strong><br>' + response.data.message + '</p></div>').show();
                } else {
                    $results.html('<div class="holler-notice notice-error"><p><strong><?php echo esc_js(__('Connection Failed!', 'holler-cache-control')); ?></strong><br>' + (response.data ? response.data.message : '<?php echo esc_js(__('Unknown error occurred.', 'holler-cache-control')); ?>') + '</p></div>').show();
                }
            },
            error: function() {
                $results.html('<div class="holler-notice notice-error"><p><strong><?php echo esc_js(__('Connection Test Failed!', 'holler-cache-control')); ?></strong><br><?php echo esc_js(__('Unable to test connection. Please try again.', 'holler-cache-control')); ?></p></div>').show();
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Handle Cloudflare settings form submission
    $('#cloudflare-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"]');
        var originalText = $submitButton.val();

        $submitButton.prop('disabled', true).val('<?php echo esc_js(__('Saving...', 'holler-cache-control')); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=holler_save_cloudflare_settings&_wpnonce=<?php echo wp_create_nonce('holler_cloudflare_settings'); ?>',
            success: function(response) {
                if (response.success) {
                    showNotice('<?php echo esc_js(__('Cloudflare settings saved successfully!', 'holler-cache-control')); ?>', 'success');
                    // Reload page to reflect new settings
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('<?php echo esc_js(__('Failed to save settings: ', 'holler-cache-control')); ?>' + (response.data ? response.data.message : ''), 'error');
                }
            },
            error: function() {
                showNotice('<?php echo esc_js(__('Failed to save settings. Please try again.', 'holler-cache-control')); ?>', 'error');
            },
            complete: function() {
                $submitButton.prop('disabled', false).val(originalText);
            }
        });
    });
    
    // Handle Development Mode toggle
    $('#toggle-dev-mode').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var currentStatus = $button.data('current-status');
        var action = currentStatus === 'on' ? 'disable' : 'enable';
        var originalText = $button.text();
        var $statusSpan = $button.closest('.cache-details').find('span:first');
        var $statusText = $statusSpan.contents().filter(function() {
            return this.nodeType === 3; // Text nodes only
        });
        var $messageText = $button.closest('.cache-details').find('p:nth-child(3) em');
        var $warningNotice = $button.closest('.cache-details').find('.holler-notice');
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + 
            (action === 'enable' ? '<?php echo esc_js(__('Enabling...', 'holler-cache-control')); ?>' : '<?php echo esc_js(__('Disabling...', 'holler-cache-control')); ?>'));
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'holler_toggle_cloudflare_dev_mode',
                dev_mode_action: action,
                nonce: '<?php echo wp_create_nonce('holler_cache_control_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Update UI elements
                    var newStatus = response.data.new_status;
                    var isEnabled = newStatus === 'on';
                    
                    // Update status indicator and text
                    $statusSpan.removeClass('active inactive').addClass(isEnabled ? 'active' : 'inactive');
                    $statusText.replaceWith(isEnabled ? '<?php echo esc_js(__('Enabled', 'holler-cache-control')); ?>' : '<?php echo esc_js(__('Disabled', 'holler-cache-control')); ?>');
                    
                    // Update message
                    $messageText.text(response.data.message);
                    
                    // Update button
                    $button.data('current-status', newStatus)
                           .html('<span class="dashicons dashicons-admin-tools"></span> ' + 
                                (isEnabled ? '<?php echo esc_js(__('Disable Dev Mode', 'holler-cache-control')); ?>' : '<?php echo esc_js(__('Enable Dev Mode', 'holler-cache-control')); ?>'));
                    
                    // Show/hide warning notice
                    if (isEnabled && $warningNotice.length === 0) {
                        $button.parent().after('<div class="holler-notice notice-warning" style="margin-top: 8px; padding: 8px 12px;">' +
                            '<p style="margin: 0; font-size: 12px;">' +
                            '<strong><?php echo esc_js(__('Note:', 'holler-cache-control')); ?></strong> ' +
                            '<?php echo esc_js(__('Development mode bypasses cache for 3 hours, then automatically disables.', 'holler-cache-control')); ?>' +
                            '</p></div>');
                    } else if (!isEnabled && $warningNotice.length > 0) {
                        $warningNotice.remove();
                    }
                    
                    // Show success message
                    showNotice(response.data.message, 'success');
                } else {
                    showNotice('<?php echo esc_js(__('Failed to toggle development mode: ', 'holler-cache-control')); ?>' + (response.data ? response.data.message : ''), 'error');
                }
            },
            error: function() {
                showNotice('<?php echo esc_js(__('Failed to toggle development mode. Please try again.', 'holler-cache-control')); ?>', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});
</script>

<style>
.dashicons.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

#toggle-dev-mode {
    transition: all 0.3s ease;
}

#toggle-dev-mode:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.cache-details .holler-notice {
    border-radius: 4px;
    font-size: 12px;
}
</style>
