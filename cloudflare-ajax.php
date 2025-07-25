<?php
/**
 * Standalone Cloudflare AJAX Handler
 * Simple, reliable AJAX endpoint for Cloudflare settings check
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register the AJAX handlers
add_action('wp_ajax_cloudflare_simple_check', 'handle_cloudflare_simple_check');
add_action('wp_ajax_cloudflare_load_settings', 'handle_cloudflare_load_settings');
add_action('wp_ajax_cloudflare_update_setting', 'handle_cloudflare_update_setting');
add_action('wp_ajax_cloudflare_update_minify', 'handle_cloudflare_update_minify');
add_action('wp_ajax_cloudflare_update_multiple', 'handle_cloudflare_update_multiple');

function handle_cloudflare_simple_check() {
    // Debug logging
    error_log('Standalone Cloudflare AJAX handler called');
    file_put_contents('/tmp/holler_debug.log', date('Y-m-d H:i:s') . ' - Standalone Cloudflare AJAX handler called' . "\n", FILE_APPEND);
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cloudflare_simple')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    try {
        // Get Cloudflare credentials from WordPress options or constants
        $email = defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : get_option('cloudflare_email', '');
        $api_key = defined('CLOUDFLARE_API_KEY') ? CLOUDFLARE_API_KEY : get_option('cloudflare_api_key', '');
        $zone_id = defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : get_option('cloudflare_zone_id', '');
        
        if (empty($email) || empty($api_key) || empty($zone_id)) {
            wp_send_json_error(array(
                'message' => 'Cloudflare credentials not configured. Please set your API credentials.'
            ));
            return;
        }
        
        // Test Cloudflare API connection
        $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'X-Auth-Email' => $email,
                'X-Auth-Key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Failed to connect to Cloudflare API: ' . $response->get_error_message()
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !$data['success']) {
            $error_msg = isset($data['errors'][0]['message']) ? $data['errors'][0]['message'] : 'Unknown API error';
            wp_send_json_error(array(
                'message' => 'Cloudflare API error: ' . $error_msg
            ));
            return;
        }
        
        // Build success response with details
        $details = array();
        $details[] = '🔗 Successfully connected to Cloudflare API';
        $details[] = '📧 Email: ' . $email;
        $details[] = '🌐 Zone ID: ' . substr($zone_id, 0, 8) . '...';
        
        // Parse settings from response
        if (isset($data['result']) && is_array($data['result'])) {
            foreach ($data['result'] as $setting) {
                if (!isset($setting['id']) || !isset($setting['value'])) continue;
                
                switch ($setting['id']) {
                    case 'development_mode':
                        $dev_mode = $setting['value'] === 'on' ? 'Enabled' : 'Disabled';
                        $details[] = '🔧 Development Mode: ' . $dev_mode;
                        break;
                    case 'cache_level':
                        $details[] = '📈 Cache Level: ' . ucfirst($setting['value']);
                        break;
                    case 'browser_cache_ttl':
                        $details[] = '⏱️ Browser Cache TTL: ' . $setting['value'] . ' seconds';
                        break;
                    case 'always_online':
                        $details[] = '🌐 Always Online: ' . ucfirst($setting['value']);
                        break;
                }
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Cloudflare connection successful!',
            'details' => $details
        ));
        
    } catch (Exception $e) {
        error_log('Cloudflare check error: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => 'Error: ' . $e->getMessage()
        ));
    }
}

/**
 * Load current Cloudflare settings
 */
function handle_cloudflare_load_settings() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cloudflare_settings')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    try {
        $settings = get_cloudflare_zone_settings();
        
        if (!$settings) {
            wp_send_json_error(array('message' => 'Failed to load Cloudflare settings'));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Settings loaded successfully',
            'settings' => $settings
        ));
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}

/**
 * Update individual Cloudflare setting
 */
