<?php
/**
 * Settings Tab - Plugin Configuration
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/views/tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Plugin Settings', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Configure plugin visibility, admin bar features, user access, and automatic cache purging behavior.', 'holler-cache-control'); ?></p>
        
        <form method="post" action="options.php" id="holler-cache-control-settings">
            <?php
            settings_fields('holler_cache_control_settings');
            
            // Display all settings sections manually with consistent formatting
            global $wp_settings_fields;
            
            // Plugin & Feature Visibility Section
            if (isset($wp_settings_fields['holler_cache_control_settings']['holler_cache_control_visibility'])) {
                echo '<div class="settings-section" style="margin-bottom: 40px;">';
                echo '<h3 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">' . __('Plugin & Feature Visibility', 'holler-cache-control') . '</h3>';
                
                foreach ($wp_settings_fields['holler_cache_control_settings']['holler_cache_control_visibility'] as $field) {
                    echo '<div class="settings-field" style="margin-bottom: 30px;">';
                    echo '<h4>' . $field['title'] . '</h4>';
                    call_user_func($field['callback'], $field['args']);
                    echo '</div>';
                }
                echo '</div>';
            }
            
            // Automatic Cache Purging Section
            if (isset($wp_settings_fields['holler_cache_control_settings']['holler_cache_control_auto_purge'])) {
                echo '<div class="settings-section" style="margin-bottom: 40px;">';
                echo '<h3 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">' . __('Automatic Cache Purging', 'holler-cache-control') . '</h3>';
                echo '<p style="margin-bottom: 20px; color: #666;">' . __('Configure when caches should be automatically purged and which cache layers to clear.', 'holler-cache-control') . '</p>';
                
                foreach ($wp_settings_fields['holler_cache_control_settings']['holler_cache_control_auto_purge'] as $field) {
                    echo '<div class="settings-field" style="margin-bottom: 30px;">';
                    echo '<h4>' . $field['title'] . '</h4>';
                    call_user_func($field['callback'], $field['args']);
                    echo '</div>';
                }
                echo '</div>';
            }
            
            // Smart Cache Invalidation Section
            if (isset($wp_settings_fields['holler_cache_control_settings']['holler_cache_control_smart_invalidation'])) {
                echo '<div class="settings-section" style="margin-bottom: 40px;">';
                echo '<h3 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">' . __('Smart Cache Invalidation', 'holler-cache-control') . '</h3>';
                echo '<p style="margin-bottom: 20px; color: #666;">' . __('Configure intelligent cache purging strategies for optimal performance and selective invalidation.', 'holler-cache-control') . '</p>';
                
                foreach ($wp_settings_fields['holler_cache_control_settings']['holler_cache_control_smart_invalidation'] as $field) {
                    echo '<div class="settings-field" style="margin-bottom: 30px;">';
                    echo '<h4>' . $field['title'] . '</h4>';
                    call_user_func($field['callback'], $field['args']);
                    echo '</div>';
                }
                echo '</div>';
            }
            
            submit_button(__('Save All Settings', 'holler-cache-control'), 'primary', 'submit', true, array('style' => 'margin-top: 20px;'));
            ?>
        </form>
    </div>
</div>





