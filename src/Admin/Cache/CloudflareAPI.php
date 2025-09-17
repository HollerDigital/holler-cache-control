<?php
namespace Holler\CacheControl\Admin\Cache;

/**
 * Handles all Cloudflare API operations
 */
class CloudflareAPI {
    private $email;
    private $api_key;
    private $zone_id;
    private $api_endpoint = 'https://api.cloudflare.com/client/v4';

    // Recommended settings
    private $recommended_settings = array(
        'browser_cache_ttl' => 14400, // 4 hours
        'cache_level' => 'aggressive',
        'always_online' => 'on',
        'auto_minify' => array(
            'html' => true,
            'css' => true,
            'js' => true
        ),
        'rocket_loader' => 'on',
        'minify' => array(
            'html' => true,
            'css' => true,
            'js' => true
        ),
        'polish' => 'lossless',
        'brotli' => 'on',
        'early_hints' => 'on'
    );

    public function __construct($email = null, $api_key = null, $zone_id = null) {
        $this->email = $email ?: (defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : get_option('holler_cache_control_cloudflare_email'));
        $this->api_key = $api_key ?: (defined('CLOUDFLARE_API_KEY') ? CLOUDFLARE_API_KEY : get_option('holler_cache_control_cloudflare_api_key'));
        $this->zone_id = $zone_id ?: (defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : get_option('holler_cache_control_cloudflare_zone_id'));
    }

    /**
     * Get request headers for Cloudflare API
     */
    public function get_headers() {
        return array(
            'X-Auth-Email' => $this->email,
            'X-Auth-Key' => $this->api_key,
            'Content-Type' => 'application/json'
        );
    }

    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        $args = array(
            'headers' => $this->get_headers(),
            'method' => $method
        );

        if ($data && $method !== 'GET') {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($this->api_endpoint . $endpoint, $args);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!$body) {
            throw new \Exception(__('Invalid response from Cloudflare.', 'holler-cache-control'));
        }

