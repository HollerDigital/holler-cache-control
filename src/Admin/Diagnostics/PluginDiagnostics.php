<?php
/**
 * Plugin Diagnostics - Analyze and recommend settings for performance plugins
 *
 * @package    HollerCacheControl
 * @subpackage HollerCacheControl/admin/diagnostics
 */

namespace Holler\CacheControl\Admin\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class PluginDiagnostics {
    
    /**
     * Get comprehensive plugin diagnostics and recommendations
     */
    public static function get_plugin_recommendations() {
        $recommendations = array();
        
        // Check Elementor
        if (self::is_elementor_active()) {
            $recommendations['elementor'] = self::analyze_elementor_settings();
        }
        
        // Check Perfmatters
        if (self::is_perfmatters_active()) {
            $recommendations['perfmatters'] = self::analyze_perfmatters_settings();
        }
        
        // Check other common performance plugins
        $recommendations['other_plugins'] = self::analyze_other_performance_plugins();
        
        return $recommendations;
    }
    
    /**
     * Check if Elementor is active
     */
    private static function is_elementor_active() {
        return defined('ELEMENTOR_VERSION') || is_plugin_active('elementor/elementor.php');
    }
    
    /**
     * Check if Perfmatters is active
     */
    private static function is_perfmatters_active() {
        return is_plugin_active('perfmatters/perfmatters.php') || class_exists('Perfmatters\Config');
    }
    
    /**
     * Analyze Elementor settings and provide recommendations
     */
    private static function analyze_elementor_settings() {
        $analysis = array(
            'plugin_name' => 'Elementor',
            'version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'Unknown',
            'status' => 'active',
            'recommendations' => array(),
            'current_settings' => array(),
            'optimization_score' => 100
        );
        
        // Get Elementor options
        $elementor_options = get_option('elementor_general_settings', array());
        $elementor_experiments = get_option('elementor_experiments', array());
        
        // Check CSS Print Method
        $css_print_method = isset($elementor_options['css_print_method']) ? $elementor_options['css_print_method'] : 'external';
        $analysis['current_settings']['css_print_method'] = $css_print_method;
        
        if ($css_print_method !== 'external') {
            $analysis['recommendations'][] = array(
                'type' => 'warning',
                'setting' => 'CSS Print Method',
                'current' => ucfirst($css_print_method),
                'recommended' => 'External File',
                'reason' => 'External CSS files can be cached by browsers and CDNs, improving performance.',
                'impact' => 'medium',
                'action' => 'Go to Elementor > Settings > Advanced > CSS Print Method and select "External File"'
            );
            $analysis['optimization_score'] -= 15;
        }
        
        // Check Google Fonts loading
        $google_fonts = isset($elementor_options['google_font']) ? $elementor_options['google_font'] : 'yes';
        $analysis['current_settings']['google_fonts'] = $google_fonts;
        
        if ($google_fonts === 'yes') {
            $analysis['recommendations'][] = array(
                'type' => 'info',
                'setting' => 'Google Fonts',
                'current' => 'Enabled',
                'recommended' => 'Consider local hosting',
                'reason' => 'Local font hosting eliminates external requests and improves GDPR compliance.',
                'impact' => 'low',
                'action' => 'Consider using a plugin like OMGF or Perfmatters to host Google Fonts locally'
            );
            $analysis['optimization_score'] -= 5;
        }
        
        // Check Font Display setting
        $font_display = isset($elementor_options['font_display']) ? $elementor_options['font_display'] : 'auto';
        $analysis['current_settings']['font_display'] = $font_display;
        
        if ($font_display !== 'swap') {
            $analysis['recommendations'][] = array(
                'type' => 'warning',
                'setting' => 'Font Display',
                'current' => ucfirst($font_display),
                'recommended' => 'Swap',
                'reason' => 'Font-display: swap prevents invisible text during font load and improves perceived performance.',
                'impact' => 'medium',
                'action' => 'Go to Elementor > Settings > Advanced > Font Display and select "Swap"'
            );
            $analysis['optimization_score'] -= 10;
        }
        
        // Check Improved Asset Loading experiment
        $improved_assets = isset($elementor_experiments['e_optimized_assets_loading']) ? $elementor_experiments['e_optimized_assets_loading'] : 'inactive';
        $analysis['current_settings']['improved_asset_loading'] = $improved_assets;
        
        if ($improved_assets !== 'active') {
            $analysis['recommendations'][] = array(
                'type' => 'success',
                'setting' => 'Improved Asset Loading',
                'current' => 'Disabled',
                'recommended' => 'Enable',
                'reason' => 'Reduces CSS and JS files loaded on pages, improving performance.',
                'impact' => 'high',
                'action' => 'Go to Elementor > Settings > Features > Experiments and enable "Improved Asset Loading"'
            );
            $analysis['optimization_score'] -= 20;
        }
        
        // Check Improved CSS Loading experiment
        $improved_css = isset($elementor_experiments['e_optimized_css_loading']) ? $elementor_experiments['e_optimized_css_loading'] : 'inactive';
        $analysis['current_settings']['improved_css_loading'] = $improved_css;
        
        if ($improved_css !== 'active') {
            $analysis['recommendations'][] = array(
                'type' => 'success',
                'setting' => 'Improved CSS Loading',
                'current' => 'Disabled',
                'recommended' => 'Enable',
                'reason' => 'Loads only necessary CSS for each page, reducing file sizes.',
                'impact' => 'high',
                'action' => 'Go to Elementor > Settings > Features > Experiments and enable "Improved CSS Loading"'
            );
            $analysis['optimization_score'] -= 20;
        }
        
        return $analysis;
    }
    
