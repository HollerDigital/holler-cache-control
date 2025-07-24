<?php
/**
 * Diagnostics Tab - System Diagnostics & Troubleshooting
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/views/tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get comprehensive diagnostics
$path_report = \Holler\CacheControl\Core\CachePathDetector::get_comprehensive_report();

// Get plugin recommendations
use Holler\CacheControl\Admin\Diagnostics\PluginDiagnostics;
$plugin_recommendations = PluginDiagnostics::get_plugin_recommendations();
$optimization_score = PluginDiagnostics::get_optimization_score($plugin_recommendations);
$priority_recommendations = PluginDiagnostics::get_priority_recommendations($plugin_recommendations);

// Get conflicting cache plugins
$tools = new \Holler\CacheControl\Admin\Tools('holler-cache-control', HOLLER_CACHE_CONTROL_VERSION);
$conflicting_plugins = $tools->detect_conflicting_cache_plugins();
$conflict_warnings = $tools->get_cache_plugin_conflict_warnings();
?>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('System Diagnostics', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Comprehensive system information and cache diagnostics for troubleshooting.', 'holler-cache-control'); ?></p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-top: 16px;">
            <div class="cache-details">
                <h4><?php _e('Plugin Information', 'holler-cache-control'); ?></h4>
                <p><strong><?php _e('Version:', 'holler-cache-control'); ?></strong> <?php echo esc_html(HOLLER_CACHE_CONTROL_VERSION); ?></p>
                <p><strong><?php _e('WordPress:', 'holler-cache-control'); ?></strong> <?php echo esc_html(get_bloginfo('version')); ?></p>
                <p><strong><?php _e('PHP:', 'holler-cache-control'); ?></strong> <?php echo esc_html(PHP_VERSION); ?></p>
            </div>
            
            <div class="cache-details">
                <h4><?php _e('Server Information', 'holler-cache-control'); ?></h4>
                <p><strong><?php _e('Server:', 'holler-cache-control'); ?></strong> <?php echo esc_html($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></p>
                <p><strong><?php _e('User:', 'holler-cache-control'); ?></strong> <?php echo esc_html(get_current_user()); ?></p>
                <p><strong><?php _e('Memory Limit:', 'holler-cache-control'); ?></strong> <?php echo esc_html(ini_get('memory_limit')); ?></p>
            </div>
            
            <div class="cache-details">
                <h4><?php _e('WordPress Configuration', 'holler-cache-control'); ?></h4>
                <p><strong><?php _e('WP Debug:', 'holler-cache-control'); ?></strong> <?php echo WP_DEBUG ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?></p>
                <p><strong><?php _e('WP Cache:', 'holler-cache-control'); ?></strong> <?php echo (defined('WP_CACHE') && WP_CACHE) ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?></p>
                <p><strong><?php _e('Redis Extension:', 'holler-cache-control'); ?></strong> <?php echo class_exists('Redis') ? __('Available', 'holler-cache-control') : __('Not Available', 'holler-cache-control'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Cache Plugin Conflicts -->
<?php if (!empty($conflicting_plugins)): ?>
<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Cache Plugin Conflicts', 'holler-cache-control'); ?></h3>
        <div class="conflict-summary">
            <span class="conflict-count <?php echo !empty($conflict_warnings) ? 'has-conflicts' : 'no-conflicts'; ?>">
                <?php 
                // Exclude informational plugins from conflict count
                $high_conflicts = array_filter($conflicting_plugins, function($plugin) { 
                    return $plugin['conflict_level'] === 'high' && !isset($plugin['is_informational']); 
                });
                $medium_conflicts = array_filter($conflicting_plugins, function($plugin) { 
                    return $plugin['conflict_level'] === 'medium' && !isset($plugin['is_informational']); 
                });
                $conflict_count = count($high_conflicts) + count($medium_conflicts);
                
                $info_plugins = array_filter($conflicting_plugins, function($plugin) { 
                    return isset($plugin['is_informational']) && $plugin['is_informational']; 
                });
                $info_count = count($info_plugins);
                
                if ($conflict_count > 0) {
                    printf(_n('%d Conflict Detected', '%d Conflicts Detected', $conflict_count, 'holler-cache-control'), $conflict_count);
                    if ($info_count > 0) {
                        printf(' | %d ' . _n('Info Plugin', 'Info Plugins', $info_count, 'holler-cache-control'), $info_count);
                    }
                } else {
                    if ($info_count > 0) {
                        printf('%d ' . _n('Info Plugin', 'Info Plugins', $info_count, 'holler-cache-control'), $info_count);
                    } else {
                        _e('No Conflicts', 'holler-cache-control');
                    }
                }
                ?>
            </span>
        </div>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Detection and analysis of other cache plugins that may conflict with Holler Cache Control.', 'holler-cache-control'); ?></p>
        
        <?php if (!empty($conflict_warnings)): ?>
            <div class="conflict-warnings" style="margin: 16px 0;">
                <?php foreach ($conflict_warnings as $warning): ?>
                    <div class="holler-notice notice-<?php echo esc_attr($warning['type']); ?>" style="margin-bottom: 12px;">
                        <p><?php echo wp_kses_post($warning['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="plugin-conflicts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-top: 16px;">
            <?php foreach ($conflicting_plugins as $plugin_key => $plugin_info): ?>
                <div class="conflict-plugin-card" style="border: 1px solid #ddd; border-radius: 4px; padding: 16px; background: #fff;">
                    <div class="plugin-conflict-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="margin: 0; font-size: 14px;"><?php echo esc_html($plugin_info['name']); ?></h4>
                        <span class="conflict-level conflict-<?php echo esc_attr($plugin_info['conflict_level']); ?>" style="padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                            <?php 
                            switch($plugin_info['conflict_level']) {
                                case 'high':
                                    echo '⚠️ ' . __('High Risk', 'holler-cache-control');
                                    break;
                                case 'medium':
                                    echo '⚡ ' . __('Medium Risk', 'holler-cache-control');
                                    break;
                                case 'low':
                                    echo '✅ ' . __('Low Risk', 'holler-cache-control');
                                    break;
                                case 'info':
                                    echo 'ℹ️ ' . __('Informational', 'holler-cache-control');
                                    break;
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="plugin-conflict-details">
                        <p style="margin: 0 0 8px 0; font-size: 13px; color: #666;">
                            <strong><?php _e('Description:', 'holler-cache-control'); ?></strong> <?php echo esc_html($plugin_info['description']); ?>
                        </p>
                        
                        <?php if (isset($plugin_info['purging_enabled'])): ?>
                            <p style="margin: 0 0 8px 0; font-size: 13px;">
                                <strong><?php _e('Auto-Purge:', 'holler-cache-control'); ?></strong> 
                                <span class="<?php echo $plugin_info['purging_enabled'] ? 'enabled' : 'disabled'; ?>">
                                    <?php echo $plugin_info['purging_enabled'] ? __('Enabled', 'holler-cache-control') : __('Disabled', 'holler-cache-control'); ?>
                                </span>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (isset($plugin_info['purge_settings']) && $plugin_key === 'nginx_helper'): ?>
                            <div style="margin: 8px 0; padding: 8px; background: #f8f9fa; border-radius: 3px;">
                                <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: bold; color: #495057;">
                                    <?php printf(__('Purge Triggers (%d of %d enabled):', 'holler-cache-control'), $plugin_info['enabled_purge_count'], $plugin_info['total_purge_settings']); ?>
                                </p>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 4px; font-size: 11px;">
                                    <?php foreach ($plugin_info['purge_settings'] as $setting_key => $is_enabled): ?>
                                        <div style="display: flex; align-items: center; gap: 4px;">
                                            <span style="color: <?php echo $is_enabled ? '#28a745' : '#6c757d'; ?>; font-weight: bold;">
                                                <?php echo $is_enabled ? '✓' : '✗'; ?>
                                            </span>
                                            <span style="color: <?php echo $is_enabled ? '#28a745' : '#6c757d'; ?>; font-family: monospace;">
                                                <?php echo esc_html(str_replace('_', ' ', ucfirst($setting_key))); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($plugin_info['redis_status']) && $plugin_key === 'redis_cache'): ?>
                            <div style="margin: 8px 0; padding: 8px; background: #e8f4fd; border-radius: 3px; border-left: 3px solid #0073aa;">
                                <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: bold; color: #0073aa;">
                                    <?php _e('Redis Connection Status:', 'holler-cache-control'); ?>
                                </p>
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 11px;">
                                    <span style="color: <?php echo $plugin_info['redis_status'] === 'connected' ? '#28a745' : ($plugin_info['redis_status'] === 'error' ? '#dc3545' : '#ffc107'); ?>; font-weight: bold;">
                                        <?php 
                                        switch($plugin_info['redis_status']) {
                                            case 'connected':
                                                echo '✓ Connected';
                                                break;
                                            case 'disconnected':
                                                echo '✗ Disconnected';
                                                break;
                                            case 'error':
                                                echo '⚠ Error';
                                                break;
                                            default:
                                                echo '❓ Unknown';
                                        }
                                        ?>
                                    </span>
                                    <span style="color: #495057; font-style: italic;">
                                        <?php echo esc_html($plugin_info['redis_info']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="plugin-recommendation" style="margin-top: 12px; padding: 8px; background: #f9f9f9; border-left: 3px solid #0073aa; font-size: 12px;">
                            <strong><?php _e('Recommendation:', 'holler-cache-control'); ?></strong><br>
                            <?php echo esc_html($plugin_info['recommendation']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($conflicting_plugins)): ?>
            <div class="no-conflicts" style="text-align: center; padding: 32px; background: #f0f8f0; border: 1px solid #d4edda; border-radius: 4px; margin-top: 16px;">
                <span style="font-size: 48px;">✅</span>
                <h4 style="color: #155724; margin: 8px 0;"><?php _e('No Cache Plugin Conflicts Detected', 'holler-cache-control'); ?></h4>
                <p style="color: #155724; margin: 0;"><?php _e('Your site is not using any conflicting cache plugins. Holler Cache Control can operate without interference.', 'holler-cache-control'); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="conflict-documentation" style="margin-top: 24px; padding: 16px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
            <h4 style="margin: 0 0 12px 0; color: #856404;"><?php _e('Best Practices for Cache Management', 'holler-cache-control'); ?></h4>
            <ul style="margin: 0; padding-left: 20px; color: #856404;">
                <li><?php _e('For optimal performance, use only one cache management plugin at a time', 'holler-cache-control'); ?></li>
                <li><?php _e('If using Holler Cache Control exclusively, disable auto-purge features in other cache plugins', 'holler-cache-control'); ?></li>
                <li><?php _e('Monitor your site performance after making cache plugin changes', 'holler-cache-control'); ?></li>
                <li><?php _e('Consider deactivating conflicting plugins if they are not essential for your setup', 'holler-cache-control'); ?></li>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Plugin Performance Recommendations -->
<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Plugin Performance Recommendations', 'holler-cache-control'); ?></h3>
        <div class="optimization-score">
            <span class="score-label"><?php _e('Optimization Score:', 'holler-cache-control'); ?></span>
            <span class="score-value <?php echo $optimization_score >= 80 ? 'good' : ($optimization_score >= 60 ? 'warning' : 'poor'); ?>">
                <?php echo $optimization_score; ?>%
            </span>
        </div>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Intelligent analysis of your performance plugins with actionable recommendations for optimal caching and performance.', 'holler-cache-control'); ?></p>
        
        <?php if (!empty($priority_recommendations)): ?>
            <div class="priority-recommendations">
                <h4><?php _e('Priority Actions', 'holler-cache-control'); ?></h4>
                <div class="recommendations-grid">
                    <?php foreach (array_slice($priority_recommendations, 0, 3) as $rec): ?>
                        <div class="recommendation-card <?php echo esc_attr($rec['type']); ?>">
                            <div class="rec-header">
                                <span class="rec-plugin"><?php echo esc_html($rec['plugin']); ?></span>
                                <span class="rec-impact impact-<?php echo esc_attr($rec['impact']); ?>">
                                    <?php echo esc_html(ucfirst($rec['impact'])); ?> Impact
                                </span>
                            </div>
                            <h5><?php echo esc_html($rec['setting']); ?></h5>
                            <p class="rec-reason"><?php echo esc_html($rec['reason']); ?></p>
                            <div class="rec-action">
                                <strong><?php _e('Action:', 'holler-cache-control'); ?></strong>
                                <?php echo esc_html($rec['action']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($plugin_recommendations)): ?>
            <div class="plugin-analysis">
                <h4><?php _e('Detailed Plugin Analysis', 'holler-cache-control'); ?></h4>
                
                <?php foreach ($plugin_recommendations as $plugin_key => $plugin_data): ?>
                    <?php if ($plugin_key === 'other_plugins') continue; // Handle separately ?>
                    <div class="plugin-analysis-card">
                        <div class="plugin-header">
                            <h5><?php echo esc_html($plugin_data['plugin_name']); ?></h5>
                            <div class="plugin-meta">
                                <span class="version"><?php _e('Version:', 'holler-cache-control'); ?> <?php echo esc_html($plugin_data['version']); ?></span>
                                <span class="score <?php echo $plugin_data['optimization_score'] >= 80 ? 'good' : ($plugin_data['optimization_score'] >= 60 ? 'warning' : 'poor'); ?>">
                                    <?php echo $plugin_data['optimization_score']; ?>%
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($plugin_data['recommendations'])): ?>
                            <div class="plugin-recommendations">
                                <?php foreach ($plugin_data['recommendations'] as $rec): ?>
                                    <div class="recommendation-item <?php echo esc_attr($rec['type']); ?>">
                                        <div class="rec-summary">
                                            <span class="rec-setting"><?php echo esc_html($rec['setting']); ?></span>
                                            <span class="rec-current"><?php _e('Current:', 'holler-cache-control'); ?> <?php echo esc_html($rec['current']); ?></span>
                                            <span class="rec-recommended"><?php _e('Recommended:', 'holler-cache-control'); ?> <?php echo esc_html($rec['recommended']); ?></span>
                                        </div>
                                        <p class="rec-reason"><?php echo esc_html($rec['reason']); ?></p>
                                        <div class="rec-action">
                                            <strong><?php _e('How to fix:', 'holler-cache-control'); ?></strong>
                                            <?php echo esc_html($rec['action']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-recommendations">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <p><?php _e('All settings are optimized! No recommendations needed.', 'holler-cache-control'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!empty($plugin_recommendations['other_plugins'])): ?>
                    <div class="other-plugins-analysis">
                        <h5><?php _e('Other Performance Plugins', 'holler-cache-control'); ?></h5>
                        <div class="other-plugins-grid">
                            <?php foreach ($plugin_recommendations['other_plugins'] as $plugin): ?>
                                <div class="other-plugin-item <?php echo esc_attr($plugin['type']); ?>">
                                    <h6><?php echo esc_html($plugin['name']); ?></h6>
                                    <p><?php echo esc_html($plugin['message']); ?></p>
                                    <div class="plugin-recommendation">
                                        <?php echo esc_html($plugin['recommendation']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="no-plugins-detected">
                <div class="holler-notice notice-info">
                    <p><?php _e('No supported performance plugins detected. Consider installing Elementor and/or Perfmatters for enhanced performance optimization.', 'holler-cache-control'); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Cache Path Analysis', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <?php if (!empty($path_report['detected_paths'])): ?>
            <div class="cache-details">
                <h4><?php _e('Detected Cache Paths', 'holler-cache-control'); ?></h4>
                <div style="overflow-x: auto;">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Path', 'holler-cache-control'); ?></th>
                                <th><?php _e('Environment', 'holler-cache-control'); ?></th>
                                <th><?php _e('Priority', 'holler-cache-control'); ?></th>
                                <th><?php _e('Writable', 'holler-cache-control'); ?></th>
                                <th><?php _e('Size', 'holler-cache-control'); ?></th>
                                <th><?php _e('Files', 'holler-cache-control'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($path_report['detected_paths'] as $path_info): ?>
                                <tr>
                                    <td><code><?php echo esc_html($path_info['path']); ?></code></td>
                                    <td><?php echo esc_html($path_info['environment']); ?></td>
                                    <td><?php echo esc_html($path_info['priority']); ?></td>
                                    <td>
                                        <?php if ($path_info['metadata']['writable']): ?>
                                            <span style="color: #46b450;">✓</span>
                                        <?php else: ?>
                                            <span style="color: #dc3232;">✗</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($path_info['metadata']['size_human']); ?></td>
                                    <td><?php echo esc_html($path_info['metadata']['file_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="holler-notice notice-warning">
                <p><?php _e('No cache paths detected automatically. This may indicate a configuration issue.', 'holler-cache-control'); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($path_report['config_paths'])): ?>
            <div class="cache-details">
                <h4><?php _e('Configuration-based Paths', 'holler-cache-control'); ?></h4>
                <div style="overflow-x: auto;">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Path', 'holler-cache-control'); ?></th>
                                <th><?php _e('Source', 'holler-cache-control'); ?></th>
                                <th><?php _e('Priority', 'holler-cache-control'); ?></th>
                                <th><?php _e('Writable', 'holler-cache-control'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($path_report['config_paths'] as $path_info): ?>
                                <tr>
                                    <td><code><?php echo esc_html($path_info['path']); ?></code></td>
                                    <td><?php echo esc_html($path_info['source']); ?></td>
                                    <td><?php echo esc_html($path_info['priority']); ?></td>
                                    <td>
                                        <?php if ($path_info['metadata']['writable']): ?>
                                            <span style="color: #46b450;">✓</span>
                                        <?php else: ?>
                                            <span style="color: #dc3232;">✗</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($path_report['best_path']): ?>
            <div class="holler-notice notice-success">
                <p><strong><?php _e('Best Cache Path:', 'holler-cache-control'); ?></strong> 
                <code><?php echo esc_html($path_report['best_path']['path']); ?></code></p>
                <p><?php _e('Environment:', 'holler-cache-control'); ?> <?php echo esc_html($path_report['best_path']['environment']); ?> | 
                <?php _e('Priority:', 'holler-cache-control'); ?> <?php echo esc_html($path_report['best_path']['priority']); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($path_report['recommendations'])): ?>
<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Recommendations', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <ul>
            <?php foreach ($path_report['recommendations'] as $rec): ?>
                <li class="<?php echo esc_attr($rec['type']); ?>">
                    <?php 
                    switch ($rec['type']) {
                        case 'error':
                            echo '<span style="color: #dc3232;">❌</span> ';
                            break;
                        case 'warning':
                            echo '<span style="color: #ffb900;">⚠️</span> ';
                            break;
                        default:
                            echo '<span style="color: #72aee6;">ℹ️</span> ';
                            break;
                    }
                    echo esc_html($rec['message']); 
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Perfmatters Compatibility Check -->
<?php
// Check Perfmatters compatibility
$perfmatters_active = is_plugin_active('perfmatters/perfmatters.php');
$perfmatters_issues = array();
$perfmatters_recommendations = array();

if ($perfmatters_active) {
    $perfmatters_options = get_option('perfmatters_options', array());
    
    // Check JavaScript delay settings
    $js_delay_enabled = isset($perfmatters_options['assets']['delay_js']) && $perfmatters_options['assets']['delay_js'];
    $js_defer_enabled = isset($perfmatters_options['assets']['defer_js']) && $perfmatters_options['assets']['defer_js'];
    
    if ($js_delay_enabled) {
        $perfmatters_issues[] = array(
            'type' => 'warning',
            'title' => __('JavaScript Delay Enabled', 'holler-cache-control'),
            'message' => __('Perfmatters JavaScript delay is enabled. This may prevent the admin bar "Clear All Caches" button from working on the frontend.', 'holler-cache-control'),
            'action' => __('Add admin bar scripts to Perfmatters JavaScript exclusions', 'holler-cache-control')
        );
    }
    
    if ($js_defer_enabled) {
        $perfmatters_issues[] = array(
            'type' => 'info',
            'title' => __('JavaScript Defer Enabled', 'holler-cache-control'),
            'message' => __('Perfmatters JavaScript defer is enabled. This may delay admin bar functionality.', 'holler-cache-control'),
            'action' => __('Consider excluding admin bar scripts if experiencing issues', 'holler-cache-control')
        );
    }
    
    // Check exclusions
    $js_exclusions = isset($perfmatters_options['assets']['delay_js_exclusions']) ? $perfmatters_options['assets']['delay_js_exclusions'] : '';
    
    // Handle both array and string formats for exclusions
    $has_admin_bar_exclusion = false;
    if (is_array($js_exclusions)) {
        $exclusions_string = implode('\n', $js_exclusions);
        $has_admin_bar_exclusion = strpos($exclusions_string, 'wp-admin-bar') !== false || strpos($exclusions_string, 'holler-cache-control') !== false;
    } elseif (is_string($js_exclusions)) {
        $has_admin_bar_exclusion = strpos($js_exclusions, 'wp-admin-bar') !== false || strpos($js_exclusions, 'holler-cache-control') !== false;
    }
    
    if ($js_delay_enabled && !$has_admin_bar_exclusion) {
        $perfmatters_recommendations[] = array(
            'type' => 'action',
            'title' => __('Add Admin Bar Exclusions', 'holler-cache-control'),
            'message' => __('To ensure admin bar cache clearing works on frontend, add these to Perfmatters JavaScript exclusions:', 'holler-cache-control'),
            'code' => 'wp-admin-bar\nholler-cache-control'
        );
    }
    
    if (empty($perfmatters_issues)) {
        $perfmatters_issues[] = array(
            'type' => 'success',
            'title' => __('Perfmatters Compatible', 'holler-cache-control'),
            'message' => __('No Perfmatters compatibility issues detected.', 'holler-cache-control'),
            'action' => ''
        );
    }
} else {
    $perfmatters_issues[] = array(
        'type' => 'info',
        'title' => __('Perfmatters Not Active', 'holler-cache-control'),
        'message' => __('Perfmatters plugin is not active. No compatibility checks needed.', 'holler-cache-control'),
        'action' => ''
    );
}
?>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Perfmatters Compatibility', 'holler-cache-control'); ?></h3>
        <div class="compatibility-status">
            <?php if ($perfmatters_active): ?>
                <span class="status-badge <?php echo !empty($perfmatters_issues) && $perfmatters_issues[0]['type'] === 'warning' ? 'warning' : 'active'; ?>">
                    <?php echo $perfmatters_active ? __('Active', 'holler-cache-control') : __('Inactive', 'holler-cache-control'); ?>
                </span>
            <?php else: ?>
                <span class="status-badge inactive"><?php _e('Not Active', 'holler-cache-control'); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Perfmatters is a performance optimization plugin commonly used in the Holler Digital stack. JavaScript delay/defer settings can interfere with admin bar functionality.', 'holler-cache-control'); ?></p>
        
        <?php if (!empty($perfmatters_issues)): ?>
            <div class="perfmatters-analysis">
                <h4><?php _e('Compatibility Analysis', 'holler-cache-control'); ?></h4>
                <div class="compatibility-grid">
                    <?php foreach ($perfmatters_issues as $issue): ?>
                        <div class="compatibility-item <?php echo esc_attr($issue['type']); ?>">
                            <div class="compatibility-icon">
                                <?php 
                                switch ($issue['type']) {
                                    case 'warning':
                                        echo '<span style="color: #ffb900;">⚠️</span>';
                                        break;
                                    case 'success':
                                        echo '<span style="color: #46b450;">✅</span>';
                                        break;
                                    case 'info':
                                    default:
                                        echo '<span style="color: #72aee6;">ℹ️</span>';
                                        break;
                                }
                                ?>
                            </div>
                            <div class="compatibility-content">
                                <h5><?php echo esc_html($issue['title']); ?></h5>
                                <p><?php echo esc_html($issue['message']); ?></p>
                                <?php if (!empty($issue['action'])): ?>
                                    <div class="compatibility-action">
                                        <strong><?php _e('Recommended Action:', 'holler-cache-control'); ?></strong>
                                        <?php echo esc_html($issue['action']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($perfmatters_recommendations)): ?>
            <div class="perfmatters-recommendations">
                <h4><?php _e('Configuration Recommendations', 'holler-cache-control'); ?></h4>
                <?php foreach ($perfmatters_recommendations as $rec): ?>
                    <div class="recommendation-card">
                        <div class="recommendation-header">
                            <h5><?php echo esc_html($rec['title']); ?></h5>
                        </div>
                        <div class="recommendation-content">
                            <p><?php echo esc_html($rec['message']); ?></p>
                            <?php if (!empty($rec['code'])): ?>
                                <div class="code-block">
                                    <strong><?php _e('Add to Perfmatters → Assets → JavaScript → Delay JavaScript Execution → Exclusions:', 'holler-cache-control'); ?></strong>
                                    <pre><code><?php echo esc_html($rec['code']); ?></code></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="perfmatters-documentation">
            <h4><?php _e('Documentation', 'holler-cache-control'); ?></h4>
            <div class="doc-grid">
                <div class="doc-item">
                    <h5><?php _e('Common Issue', 'holler-cache-control'); ?></h5>
                    <p><?php _e('Admin bar "Clear All Caches" button not responding on frontend pages.', 'holler-cache-control'); ?></p>
                </div>
                <div class="doc-item">
                    <h5><?php _e('Root Cause', 'holler-cache-control'); ?></h5>
                    <p><?php _e('Perfmatters JavaScript delay/defer prevents admin bar scripts from executing immediately.', 'holler-cache-control'); ?></p>
                </div>
                <div class="doc-item">
                    <h5><?php _e('Solution', 'holler-cache-control'); ?></h5>
                    <p><?php _e('Add admin bar scripts to Perfmatters JavaScript exclusions to ensure immediate execution.', 'holler-cache-control'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('WP-CLI Commands', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Use these WP-CLI commands for advanced diagnostics and management:', 'holler-cache-control'); ?></p>
        
        <div class="cache-details">
            <h4><?php _e('Available Commands', 'holler-cache-control'); ?></h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
                <div>
                    <strong><?php _e('Status Check:', 'holler-cache-control'); ?></strong><br>
                    <code>wp holler-cache status</code>
                    <p><em><?php _e('Check all cache systems status', 'holler-cache-control'); ?></em></p>
                </div>
                
                <div>
                    <strong><?php _e('Full Diagnostics:', 'holler-cache-control'); ?></strong><br>
                    <code>wp holler-cache diagnostics</code>
                    <p><em><?php _e('Comprehensive system diagnostics', 'holler-cache-control'); ?></em></p>
                </div>
                
                <div>
                    <strong><?php _e('Cache Paths Only:', 'holler-cache-control'); ?></strong><br>
                    <code>wp holler-cache diagnostics --paths-only</code>
                    <p><em><?php _e('Show only cache path information', 'holler-cache-control'); ?></em></p>
                </div>
                
                <div>
                    <strong><?php _e('Purge All Caches:', 'holler-cache-control'); ?></strong><br>
                    <code>wp holler-cache purge all</code>
                    <p><em><?php _e('Purge all cache systems', 'holler-cache-control'); ?></em></p>
                </div>
                
                <div>
                    <strong><?php _e('Slack Integration:', 'holler-cache-control'); ?></strong><br>
                    <code>wp holler-cache slack status</code>
                    <p><em><?php _e('Check Slack integration status', 'holler-cache-control'); ?></em></p>
                </div>
                
                <div>
                    <strong><?php _e('JSON Output:', 'holler-cache-control'); ?></strong><br>
                    <code>wp holler-cache diagnostics --format=json</code>
                    <p><em><?php _e('Get diagnostics in JSON format', 'holler-cache-control'); ?></em></p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="cache-status-card full-width">
    <div class="cache-status-header">
        <h3><?php _e('Export Diagnostics', 'holler-cache-control'); ?></h3>
    </div>
    <div class="cache-status-content">
        <p><?php _e('Export system diagnostics for support or troubleshooting purposes.', 'holler-cache-control'); ?></p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 16px;">
            <button type="button" class="button button-secondary" id="export-diagnostics-text">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export as Text', 'holler-cache-control'); ?>
            </button>
            
            <button type="button" class="button button-secondary" id="export-diagnostics-json">
                <span class="dashicons dashicons-media-code"></span>
                <?php _e('Export as JSON', 'holler-cache-control'); ?>
            </button>
            
            <button type="button" class="button button-secondary" id="copy-diagnostics">
                <span class="dashicons dashicons-clipboard"></span>
                <?php _e('Copy to Clipboard', 'holler-cache-control'); ?>
            </button>
        </div>
        
        <div id="diagnostics-export-results" style="margin-top: 16px; display: none;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle diagnostics export
    $('#export-diagnostics-text, #export-diagnostics-json, #copy-diagnostics').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var action = $button.attr('id');
        var format = action.includes('json') ? 'json' : 'text';
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'holler-cache-control')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'holler_export_diagnostics',
                format: format,
                export_type: action.includes('copy') ? 'copy' : 'download',
                nonce: '<?php echo wp_create_nonce('holler_cache_control'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    if (action.includes('copy')) {
                        // Copy to clipboard
                        navigator.clipboard.writeText(response.data.content).then(function() {
                            $('#diagnostics-export-results').html('<div class="holler-notice notice-success"><p><?php echo esc_js(__('Diagnostics copied to clipboard!', 'holler-cache-control')); ?></p></div>').show();
                        });
                    } else {
                        // Download file
                        var blob = new Blob([response.data.content], {type: format === 'json' ? 'application/json' : 'text/plain'});
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'holler-cache-diagnostics.' + (format === 'json' ? 'json' : 'txt');
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                        
                        $('#diagnostics-export-results').html('<div class="holler-notice notice-success"><p><?php echo esc_js(__('Diagnostics exported successfully!', 'holler-cache-control')); ?></p></div>').show();
                    }
                } else {
                    $('#diagnostics-export-results').html('<div class="holler-notice notice-error"><p><?php echo esc_js(__('Export failed: ', 'holler-cache-control')); ?>' + (response.data ? response.data.message : '') + '</p></div>').show();
                }
            },
            error: function() {
                $('#diagnostics-export-results').html('<div class="holler-notice notice-error"><p><?php echo esc_js(__('Export failed. Please try again.', 'holler-cache-control')); ?></p></div>').show();
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>

<style>
/* Cache Plugin Conflict Styles */
.conflict-summary .conflict-count {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.conflict-count.has-conflicts {
    background: #dc3545;
    color: white;
}

.conflict-count.no-conflicts {
    background: #28a745;
    color: white;
}

.conflict-level {
    display: inline-block;
}

.conflict-level.conflict-high {
    background: #dc3545;
    color: white;
}

.conflict-level.conflict-medium {
    background: #ffc107;
    color: #212529;
}

.conflict-level.conflict-low {
    background: #28a745;
    color: white;
}

.conflict-level.conflict-info {
    background: #17a2b8;
    color: white;
}

.plugin-conflict-details .enabled {
    color: #dc3545;
    font-weight: bold;
}

.plugin-conflict-details .disabled {
    color: #28a745;
    font-weight: bold;
}

.conflict-plugin-card {
    transition: box-shadow 0.2s ease;
}

.conflict-plugin-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.plugin-recommendation {
    position: relative;
}

.plugin-recommendation::before {
    content: "💡";
    position: absolute;
    left: -20px;
    top: 8px;
}

.conflict-warnings .holler-notice {
    border-left-width: 4px;
    border-left-style: solid;
    padding: 12px;
    margin: 8px 0;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.conflict-warnings .notice-error {
    border-left-color: #dc3545;
    background: #f8d7da;
}

.conflict-warnings .notice-warning {
    border-left-color: #ffc107;
    background: #fff3cd;
}

.no-conflicts {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.conflict-documentation {
    position: relative;
}

.conflict-documentation::before {
    content: "📚";
    position: absolute;
    left: -30px;
    top: 16px;
    font-size: 20px;
}

@media (max-width: 768px) {
    .plugin-conflicts-grid {
        grid-template-columns: 1fr !important;
    }
    
    .plugin-conflict-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 8px;
    }
}
</style>