        return $body;
    }

    /**
     * Test Cloudflare connection
     */
    public function test_connection() {
        try {
            if (empty($this->email) || empty($this->api_key) || empty($this->zone_id)) {
                return array(
                    'success' => false,
                    'message' => __('Missing Cloudflare credentials.', 'holler-cache-control')
                );
            }

            $body = $this->make_request('/zones/' . $this->zone_id);

            if (!$body['success']) {
                $message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control');
                return array(
                    'success' => false,
                    'message' => $message
                );
            }

            return array(
                'success' => true,
                'message' => __('Successfully connected to Cloudflare.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Cloudflare connection test error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get setting
     */
    private function get_setting($setting) {
        try {
            $body = $this->make_request('/zones/' . $this->zone_id . '/settings/' . $setting);
            return $body['success'] ? $body['result'] : null;
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get Cloudflare setting ' . $setting . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update setting
     */
    private function update_setting($setting, $value) {
        try {
            $body = $this->make_request(
                '/zones/' . $this->zone_id . '/settings/' . $setting,
                'PATCH',
                array('value' => $value)
            );
            return $body['success'];
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to update Cloudflare setting ' . $setting . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings
     */
    public function get_all_settings() {
        $settings = array();
        
        // Get development mode
        $dev_mode = $this->get_setting('development_mode');
        if ($dev_mode) {
            $settings['development_mode'] = array(
                'enabled' => $dev_mode['value'] === 'on',
                'message' => $dev_mode['value'] === 'on' ? 
                    __('Development mode is enabled.', 'holler-cache-control') : 
                    __('Development mode is disabled.', 'holler-cache-control')
            );
        }

        // Get cache level
        $cache_level = $this->get_setting('cache_level');
        if ($cache_level) {
            $settings['cache_level'] = array(
                'value' => $cache_level['value'],
                'recommended' => $this->recommended_settings['cache_level'],
                'message' => sprintf(
                    __('Cache level is set to: %s (Recommended: %s)', 'holler-cache-control'),
                    $cache_level['value'],
                    $this->recommended_settings['cache_level']
                )
            );
        }

        // Get browser cache TTL
        $browser_cache = $this->get_setting('browser_cache_ttl');
        if ($browser_cache) {
            $settings['browser_cache'] = array(
                'value' => $browser_cache['value'],
                'recommended' => $this->recommended_settings['browser_cache_ttl'],
                'message' => sprintf(
                    __('Browser cache TTL is set to: %d seconds (Recommended: %d)', 'holler-cache-control'),
                    $browser_cache['value'],
                    $this->recommended_settings['browser_cache_ttl']
                )
            );
        }

        // Get Always Online
        $always_online = $this->get_setting('always_online');
        if ($always_online) {
            $settings['always_online'] = array(
                'value' => $always_online['value'],
                'recommended' => $this->recommended_settings['always_online'],
                'message' => sprintf(
                    __('Always Online is: %s (Recommended: on)', 'holler-cache-control'),
                    $always_online['value']
                )
            );
        }

        // Get Auto Minify
        $auto_minify = $this->get_setting('minify');
        if ($auto_minify) {
            $settings['auto_minify'] = array(
                'value' => $auto_minify['value'],
                'recommended' => $this->recommended_settings['auto_minify'],
                'message' => sprintf(
                    __('Auto Minify - HTML: %s, CSS: %s, JS: %s', 'holler-cache-control'),
                    $auto_minify['value']['html'] ? 'on' : 'off',
                    $auto_minify['value']['css'] ? 'on' : 'off',
                    $auto_minify['value']['js'] ? 'on' : 'off'
                )
            );
        }

        // Get Rocket Loader
        $rocket_loader = $this->get_setting('rocket_loader');
        if ($rocket_loader) {
            $settings['rocket_loader'] = array(
                'value' => $rocket_loader['value'],
                'recommended' => $this->recommended_settings['rocket_loader'],
                'message' => sprintf(
                    __('Rocket Loader is: %s (Recommended: on)', 'holler-cache-control'),
                    $rocket_loader['value']
                )
            );
        }

        // Get Brotli
        $brotli = $this->get_setting('brotli');
        if ($brotli) {
            $settings['brotli'] = array(
                'value' => $brotli['value'],
                'recommended' => $this->recommended_settings['brotli'],
                'message' => sprintf(
                    __('Brotli compression is: %s (Recommended: on)', 'holler-cache-control'),
                    $brotli['value']
                )
            );
        }

        // Get Early Hints
        $early_hints = $this->get_setting('early_hints');
        if ($early_hints) {
            $settings['early_hints'] = array(
                'value' => $early_hints['value'],
                'recommended' => $this->recommended_settings['early_hints'],
                'message' => sprintf(
                    __('Early Hints is: %s (Recommended: on)', 'holler-cache-control'),
                    $early_hints['value']
                )
            );
        }

        return $settings;
    }

    /**
     * Apply recommended settings
     */
    public function apply_recommended_settings() {
        $results = array();
        
        foreach ($this->recommended_settings as $setting => $value) {
            $success = $this->update_setting($setting, $value);
            $results[$setting] = array(
                'success' => $success,
                'message' => $success ? 
                    sprintf(__('Successfully updated %s', 'holler-cache-control'), $setting) :
                    sprintf(__('Failed to update %s', 'holler-cache-control'), $setting)
            );
        }

        return $results;
    }

    /**
     * Get development mode status
     */
    public function get_development_mode() {
        try {
            $response = wp_remote_get($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/development_mode', array(
                'headers' => $this->get_headers()
            ));

            if (is_wp_error($response)) {
                return array('value' => 'off');
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                return array('value' => 'off');
            }

            return array('value' => $body['result']['value']);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Get development mode error: ' . $e->getMessage());
            return array('value' => 'off');
        }
    }

    /**
     * Update development mode setting
     *
     * @param string $value 'on' or 'off'
     * @return array Update result
     */
    public function update_development_mode($value = 'off') {
        try {
            $response = wp_remote_request($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/development_mode', array(
                'method' => 'PATCH',
                'headers' => $this->get_headers(),
                'body' => json_encode(array(
                    'value' => $value
                ))
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => __('Failed to connect to Cloudflare API.', 'holler-cache-control')
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from Cloudflare API.', 'holler-cache-control')
                );
            }

            if ($body['success']) {
                $status_message = $value === 'on' ? 
                    __('Development mode enabled. Cache is bypassed for 3 hours.', 'holler-cache-control') :
                    __('Development mode disabled. Normal caching resumed.', 'holler-cache-control');
                    
                return array(
                    'success' => true,
                    'message' => $status_message,
                    'value' => $value
                );
            } else {
                $error_message = isset($body['errors'][0]['message']) ? 
                    $body['errors'][0]['message'] : 
                    __('Unknown error occurred.', 'holler-cache-control');
                    
                return array(
                    'success' => false,
                    'message' => $error_message
                );
            }
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Update development mode error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Failed to update development mode: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get security settings from Cloudflare
     */
    public function get_security_settings() {
        try {
            $settings = array();
            
            // Get security level
            $security_level = $this->get_security_level();
            $settings['security_level'] = $security_level['value'] ?? 'unknown';
            
            // Get bot fight mode
            $bot_fight = $this->get_bot_fight_mode();
            $settings['bot_fight_mode'] = array(
                'value' => $bot_fight['value'] ?? 'unknown',
                'error' => $bot_fight['error'] ?? null
            );
            
            // Get browser integrity check
            $browser_check = $this->get_browser_integrity_check();
            $settings['browser_check'] = $browser_check['value'] ?? 'unknown';
            
            // Get email obfuscation
            $email_obfuscation = $this->get_email_obfuscation();
            $settings['email_obfuscation'] = $email_obfuscation['value'] ?? 'unknown';
            
            return array(
                'success' => true,
                'settings' => $settings
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Get security settings error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Failed to get security settings: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get security level
     */
    public function get_security_level() {
        try {
            $response = wp_remote_get($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/security_level', array(
                'headers' => $this->get_headers()
            ));

            if (is_wp_error($response)) {
                return array('value' => 'unknown');
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                return array('value' => 'unknown');
            }

            return array('value' => $body['result']['value']);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Get security level error: ' . $e->getMessage());
            return array('value' => 'unknown');
        }
    }

    /**
     * Update security level
     */
    public function update_security_level($level) {
        try {
            $valid_levels = array('essentially_off', 'low', 'medium', 'high', 'under_attack');
            if (!in_array($level, $valid_levels)) {
                return array(
                    'success' => false,
                    'message' => 'Invalid security level'
                );
            }

            $response = wp_remote_request($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/security_level', array(
                'method' => 'PATCH',
                'headers' => $this->get_headers(),
                'body' => json_encode(array(
                    'value' => $level
                ))
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to connect to Cloudflare API'
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                $error_message = 'Unknown error';
                if (isset($body['errors']) && is_array($body['errors']) && !empty($body['errors'])) {
                    $error_message = $body['errors'][0]['message'];
                }
                return array(
                    'success' => false,
                    'message' => $error_message
                );
            }

            return array(
                'success' => true,
                'message' => 'Security level updated successfully',
                'value' => $body['result']['value']
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Update security level error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Failed to update security level: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get bot fight mode status
     */
    public function get_bot_fight_mode() {
        try {
            $response = wp_remote_get($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/bot_fight_mode', array(
                'headers' => $this->get_headers()
            ));

            if (is_wp_error($response)) {
                return array(
                    'value' => 'unknown',
                    'error' => $response->get_error_message()
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success'])) {
                return array(
                    'value' => 'unknown',
                    'error' => __('Invalid response from Cloudflare', 'holler-cache-control')
                );
            }

            if (!$body['success']) {
                $error = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error', 'holler-cache-control');
                $code = isset($body['errors'][0]['code']) ? $body['errors'][0]['code'] : 0;
                
                // Handle feature not enabled error
                if ($code === 1009) {
                    return array(
                        'value' => 'not_available',
                        'error' => __('Bot Fight Mode requires Bot Management to be enabled for this zone', 'holler-cache-control')
                    );
                }
                
                return array(
                    'value' => 'unknown',
                    'error' => $error
                );
            }

            return array('value' => $body['result']['value']);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Get bot fight mode error: ' . $e->getMessage());
            return array(
                'value' => 'unknown',
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Update bot fight mode
     */
    public function update_bot_fight_mode($enabled) {
        try {
            $value = $enabled ? 'on' : 'off';

            $response = wp_remote_request($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/bot_fight_mode', array(
                'method' => 'PATCH',
                'headers' => $this->get_headers(),
                'body' => json_encode(array(
                    'value' => $value
                ))
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to connect to Cloudflare API'
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                $error_message = 'Unknown error';
                if (isset($body['errors']) && is_array($body['errors']) && !empty($body['errors'])) {
                    $error_message = $body['errors'][0]['message'];
                }
                return array(
                    'success' => false,
                    'message' => $error_message
                );
            }

            return array(
                'success' => true,
                'message' => 'Bot fight mode updated successfully',
                'value' => $body['result']['value']
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Update bot fight mode error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Failed to update bot fight mode: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get browser integrity check status
     */
    public function get_browser_integrity_check() {
        try {
            $response = wp_remote_get($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/browser_check', array(
                'headers' => $this->get_headers()
            ));

            if (is_wp_error($response)) {
                return array('value' => 'unknown');
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                return array('value' => 'unknown');
            }

            return array('value' => $body['result']['value']);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Get browser integrity check error: ' . $e->getMessage());
            return array('value' => 'unknown');
        }
    }

    /**
     * Update browser integrity check
     */
    public function update_browser_integrity_check($enabled) {
        try {
            $value = $enabled ? 'on' : 'off';

            $response = wp_remote_request($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/browser_check', array(
                'method' => 'PATCH',
                'headers' => $this->get_headers(),
                'body' => json_encode(array(
                    'value' => $value
                ))
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to connect to Cloudflare API'
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                $error_message = 'Unknown error';
                if (isset($body['errors']) && is_array($body['errors']) && !empty($body['errors'])) {
                    $error_message = $body['errors'][0]['message'];
                }
                return array(
                    'success' => false,
                    'message' => $error_message
                );
            }

            return array(
                'success' => true,
                'message' => 'Browser integrity check updated successfully',
                'value' => $body['result']['value']
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Update browser integrity check error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Failed to update browser integrity check: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get email obfuscation status
     */
    public function get_email_obfuscation() {
        try {
            $response = wp_remote_get($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/email_obfuscation', array(
                'headers' => $this->get_headers()
            ));

            if (is_wp_error($response)) {
                return array('value' => 'unknown');
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                return array('value' => 'unknown');
            }

            return array('value' => $body['result']['value']);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Get email obfuscation error: ' . $e->getMessage());
            return array('value' => 'unknown');
        }
    }

    /**
     * Update email obfuscation
     */
    public function update_email_obfuscation($enabled) {
        try {
            $value = $enabled ? 'on' : 'off';

            $response = wp_remote_request($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/email_obfuscation', array(
                'method' => 'PATCH',
                'headers' => $this->get_headers(),
                'body' => json_encode(array(
                    'value' => $value
                ))
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to connect to Cloudflare API'
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                $error_message = 'Unknown error';
                if (isset($body['errors']) && is_array($body['errors']) && !empty($body['errors'])) {
                    $error_message = $body['errors'][0]['message'];
                }
                return array(
                    'success' => false,
                    'message' => $error_message
                );
            }

            return array(
                'success' => true,
                'message' => 'Email obfuscation updated successfully',
                'value' => $body['result']['value']
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Update email obfuscation error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Failed to update email obfuscation: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get cache level
     */
    public function get_cache_level() {
        try {
            $response = wp_remote_get($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/cache_level', array(
                'headers' => $this->get_headers()
            ));

            if (is_wp_error($response)) {
                return array('value' => 'unknown');
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                return array('value' => 'unknown');
            }

            return array('value' => $body['result']['value']);
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Get cache level error: ' . $e->getMessage());
            return array('value' => 'unknown');
        }
    }

    /**
     * Get browser cache TTL
     */
    public function get_browser_cache_ttl() {
        try {
            $response = wp_remote_get($this->api_endpoint . '/zones/' . $this->zone_id . '/settings/browser_cache_ttl', array(
                'headers' => $this->get_headers()
            ));

            if (is_wp_error($response)) {
                return array('value' => 0);
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                return array('value' => 0);
            }

            return array('value' => intval($body['result']['value']));
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Get browser cache TTL error: ' . $e->getMessage());
            return array('value' => 0);
        }
    }

    /**
     * Update browser cache TTL
     *
     * @param int $ttl TTL in seconds
     * @return array Result of the update
     */
    public function update_browser_cache_ttl($ttl = 14400) {
        try {
            $result = $this->update_setting('browser_cache_ttl', $ttl);
            
            return array(
                'success' => $result,
                'message' => $result ? 
                    sprintf(__('Browser cache TTL updated to %d seconds.', 'holler-cache-control'), $ttl) :
                    __('Failed to update browser cache TTL.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to update browser cache TTL: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Purge Cloudflare cache
     */
    public function purge_cache() {
        try {
            if (empty($this->email) || empty($this->api_key) || empty($this->zone_id)) {
                return array(
                    'success' => false,
                    'message' => __('Missing Cloudflare credentials.', 'holler-cache-control')
                );
            }

            $response = wp_remote_post($this->api_endpoint . '/zones/' . $this->zone_id . '/purge_cache', array(
                'headers' => $this->get_headers(),
                'body' => json_encode(array('purge_everything' => true))
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from Cloudflare.', 'holler-cache-control')
                );
            }

            if (!$body['success']) {
                $message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control');
                return array(
                    'success' => false,
                    'message' => $message
                );
            }

            return array(
                'success' => true,
                'message' => __('Cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Purge cache error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Purge Cloudflare APO cache
     */
    public function purge_apo_cache() {
        try {
            if (empty($this->email) || empty($this->api_key) || empty($this->zone_id)) {
                return array(
                    'success' => false,
                    'message' => __('Missing Cloudflare credentials.', 'holler-cache-control')
                );
            }

            $response = wp_remote_post($this->api_endpoint . '/zones/' . $this->zone_id . '/cache/purge', array(
                'headers' => $this->get_headers(),
                'body' => json_encode(array(
                    'purge_everything' => true,
                    'hosts' => array(parse_url(get_site_url(), PHP_URL_HOST))
                ))
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from Cloudflare.', 'holler-cache-control')
                );
            }

            if (!$body['success']) {
                $message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control');
                return array(
                    'success' => false,
                    'message' => $message
                );
            }

            return array(
                'success' => true,
                'message' => __('Cloudflare APO cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Cloudflare APO purge error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update Always Online setting
     *
     * @param string $value 'on' or 'off'
     * @return array Result of the update
     */
    public function update_always_online($value = 'on') {
        try {
            $result = $this->update_setting('always_online', $value);
            
            return array(
                'success' => $result,
                'message' => $result ? 
                    sprintf(__('Always Online has been turned %s.', 'holler-cache-control'), $value) :
                    __('Failed to update Always Online setting.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to update Always Online setting: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update Rocket Loader setting
     *
     * @param string $value 'on' or 'off'
     * @return array Result of the update
     */
    public function update_rocket_loader($value = 'on') {
        try {
            $result = $this->update_setting('rocket_loader', $value);
            
            return array(
                'success' => $result,
                'message' => $result ? 
                    sprintf(__('Rocket Loader has been turned %s.', 'holler-cache-control'), $value) :
                    __('Failed to update Rocket Loader setting.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to update Rocket Loader setting: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get API endpoint URL
     * 
     * @return string API endpoint URL
     */
    public function get_api_endpoint() {
        return $this->api_endpoint;
    }

    /**
     * Get Elementor Page Rules
     */
    public function get_elementor_page_rules() {
        try {
            $body = $this->make_request('/zones/' . $this->zone_id . '/pagerules');
            if (!$body['success']) {
                return array();
            }

            $elementor_rules = array();
            foreach ($body['result'] as $rule) {
                if (strpos($rule['targets'][0]['constraint']['value'], '*elementor*') !== false ||
                    strpos($rule['targets'][0]['constraint']['value'], '?elementor_library=*') !== false) {
                    $elementor_rules[] = $rule;
                }
            }

            return $elementor_rules;
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get Elementor page rules: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Create Elementor Page Rules
     */
    public function create_elementor_page_rules() {
        try {
            $site_url = get_site_url();
            $rules_to_create = array(
                array(
                    'targets' => array(
                        array(
                            'target' => 'url',
                            'constraint' => array(
                                'operator' => 'matches',
                                'value' => $site_url . '/*elementor*'
                            )
                        )
                    ),
                    'actions' => array(
                        array(
                            'id' => 'rocket_loader',
                            'value' => 'off'
                        )
                    ),
                    'status' => 'active',
                    'priority' => 1
                ),
                array(
                    'targets' => array(
                        array(
                            'target' => 'url',
                            'constraint' => array(
                                'operator' => 'matches',
                                'value' => $site_url . '/?elementor_library=*'
                            )
                        )
                    ),
                    'actions' => array(
                        array(
                            'id' => 'rocket_loader',
                            'value' => 'off'
                        )
                    ),
                    'status' => 'active',
                    'priority' => 2
                )
            );

            $results = array();
            foreach ($rules_to_create as $rule) {
                $body = $this->make_request('/zones/' . $this->zone_id . '/pagerules', 'POST', $rule);
                $results[] = array(
                    'success' => $body['success'],
                    'message' => $body['success'] ? 
                        __('Created page rule for: ', 'holler-cache-control') . $rule['targets'][0]['constraint']['value'] :
                        (isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control'))
                );
            }

            return $results;
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to create Elementor page rules: ' . $e->getMessage());
            return array(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Setup Elementor compatibility
     */
    public function setup_elementor_compatibility() {
        try {
            // First check if rules already exist
            $existing_rules = $this->get_elementor_page_rules();
            if (!empty($existing_rules)) {
                return array(
                    'success' => true,
                    'message' => __('Elementor page rules already exist.', 'holler-cache-control')
                );
            }

            // Create the rules
            $results = $this->create_elementor_page_rules();
            
            // Check if all rules were created successfully
            $all_success = true;
            $messages = array();
            foreach ($results as $result) {
                if (!$result['success']) {
                    $all_success = false;
                }
                $messages[] = $result['message'];
            }

            return array(
                'success' => $all_success,
                'message' => implode("\n", $messages)
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to setup Elementor compatibility: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Create test page rule to disable caching for HTML pages
     */
    public function create_test_page_rule() {
        try {
            $site_url = get_site_url();
            $rule = array(
                'targets' => array(
                    array(
                        'target' => 'url',
                        'constraint' => array(
                            'operator' => 'equals',
                            'value' => rtrim($site_url, '/') . '/'
                        )
                    )
                ),
                'actions' => array(
                    array(
                        'id' => 'cache_level',
                        'value' => 'bypass'
                    )
                ),
                'status' => 'active',
                'priority' => 1
            );

            $body = $this->make_request('/zones/' . $this->zone_id . '/pagerules', 'POST', $rule);
            
            return array(
                'success' => $body['success'],
                'message' => $body['success'] ? 
                    __('Created test page rule to disable caching.', 'holler-cache-control') :
                    (isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control'))
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to create test page rule: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Delete page rule by ID
     */
    public function delete_page_rule($rule_id) {
        try {
            $body = $this->make_request('/zones/' . $this->zone_id . '/pagerules/' . $rule_id, 'DELETE');
            
            return array(
                'success' => $body['success'],
                'message' => $body['success'] ? 
                    __('Page rule deleted.', 'holler-cache-control') :
                    (isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control'))
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to delete page rule: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get all page rules
     */
    public function get_page_rules() {
        try {
            $body = $this->make_request('/zones/' . $this->zone_id . '/pagerules');
            if (!$body['success']) {
                return array();
            }
            return $body['result'];
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Failed to get page rules: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Purge specific URL from Cloudflare cache
     */
    public function purge_url($url) {
        try {
            if (empty($this->email) || empty($this->api_key) || empty($this->zone_id)) {
                return array(
                    'success' => false,
                    'message' => __('Missing Cloudflare credentials.', 'holler-cache-control')
                );
            }

            $response = wp_remote_post($this->api_endpoint . '/zones/' . $this->zone_id . '/purge_cache', array(
                'headers' => $this->get_headers(),
                'body' => json_encode(array(
                    'files' => array($url)
                ))
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from Cloudflare.', 'holler-cache-control')
                );
            }

            if (!$body['success']) {
                $message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error.', 'holler-cache-control');
                return array(
                    'success' => false,
                    'message' => $message
                );
            }

            return array(
                'success' => true,
                'message' => __('URL cache purged successfully.', 'holler-cache-control')
            );
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Purge URL error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}