    /**
     * Analyze Perfmatters settings and provide recommendations
     */
    private static function analyze_perfmatters_settings() {
        $analysis = array(
            'plugin_name' => 'Perfmatters',
            'version' => 'Unknown',
            'status' => 'active',
            'recommendations' => array(),
            'current_settings' => array(),
            'optimization_score' => 100
        );
        
        // Get Perfmatters options
        $perfmatters_options = get_option('perfmatters_options', array());
        
        // Check if we have Redis/Object Cache enabled
        $redis_active = class_exists('Redis') && wp_using_ext_object_cache();
        
        // Check Disable Emojis
        $disable_emojis = isset($perfmatters_options['disable_emojis']) ? $perfmatters_options['disable_emojis'] : false;
        $analysis['current_settings']['disable_emojis'] = $disable_emojis;
        
        if (!$disable_emojis) {
            $analysis['recommendations'][] = array(
                'type' => 'success',
                'setting' => 'Disable Emojis',
                'current' => 'Enabled',
                'recommended' => 'Disable',
                'reason' => 'Removes unnecessary emoji scripts and styles, reducing HTTP requests.',
                'impact' => 'low',
                'action' => 'Enable "Disable Emojis" in Perfmatters > Options > Assets'
            );
            $analysis['optimization_score'] -= 5;
        }
        
        // Check Disable Embeds
        $disable_embeds = isset($perfmatters_options['disable_embeds']) ? $perfmatters_options['disable_embeds'] : false;
        $analysis['current_settings']['disable_embeds'] = $disable_embeds;
        
        if (!$disable_embeds) {
            $analysis['recommendations'][] = array(
                'type' => 'info',
                'setting' => 'Disable Embeds',
                'current' => 'Enabled',
                'recommended' => 'Consider disabling',
                'reason' => 'Removes WordPress embed functionality if not needed, reducing script load.',
                'impact' => 'low',
                'action' => 'Enable "Disable Embeds" in Perfmatters > Options > Assets if you don\'t use WordPress embeds'
            );
            $analysis['optimization_score'] -= 3;
        }
        
        // Check Lazy Loading
        $lazy_loading = isset($perfmatters_options['lazy_loading']) ? $perfmatters_options['lazy_loading'] : false;
        $analysis['current_settings']['lazy_loading'] = $lazy_loading;
        
        if (!$lazy_loading) {
            $analysis['recommendations'][] = array(
                'type' => 'warning',
                'setting' => 'Lazy Loading',
                'current' => 'Disabled',
                'recommended' => 'Enable',
                'reason' => 'Defers loading of images until they\'re needed, improving initial page load times.',
                'impact' => 'high',
                'action' => 'Enable "Lazy Loading" in Perfmatters > Options > Lazy Loading'
            );
            $analysis['optimization_score'] -= 15;
        }
        
        // Check DNS Prefetch
        $dns_prefetch = isset($perfmatters_options['dns_prefetch']) ? $perfmatters_options['dns_prefetch'] : array();
        $analysis['current_settings']['dns_prefetch_count'] = is_array($dns_prefetch) ? count($dns_prefetch) : 0;
        
        if (empty($dns_prefetch)) {
            $analysis['recommendations'][] = array(
                'type' => 'info',
                'setting' => 'DNS Prefetch',
                'current' => 'No domains configured',
                'recommended' => 'Add external domains',
                'reason' => 'Resolves DNS for external domains early, reducing connection time.',
                'impact' => 'low',
                'action' => 'Add external domains (fonts.googleapis.com, etc.) in Perfmatters > Options > Preloading > DNS Prefetch'
            );
            $analysis['optimization_score'] -= 5;
        }
        
        // Check if caching recommendations align with our cache setup
        if ($redis_active) {
            $analysis['recommendations'][] = array(
                'type' => 'success',
                'setting' => 'Object Cache Integration',
                'current' => 'Redis detected',
                'recommended' => 'Optimized',
                'reason' => 'Your Redis object cache works perfectly with Perfmatters optimizations.',
                'impact' => 'positive',
                'action' => 'No action needed - your caching setup is optimal'
            );
        }
        
        return $analysis;
    }
    
