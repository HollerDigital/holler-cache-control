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

// Check if credentials are available (either constants or options)
$email = defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : get_option('cloudflare_email', '');
$api_key = defined('CLOUDFLARE_API_KEY') ? CLOUDFLARE_API_KEY : get_option('cloudflare_api_key', '');
$zone_id = defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : get_option('cloudflare_zone_id', '');
$has_credentials = !empty($email) && !empty($api_key) && !empty($zone_id);

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
                            <?php 
                            if (is_array($value)) {
                                echo esc_html(implode(', ', $value));
                            } else {
                                echo esc_html($value);
                            }
                            ?>
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

<!-- Cloudflare Settings Check & Configuration -->
<?php if ($cloudflare_status['status'] === 'active'): ?>
<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Check & Configure Settings', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Verify your Cloudflare credentials and apply recommended performance settings for optimal WordPress caching.', 'holler-cache-control'); ?></p>
        
        <div style="margin: 16px 0;">
            <button type="button" class="button button-primary" id="check-cloudflare-settings">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Check & Configure Settings', 'holler-cache-control'); ?>
            </button>
        </div>
        
        <div class="holler-notice notice-info" style="margin-top: 16px;">
            <p><strong><?php _e('What this does:', 'holler-cache-control'); ?></strong></p>
            <ul style="margin-left: 20px;">
                <li><?php _e('✓ Verifies your Cloudflare API credentials', 'holler-cache-control'); ?></li>
                <li><?php _e('✓ Displays current Cloudflare zone settings', 'holler-cache-control'); ?></li>
                <li><?php _e('✓ Applies recommended performance optimizations', 'holler-cache-control'); ?></li>
                <li><?php _e('✓ Shows detailed before/after comparison', 'holler-cache-control'); ?></li>
            </ul>
            <p><em><?php _e('Recommended settings include: optimal cache TTL, auto-minification, Brotli compression, and other performance enhancements.', 'holler-cache-control'); ?></em></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Cloudflare Settings Controls -->