function handle_cloudflare_update_setting() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cloudflare_settings')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    $setting = sanitize_text_field($_POST['setting']);
    $value = sanitize_text_field($_POST['value']);
    
    try {
        $result = update_cloudflare_setting($setting, $value);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => ucfirst(str_replace('_', ' ', $setting)) . ' updated successfully'
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update ' . $setting));
        }
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}

/**
 * Update minify settings
 */
function handle_cloudflare_update_minify() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cloudflare_settings')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    $settings = $_POST['settings'];
    
    try {
        $minify_value = array(
            'html' => isset($settings['html']) ? (bool)$settings['html'] : false,
            'css' => isset($settings['css']) ? (bool)$settings['css'] : false,
            'js' => isset($settings['js']) ? (bool)$settings['js'] : false
        );
        
        $result = update_cloudflare_setting('auto_minify', $minify_value);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Auto Minify settings updated successfully'
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update Auto Minify settings'));
        }
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}

/**
 * Update multiple Cloudflare settings
 */
function handle_cloudflare_update_multiple() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cloudflare_settings')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    $settings = $_POST['settings'];
    
    try {
        $success_count = 0;
        $total_count = count($settings);
        
        foreach ($settings as $setting_data) {
            $setting = $setting_data['setting'];
            $value = $setting_data['value'];
            
            // Handle minify settings specially
            if (strpos($setting, 'auto_minify_') === 0) {
                continue; // Skip individual minify settings, handle them as a group
            }
            
            if (update_cloudflare_setting($setting, $value)) {
                $success_count++;
            }
        }
        
        // Handle minify settings as a group
        $minify_settings = array();
        foreach ($settings as $setting_data) {
            if ($setting_data['setting'] === 'auto_minify_html') {
                $minify_settings['html'] = (bool)$setting_data['value'];
            } elseif ($setting_data['setting'] === 'auto_minify_css') {
                $minify_settings['css'] = (bool)$setting_data['value'];
            } elseif ($setting_data['setting'] === 'auto_minify_js') {
                $minify_settings['js'] = (bool)$setting_data['value'];
            }
        }
        
        if (!empty($minify_settings)) {
            if (update_cloudflare_setting('auto_minify', $minify_settings)) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success(array(
                'message' => "Successfully updated {$success_count} settings"
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update any settings'));
        }
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
    }
}

/**
 * Get Cloudflare zone settings
 */
function get_cloudflare_zone_settings() {
    // Get credentials
    $email = defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : get_option('cloudflare_email', '');
    $api_key = defined('CLOUDFLARE_API_KEY') ? CLOUDFLARE_API_KEY : get_option('cloudflare_api_key', '');
    $zone_id = defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : get_option('cloudflare_zone_id', '');
    
    if (empty($email) || empty($api_key) || empty($zone_id)) {
        return false;
    }
    
    $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings";
    
    $response = wp_remote_get($url, array(
        'headers' => array(
            'X-Auth-Email' => $email,
            'X-Auth-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data || !$data['success']) {
        return false;
    }
    
    // Parse settings into a more usable format
    $settings = array();
    
    if (isset($data['result']) && is_array($data['result'])) {
        foreach ($data['result'] as $setting) {
            if (isset($setting['id'])) {
                $settings[$setting['id']] = $setting['value'];
            }
        }
    }
    
    return $settings;
}

/**
 * Update individual Cloudflare setting
 */
function update_cloudflare_setting($setting, $value) {
    // Get credentials
    $email = defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : get_option('cloudflare_email', '');
    $api_key = defined('CLOUDFLARE_API_KEY') ? CLOUDFLARE_API_KEY : get_option('cloudflare_api_key', '');
    $zone_id = defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : get_option('cloudflare_zone_id', '');
    
    if (empty($email) || empty($api_key) || empty($zone_id)) {
        return false;
    }
    
    $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/{$setting}";
    
    $data = array('value' => $value);
    
    $response = wp_remote_request($url, array(
        'method' => 'PATCH',
        'headers' => array(
            'X-Auth-Email' => $email,
            'X-Auth-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($data),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    
    return $result && isset($result['success']) && $result['success'];
}