    /**
     * Analyze other performance plugins
     */
    private static function analyze_other_performance_plugins() {
        $plugins = array();
        
        // Check for conflicting caching plugins
        $caching_plugins = array(
            'wp-rocket/wp-rocket.php' => 'WP Rocket',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
            'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
            'cache-enabler/cache-enabler.php' => 'Cache Enabler'
        );
        
        foreach ($caching_plugins as $plugin_path => $plugin_name) {
            if (is_plugin_active($plugin_path)) {
                $plugins[] = array(
                    'name' => $plugin_name,
                    'type' => 'warning',
                    'message' => 'Potential caching conflict detected',
                    'recommendation' => 'Consider disabling page caching in ' . $plugin_name . ' since you\'re using GridPane\'s Nginx cache',
                    'impact' => 'medium'
                );
            }
        }
        
        // Check for optimization plugins
        $optimization_plugins = array(
            'autoptimize/autoptimize.php' => 'Autoptimize',
            'wp-optimize/wp-optimize.php' => 'WP-Optimize',
            'shortpixel-image-optimiser/wp-shortpixel.php' => 'ShortPixel'
        );
        
        foreach ($optimization_plugins as $plugin_path => $plugin_name) {
            if (is_plugin_active($plugin_path)) {
                $plugins[] = array(
                    'name' => $plugin_name,
                    'type' => 'success',
                    'message' => 'Compatible optimization plugin detected',
                    'recommendation' => $plugin_name . ' works well with your current caching setup',
                    'impact' => 'positive'
                );
            }
        }
        
        return $plugins;
    }
    
    /**
     * Get overall optimization score
     */
    public static function get_optimization_score($recommendations) {
        $total_score = 0;
        $plugin_count = 0;
        
        foreach ($recommendations as $plugin => $data) {
            if (isset($data['optimization_score']) && is_numeric($data['optimization_score'])) {
                $total_score += $data['optimization_score'];
                $plugin_count++;
            }
        }
        
        return $plugin_count > 0 ? round($total_score / $plugin_count) : 100;
    }
    
    /**
     * Get priority recommendations (high impact items)
     */
    public static function get_priority_recommendations($recommendations) {
        $priority = array();
        
        foreach ($recommendations as $plugin => $data) {
            if (isset($data['recommendations'])) {
                foreach ($data['recommendations'] as $rec) {
                    if (isset($rec['impact']) && in_array($rec['impact'], array('high', 'medium'))) {
                        $priority[] = array_merge($rec, array('plugin' => $data['plugin_name']));
                    }
                }
            }
        }
        
        // Sort by impact (high first)
        usort($priority, function($a, $b) {
            $impact_order = array('high' => 3, 'medium' => 2, 'low' => 1);
            return ($impact_order[$b['impact']] ?? 0) - ($impact_order[$a['impact']] ?? 0);
        });
        
        return $priority;
    }
}