<?php if ($has_credentials): ?>
<div class="holler-card">
    <h3><?php _e('🎛️ Cloudflare Settings Controls', 'holler-cache-control'); ?></h3>
    <p><?php _e('Configure your Cloudflare zone settings directly from here. Changes are applied immediately.', 'holler-cache-control'); ?></p>
    
    <div class="cloudflare-controls-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
        
        <!-- Essential Controls -->
        <div class="control-group">
            <h4>🚀 Essential Controls</h4>
            
            <!-- Development Mode -->
            <div class="control-item" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; justify-content: space-between;">
                    <span><strong>🔧 Development Mode</strong><br><small>Bypasses cache for testing</small></span>
                    <label class="toggle-switch" style="margin-left: 10px;">
                        <input type="checkbox" id="dev-mode-toggle" data-setting="development_mode">
                        <span class="toggle-slider"></span>
                    </label>
                </label>
            </div>
            
            <!-- Cache Level -->
            <div class="control-item" style="margin-bottom: 15px;">
                <label><strong>📈 Cache Level</strong><br><small>How much content to cache</small></label>
                <select id="cache-level-select" data-setting="cache_level" style="width: 100%; margin-top: 5px;">
                    <option value="basic">Basic - Cache static content only</option>
                    <option value="simplified">Simplified - Cache static + some dynamic</option>
                    <option value="aggressive">Aggressive - Cache everything possible</option>
                </select>
            </div>
            
            <!-- Browser Cache TTL -->
            <div class="control-item" style="margin-bottom: 15px;">
                <label><strong>⏱️ Browser Cache TTL</strong><br><small>How long browsers cache content</small></label>
                <select id="browser-ttl-select" data-setting="browser_cache_ttl" style="width: 100%; margin-top: 5px;">
                    <option value="1800">30 minutes</option>
                    <option value="7200">2 hours</option>
                    <option value="14400">4 hours</option>
                    <option value="28800">8 hours</option>
                    <option value="86400">1 day</option>
                    <option value="604800">1 week</option>
                    <option value="2592000">1 month</option>
                </select>
            </div>
            
            <!-- Always Online -->
            <div class="control-item" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; justify-content: space-between;">
                    <span><strong>🌐 Always Online</strong><br><small>Serve cached version if origin is down</small></span>
                    <label class="toggle-switch" style="margin-left: 10px;">
                        <input type="checkbox" id="always-online-toggle" data-setting="always_online">
                        <span class="toggle-slider"></span>
                    </label>
                </label>
            </div>
        </div>
        
        <!-- Advanced Controls -->
        <div class="control-group">
            <h4>⚡ Advanced Controls</h4>
            
            <!-- Rocket Loader -->
            <div class="control-item" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; justify-content: space-between;">
                    <span><strong>⚡ Rocket Loader</strong><br><small>Async JavaScript loading</small></span>
                    <label class="toggle-switch" style="margin-left: 10px;">
                        <input type="checkbox" id="rocket-loader-toggle" data-setting="rocket_loader">
                        <span class="toggle-slider"></span>
                    </label>
                </label>
            </div>
            
            <!-- Auto Minify -->
            <div class="control-item" style="margin-bottom: 15px;">
                <label><strong>🗜️ Auto Minify</strong><br><small>Compress HTML, CSS, and JS</small></label>
                <div style="margin-top: 8px;">
                    <label style="display: inline-block; margin-right: 15px;"><input type="checkbox" id="minify-html" data-setting="auto_minify_html"> HTML</label>
                    <label style="display: inline-block; margin-right: 15px;"><input type="checkbox" id="minify-css" data-setting="auto_minify_css"> CSS</label>
                    <label style="display: inline-block;"><input type="checkbox" id="minify-js" data-setting="auto_minify_js"> JS</label>
                </div>
            </div>
            
            <!-- Security Level -->
            <div class="control-item" style="margin-bottom: 15px;">
                <label><strong>🛡️ Security Level</strong><br><small>Challenge level for visitors</small></label>
                <select id="security-level-select" data-setting="security_level" style="width: 100%; margin-top: 5px;">
                    <option value="off">Off - No challenges</option>
                    <option value="essentially_off">Essentially Off - Very low</option>
                    <option value="low">Low - Challenge bad IPs</option>
                    <option value="medium">Medium - Standard protection</option>
                    <option value="high">High - Challenge more visitors</option>
                    <option value="under_attack">I'm Under Attack! - Maximum protection</option>
                </select>
            </div>
            
            <!-- SSL Mode -->
            <div class="control-item" style="margin-bottom: 15px;">
                <label><strong>🔒 SSL Mode</strong><br><small>SSL/TLS encryption level</small></label>
                <select id="ssl-mode-select" data-setting="ssl" style="width: 100%; margin-top: 5px;">
                    <option value="off">Off - No encryption</option>
                    <option value="flexible">Flexible - Cloudflare to visitor only</option>
                    <option value="full">Full - End-to-end (any cert)</option>
                    <option value="strict">Full (Strict) - End-to-end (valid cert)</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="control-actions" style="margin-top: 20px; text-align: center;">
        <button type="button" id="load-current-settings" class="button button-secondary">
            🔄 Load Current Settings
        </button>
        <button type="button" id="apply-recommended-settings" class="button button-primary" style="margin-left: 10px;">
            ⚡ Apply Recommended Settings
        </button>
    </div>
    
    <div class="holler-notice notice-info" style="margin-top: 16px;">
        <p><strong><?php _e('How it works:', 'holler-cache-control'); ?></strong></p>
        <ul style="margin-left: 20px;">
            <li><?php _e('✓ Changes are applied immediately via Cloudflare API', 'holler-cache-control'); ?></li>
            <li><?php _e('✓ Load current settings to see your existing configuration', 'holler-cache-control'); ?></li>
            <li><?php _e('✓ Apply recommended settings for optimal performance', 'holler-cache-control'); ?></li>
            <li><?php _e('✓ Each control updates independently with instant feedback', 'holler-cache-control'); ?></li>
        </ul>
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
    
    // Handle Cloudflare settings form submission (but NOT the check-settings form)
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
    
    // Cloudflare Settings Check Button
    $('#check-cloudflare-settings').click(function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var originalText = $btn.text();
        
        // Update button state
        $btn.prop('disabled', true).text('Checking Settings...');
        
        // Make AJAX request
        $.post(ajaxurl, {
            action: 'cloudflare_simple_check',
            nonce: '<?php echo wp_create_nonce('cloudflare_simple'); ?>'
        })
        .done(function(response) {
            console.log('Cloudflare check response:', response);
            console.log('Response success:', response.success);
            console.log('Response data:', response.data);
            
            if (response.success) {
                showNotice('✅ ' + response.data.message, 'success');
                
                // Show configuration details
                if (response.data.details && response.data.details.length > 0) {
                    var detailsHtml = '<div style="margin-top: 10px;"><strong>Configuration Details:</strong><ul>';
                    response.data.details.forEach(function(detail) {
                        detailsHtml += '<li>' + detail + '</li>';
                    });
                    detailsHtml += '</ul></div>';
                    
                    showNotice(detailsHtml, 'info');
                }
            } else {
                showNotice('❌ Error: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX failed:', error);
            showNotice('❌ Request failed: ' + error, 'error');
        })
        .always(function() {
            // Reset button
            $btn.prop('disabled', false).text(originalText);
        });
    });
    
    // Show notice function
    function showNotice(message, type) {
        var noticeClass = 'notice-' + type;
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        $('.holler-cache-control-admin h1').after($notice);
        
        // Auto-dismiss after 8 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 8000);
    }
    
    // Cloudflare Settings Controls
    
    // Load Current Settings
    $('#load-current-settings').click(function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('🔄 Loading...');
        
        $.post(ajaxurl, {
            action: 'cloudflare_load_settings',
            nonce: '<?php echo wp_create_nonce('cloudflare_settings'); ?>'
        })
        .done(function(response) {
            if (response.success && response.data.settings) {
                populateControls(response.data.settings);
                showNotice('✅ Current settings loaded successfully!', 'success');
            } else {
                showNotice('❌ Failed to load settings: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
            }
        })
        .fail(function() {
            showNotice('❌ Failed to load settings', 'error');
        })
        .always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });
    
    // Apply Recommended Settings
    $('#apply-recommended-settings').click(function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('⚡ Applying...');
        
        var recommendedSettings = {
            development_mode: 'off',
            cache_level: 'aggressive',
            browser_cache_ttl: '14400', // 4 hours
            always_online: 'on',
            rocket_loader: 'on',
            auto_minify_html: true,
            auto_minify_css: true,
            auto_minify_js: true,
            security_level: 'medium',
            ssl: 'strict'
        };
        
        applyMultipleSettings(recommendedSettings, function(success) {
            if (success) {
                populateControls(recommendedSettings);
                showNotice('⚡ Recommended settings applied successfully!', 'success');
            }
            $btn.prop('disabled', false).text(originalText);
        });
    });
    
    // Individual Setting Changes
    $('[data-setting]').on('change', function() {
        var $control = $(this);
        var setting = $control.data('setting');
        var value;
        
        if ($control.is('input[type="checkbox"]')) {
            if (setting.startsWith('auto_minify_')) {
                // Handle minify checkboxes specially
                updateMinifySettings();
                return;
            } else {
                value = $control.is(':checked') ? 'on' : 'off';
            }
        } else {
            value = $control.val();
        }
        
        updateSetting(setting, value);
    });
    
    // Update individual setting
    function updateSetting(setting, value) {
        $.post(ajaxurl, {
            action: 'cloudflare_update_setting',
            setting: setting,
            value: value,
            nonce: '<?php echo wp_create_nonce('cloudflare_settings'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                showNotice('✅ ' + setting.replace('_', ' ').toUpperCase() + ' updated successfully!', 'success');
            } else {
                showNotice('❌ Failed to update ' + setting + ': ' + (response.data ? response.data.message : 'Unknown error'), 'error');
            }
        })
        .fail(function() {
            showNotice('❌ Failed to update ' + setting, 'error');
        });
    }
    
    // Update minify settings (combined)
    function updateMinifySettings() {
        var minifySettings = {
            html: $('#minify-html').is(':checked'),
            css: $('#minify-css').is(':checked'),
            js: $('#minify-js').is(':checked')
        };
        
        $.post(ajaxurl, {
            action: 'cloudflare_update_minify',
            settings: minifySettings,
            nonce: '<?php echo wp_create_nonce('cloudflare_settings'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                var enabled = [];
                if (minifySettings.html) enabled.push('HTML');
                if (minifySettings.css) enabled.push('CSS');
                if (minifySettings.js) enabled.push('JS');
                
                var message = enabled.length > 0 ? 
                    '✅ Auto Minify updated: ' + enabled.join(', ') + ' enabled' :
                    '✅ Auto Minify disabled';
                    
                showNotice(message, 'success');
            } else {
                showNotice('❌ Failed to update Auto Minify: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
            }
        })
        .fail(function() {
            showNotice('❌ Failed to update Auto Minify', 'error');
        });
    }
    
    // Apply multiple settings
    function applyMultipleSettings(settings, callback) {
        var settingsArray = Object.keys(settings).map(function(key) {
            return { setting: key, value: settings[key] };
        });
        
        $.post(ajaxurl, {
            action: 'cloudflare_update_multiple',
            settings: settingsArray,
            nonce: '<?php echo wp_create_nonce('cloudflare_settings'); ?>'
        })
        .done(function(response) {
            callback(response.success);
            if (!response.success) {
                showNotice('❌ Failed to apply settings: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
            }
        })
        .fail(function() {
            callback(false);
            showNotice('❌ Failed to apply settings', 'error');
        });
    }
    
    // Populate controls with current values
    function populateControls(settings) {
        // Toggle switches
        $('#dev-mode-toggle').prop('checked', settings.development_mode === 'on');
        $('#always-online-toggle').prop('checked', settings.always_online === 'on');
        $('#rocket-loader-toggle').prop('checked', settings.rocket_loader === 'on');
        
        // Dropdowns
        $('#cache-level-select').val(settings.cache_level || 'aggressive');
        $('#browser-ttl-select').val(settings.browser_cache_ttl || '14400');
        $('#security-level-select').val(settings.security_level || 'medium');
        $('#ssl-mode-select').val(settings.ssl || 'strict');
        
        // Minify checkboxes
        if (settings.auto_minify) {
            $('#minify-html').prop('checked', settings.auto_minify.html || false);
            $('#minify-css').prop('checked', settings.auto_minify.css || false);
            $('#minify-js').prop('checked', settings.auto_minify.js || false);
        } else {
            $('#minify-html').prop('checked', settings.auto_minify_html || false);
            $('#minify-css').prop('checked', settings.auto_minify_css || false);
            $('#minify-js').prop('checked', settings.auto_minify_js || false);
        }
    }
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

/* Toggle Switch Styles */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #2196F3;
}

input:focus + .toggle-slider {
    box-shadow: 0 0 1px #2196F3;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

/* Control Group Styles */
.control-group {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
}

.control-group h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 14px;
    font-weight: 600;
}

.control-item {
    background: white;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.control-item label {
    margin: 0;
    font-weight: normal;
}

.control-item strong {
    color: #333;
    font-size: 13px;
}

.control-item small {
    color: #666;
    font-size: 11px;
}

.control-item select {
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
}

.control-item input[type="checkbox"] {
    margin-right: 6px;
}

.control-actions {
    border-top: 1px solid #e0e0e0;
    padding-top: 15px;
}

.control-actions .button {
    min-width: 160px;
    height: 32px;
    font-size: 13px;
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
