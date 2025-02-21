<?php
namespace HollerCacheControl\Cache;

/**
 * Handles Cloudflare API operations
 */
class Cloudflare {
    /**
     * Get Cloudflare API credentials
     * 
     * @return array Array containing email, api_key, zone_id, and valid status
     */
    public static function get_credentials() {
        return static::get_credentials_internal();
    }

    /**
     * Internal method to get Cloudflare credentials
     * 
     * @return array Array containing email, api_key, zone_id, and valid status
     */
    protected static function get_credentials_internal() {
        $credentials = array(
            'api_key' => '',
            'zone_id' => '',
            'email' => ''
        );

        // Try to get from constants first
        if (defined('CLOUDFLARE_API_KEY')) {
            $credentials['api_key'] = CLOUDFLARE_API_KEY;
        } else {
            $credentials['api_key'] = get_option('cloudflare_api_key');
        }

        if (defined('CLOUDFLARE_ZONE_ID')) {
            $credentials['zone_id'] = CLOUDFLARE_ZONE_ID;
        } else {
            $credentials['zone_id'] = get_option('cloudflare_zone_id');
        }

        if (defined('CLOUDFLARE_EMAIL')) {
            $credentials['email'] = CLOUDFLARE_EMAIL;
        } else {
            $credentials['email'] = get_option('cloudflare_email');
        }

        // Debug output for CLI
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::debug('Cloudflare Credentials:');
            \WP_CLI::debug('- Zone ID: ' . (empty($credentials['zone_id']) ? 'Not set' : 'Set'));
            \WP_CLI::debug('- API Key: ' . (empty($credentials['api_key']) ? 'Not set' : 'Set'));
            \WP_CLI::debug('- Email: ' . (empty($credentials['email']) ? 'Not set' : 'Set'));
        }

        // Validate credentials
        $missing = array();
        if (empty($credentials['zone_id'])) {
            $missing[] = 'Zone ID';
        }
        if (empty($credentials['api_key'])) {
            $missing[] = 'API Key';
        }
        if (empty($credentials['email'])) {
            $missing[] = 'Email';
        }

        if (!empty($missing)) {
            if (defined('WP_CLI') && WP_CLI) {
                \WP_CLI::warning('Missing Cloudflare credentials: ' . implode(', ', $missing));
                \WP_CLI::line('Please set them using:');
                \WP_CLI::line('- Constants: CLOUDFLARE_API_KEY, CLOUDFLARE_ZONE_ID, CLOUDFLARE_EMAIL');
                \WP_CLI::line('- Or WordPress options: cloudflare_api_key, cloudflare_zone_id, cloudflare_email');
            }
        }

        $credentials['valid'] = empty($missing);

        return $credentials;
    }

    /**
     * Get Cloudflare API email
     * 
     * @return string
     */
    private static function get_email() {
        return defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : get_option('cloudflare_email');
    }

    /**
     * Get Cloudflare API key
     * 
     * @return string
     */
    private static function get_api_key() {
        return defined('CLOUDFLARE_API_KEY') ? CLOUDFLARE_API_KEY : get_option('cloudflare_api_key');
    }

    /**
     * Check if Cloudflare credentials are set
     * 
     * @return bool True if credentials are set, false otherwise
     */
    public static function are_credentials_set() {
        $credentials = self::get_credentials();
        return $credentials['valid'];
    }

    /**
     * Check if Cloudflare is configured
     */
    public static function is_configured() {
        return self::are_credentials_set();
    }

    /**
     * Get Cloudflare status
     *
     * @return array
     */
    public static function get_status() {
        if (!self::are_credentials_set()) {
            return array(
                'active' => false,
                'details' => __('Not Configured', 'holler-cache-control')
            );
        }

        $credentials = self::get_credentials();
        
        // Check zone status
        $response = wp_remote_get(
            "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}",
            array(
                'headers' => array(
                    'X-Auth-Email' => $credentials['email'],
                    'X-Auth-Key' => $credentials['api_key'],
                    'Content-Type' => 'application/json'
                )
            )
        );

        if (is_wp_error($response)) {
            return array(
                'active' => false,
                'details' => $response->get_error_message()
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || !isset($body['success']) || !$body['success']) {
            return array(
                'active' => false,
                'details' => isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('API Error', 'holler-cache-control')
            );
        }

        // Get development mode status specifically
        $dev_mode_response = wp_remote_get(
            "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/settings/development_mode",
            array(
                'headers' => array(
                    'X-Auth-Email' => $credentials['email'],
                    'X-Auth-Key' => $credentials['api_key'],
                    'Content-Type' => 'application/json'
                )
            )
        );

        $dev_mode = 'Unknown';
        if (!is_wp_error($dev_mode_response)) {
            $dev_mode_body = json_decode(wp_remote_retrieve_body($dev_mode_response), true);
            if (!empty($dev_mode_body) && isset($dev_mode_body['success']) && $dev_mode_body['success']) {
                $dev_mode = $dev_mode_body['result']['value'] === 'off' ? 'Off' : 'On';
            }
        }

        // Get cache level setting
        $cache_response = wp_remote_get(
            "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/settings/cache_level",
            array(
                'headers' => array(
                    'X-Auth-Email' => $credentials['email'],
                    'X-Auth-Key' => $credentials['api_key'],
                    'Content-Type' => 'application/json'
                )
            )
        );

        $cache_level = 'Unknown';
        if (!is_wp_error($cache_response)) {
            $cache_body = json_decode(wp_remote_retrieve_body($cache_response), true);
            if (!empty($cache_body) && isset($cache_body['success']) && $cache_body['success']) {
                $cache_level = ucfirst($cache_body['result']['value']);
            }
        }

        $zone_data = $body['result'];
        $status = array(
            'active' => $zone_data['status'] === 'active',
            'details' => ucfirst($zone_data['status']),
            'dev_mode' => $dev_mode,
            'cache_level' => $cache_level
        );

        return $status;
    }

    /**
     * Get Cloudflare cache status
     */
    public static function get_cache_status() {
        return array(
            'active' => self::is_configured(),
            'message' => self::is_configured() 
                ? __('Cloudflare cache is active', 'holler-cache-control')
                : __('Cloudflare credentials not configured', 'holler-cache-control')
        );
    }

    /**
     * Get zone data from Cloudflare
     * @return array
     */
    private static function get_zone_data() {
        $credentials = self::get_credentials();
        
        if (empty($credentials['api_key']) || empty($credentials['email'])) {
            return array();
        }

        $args = array(
            'headers' => array(
                'X-Auth-Email' => $credentials['email'],
                'X-Auth-Key' => $credentials['api_key'],
                'Content-Type' => 'application/json',
            ),
        );

        // Get zones one page at a time to prevent memory issues
        $page = 1;
        $per_page = 20;
        $zones = array();
        
        do {
            $url = add_query_arg(
                array(
                    'page' => $page,
                    'per_page' => $per_page,
                    'status' => 'active'
                ),
                'https://api.cloudflare.com/client/v4/zones'
            );
            
            $response = wp_remote_get($url, $args);
            
            if (is_wp_error($response)) {
                break;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (empty($data['success']) || empty($data['result'])) {
                break;
            }
            
            // Only store essential zone data
            foreach ($data['result'] as $zone) {
                $zones[] = array(
                    'id' => $zone['id'],
                    'name' => $zone['name'],
                    'status' => $zone['status']
                );
            }
            
            // Store the current page's result count before cleanup
            $current_page_count = count($data['result']);
            
            // Free up memory
            unset($data);
            unset($body);
            
            // Check if we've received fewer results than requested (last page)
            if ($current_page_count < $per_page) {
                break;
            }
            
            $page++;
            
            // Optional: Add a small delay to prevent rate limiting
            usleep(100000); // 100ms delay
            
        } while (true);
        
        return $zones;
    }

    /**
     * Enable Cloudflare proxy for domain
     *
     * @return array
     */
    public static function enable_proxy() {
        $result = array(
            'success' => false,
            'message' => ''
        );

        try {
            $credentials = self::get_credentials();
            if (!$credentials['valid']) {
                throw new \Exception(__('Cloudflare credentials not configured', 'holler-cache-control'));
            }

            // Get the site URL and domain
            $site_url = parse_url(get_site_url(), PHP_URL_HOST);
            
            // First get the DNS record ID
            $response = wp_remote_get(
                "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/dns_records?name={$site_url}",
                array(
                    'headers' => array(
                        'X-Auth-Email' => $credentials['email'],
                        'X-Auth-Key'   => $credentials['api_key'],
                        'Content-Type' => 'application/json'
                    )
                )
            );

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($body) || !isset($body['success']) || !$body['success']) {
                $error_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Failed to get DNS records', 'holler-cache-control');
                throw new \Exception($error_msg . ' Response: ' . wp_remote_retrieve_body($response));
            }

            if (empty($body['result'])) {
                throw new \Exception(__('No DNS records found for domain', 'holler-cache-control'));
            }

            // Update each A and CNAME record to be proxied
            foreach ($body['result'] as $record) {
                if ($record['type'] !== 'A' && $record['type'] !== 'CNAME') {
                    continue;
                }

                // Only keep the required fields
                $update_data = array(
                    'type' => $record['type'],
                    'name' => $record['name'],
                    'content' => $record['content'],
                    'proxied' => true,
                    'ttl' => 1 // Auto TTL
                );

                $update_response = wp_remote_post(
                    "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/dns_records/{$record['id']}",
                    array(
                        'method' => 'PUT',
                        'headers' => array(
                            'X-Auth-Email' => $credentials['email'],
                            'X-Auth-Key'   => $credentials['api_key'],
                            'Content-Type' => 'application/json'
                        ),
                        'body' => json_encode($update_data)
                    )
                );

                if (is_wp_error($update_response)) {
                    throw new \Exception($update_response->get_error_message());
                }

                $update_body = json_decode(wp_remote_retrieve_body($update_response), true);
                if (empty($update_body) || !isset($update_body['success']) || !$update_body['success']) {
                    $error_msg = isset($update_body['errors'][0]['message']) ? $update_body['errors'][0]['message'] : __('Failed to update DNS record', 'holler-cache-control');
                    throw new \Exception($error_msg . ' Response: ' . wp_remote_retrieve_body($update_response));
                }
            }

            $result['success'] = true;
            $result['message'] = __('Cloudflare proxy enabled for domain', 'holler-cache-control');

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Enable APO with WordPress settings
     *
     * @return array
     */
    public static function enable_apo() {
        $result = array(
            'success' => false,
            'message' => ''
        );

        try {
            // First ensure the domain is proxied
            $proxy_result = self::enable_proxy();
            if (!$proxy_result['success']) {
                throw new \Exception($proxy_result['message']);
            }

            // Update zone settings
            $zone_result = self::update_zone_settings();
            if (!$zone_result['success']) {
                throw new \Exception($zone_result['message']);
            }

            // Wait for settings to propagate
            sleep(10);

            $credentials = self::get_credentials();
            if (!$credentials['valid']) {
                throw new \Exception(__('Cloudflare credentials not configured', 'holler-cache-control'));
            }

            // Get the site URL for hostname
            $site_url = parse_url(get_site_url(), PHP_URL_HOST);
            
            // Enable APO with WordPress settings
            $response = wp_remote_post(
                "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/settings/automatic_platform_optimization",
                array(
                    'method' => 'PATCH',
                    'headers' => array(
                        'X-Auth-Email' => $credentials['email'],
                        'X-Auth-Key'   => $credentials['api_key'],
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode(array(
                        'value' => array(
                            'enabled' => true,
                            'cf' => true,
                            'wordpress' => true,
                            'wordpress_plugin' => true,
                            'cache_by_device_type' => true,
                            'cache_by_location' => false,
                            'hostnames' => array($site_url)
                        )
                    ))
                )
            );

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($body) || !isset($body['success']) || !$body['success']) {
                $error_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Failed to enable APO', 'holler-cache-control');
                
                // If we still get the proxy error, try one more time after a longer wait
                if (isset($body['errors'][0]['message']) && strpos($body['errors'][0]['message'], 'proxied through Cloudflare') !== false) {
                    sleep(20);
                    
                    $response = wp_remote_post(
                        "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/settings/automatic_platform_optimization",
                        array(
                            'method' => 'PATCH',
                            'headers' => array(
                                'X-Auth-Email' => $credentials['email'],
                                'X-Auth-Key'   => $credentials['api_key'],
                                'Content-Type' => 'application/json'
                            ),
                            'body' => json_encode(array(
                                'value' => array(
                                    'enabled' => true,
                                    'cf' => true,
                                    'wordpress' => true,
                                    'wordpress_plugin' => true,
                                    'cache_by_device_type' => true,
                                    'cache_by_location' => false,
                                    'hostnames' => array($site_url)
                                )
                            ))
                        )
                    );

                    if (is_wp_error($response)) {
                        throw new \Exception($response->get_error_message());
                    }

                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (empty($body) || !isset($body['success']) || !$body['success']) {
                        $error_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Failed to enable APO', 'holler-cache-control');
                        throw new \Exception($error_msg . ' Response: ' . wp_remote_retrieve_body($response));
                    }
                } else {
                    throw new \Exception($error_msg . ' Response: ' . wp_remote_retrieve_body($response));
                }
            }

            // Save the APO enabled state
            update_option('cloudflare_apo_enabled', true);

            $result['success'] = true;
            $result['message'] = __('APO enabled successfully', 'holler-cache-control');

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Update zone settings for APO
     *
     * @return array
     */
    private static function update_zone_settings() {
        $result = array(
            'success' => false,
            'message' => ''
        );

        try {
            $credentials = self::get_credentials();
            if (!$credentials['valid']) {
                throw new \Exception(__('Cloudflare credentials not configured', 'holler-cache-control'));
            }

            // Settings to update
            $settings = array(
                'ssl' => array(
                    'value' => 'full'
                ),
                'minify' => array(
                    'value' => array(
                        'css' => 'on',
                        'html' => 'on',
                        'js' => 'on'
                    )
                ),
                'browser_cache_ttl' => array(
                    'value' => 14400
                ),
                'always_online' => array(
                    'value' => 'off'
                ),
                'development_mode' => array(
                    'value' => 'off'
                ),
                'http3' => array(
                    'value' => 'on'
                ),
                'zero_rtt' => array(
                    'value' => 'on'
                ),
                'websockets' => array(
                    'value' => 'on'
                ),
                'opportunistic_onion' => array(
                    'value' => 'on'
                ),
                'rocket_loader' => array(
                    'value' => 'off'
                ),
                'security_level' => array(
                    'value' => 'medium'
                ),
                'cache_level' => array(
                    'value' => 'aggressive'
                ),
                'polish' => array(
                    'value' => 'lossless'
                ),
                'brotli' => array(
                    'value' => 'on'
                ),
                'early_hints' => array(
                    'value' => 'on'
                ),
                'automatic_https_rewrites' => array(
                    'value' => 'on'
                ),
                'always_use_https' => array(
                    'value' => 'on'
                ),
                'true_client_ip_header' => array(
                    'value' => 'on'
                ),
                'proxy_read_timeout' => array(
                    'value' => 100
                )
            );

            foreach ($settings as $setting => $config) {
                $response = wp_remote_post(
                    "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/settings/{$setting}",
                    array(
                        'method' => 'PATCH',
                        'headers' => array(
                            'X-Auth-Email' => $credentials['email'],
                            'X-Auth-Key'   => $credentials['api_key'],
                            'Content-Type' => 'application/json'
                        ),
                        'body' => json_encode($config)
                    )
                );

                if (is_wp_error($response)) {
                    continue;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (empty($body) || !isset($body['success']) || !$body['success']) {
                    continue;
                }
            }

            // Update zone settings to enable APO
            $response = wp_remote_post(
                "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/settings/automatic_platform_optimization",
                array(
                    'method' => 'PATCH',
                    'headers' => array(
                        'X-Auth-Email' => $credentials['email'],
                        'X-Auth-Key'   => $credentials['api_key'],
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode(array(
                        'value' => array(
                            'enabled' => true,
                            'cf' => true,
                            'wordpress' => true,
                            'wordpress_plugin' => true,
                            'cache_by_device_type' => true,
                            'cache_by_location' => false
                        )
                    ))
                )
            );

            if (is_wp_error($response)) {
                // Ignore APO errors
            } else {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (empty($body) || !isset($body['success']) || !$body['success']) {
                    // Ignore APO errors
                }
            }

            $result['success'] = true;
            $result['message'] = __('Zone settings updated successfully', 'holler-cache-control');

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Purge Cloudflare cache
     *
     * @return array
     */
    public static function purge_cache() {
        $result = array(
            'success' => false,
            'message' => ''
        );

        try {
            if (!defined('WP_CLI') && !\current_user_can('manage_options')) {
                throw new \Exception(__('You do not have permission to purge cache', 'holler-cache-control'));
            }

            if (!self::is_proxied()) {
                throw new \Exception(__('Website must be proxied through Cloudflare. Please enable Cloudflare proxy (orange cloud) in your DNS settings.', 'holler-cache-control'));
            }

            $credentials = self::get_credentials();
            if (!$credentials['valid']) {
                throw new \Exception(__('Cloudflare credentials not configured', 'holler-cache-control'));
            }

            // Purge cache via API
            $response = \wp_remote_post(
                'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'] . '/purge_cache',
                array(
                    'headers' => array(
                        'X-Auth-Key' => $credentials['api_key'],
                        'X-Auth-Email' => $credentials['email'],
                        'Content-Type' => 'application/json'
                    ),
                    'body' => \json_encode(array(
                        'purge_everything' => true
                    ))
                )
            );

            if (\is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = \json_decode(\wp_remote_retrieve_body($response), true);
            if (!$body || !isset($body['success']) || !$body['success']) {
                $error = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Unknown error', 'holler-cache-control');
                throw new \Exception($error);
            }

            $result['success'] = true;
            $result['message'] = __('Cloudflare cache cleared successfully', 'holler-cache-control');

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check and configure Cloudflare settings
     *
     * @return array
     */
    public static function check_and_configure_settings() {
        $result = array(
            'success' => false,
            'message' => '',
            'settings' => array()
        );

        try {
            $credentials = self::get_credentials();
            if (!$credentials['valid']) {
                throw new \Exception(__('Cloudflare credentials not configured', 'holler-cache-control'));
            }

            // Settings to check and their optimal values
            $settings_to_check = array(
                'browser_cache_ttl' => array(
                    'endpoint' => 'settings/browser_cache_ttl',
                    'optimal_value' => 0, // Respect existing headers
                    'description' => 'Browser Cache TTL'
                ),
                'cache_level' => array(
                    'endpoint' => 'settings/cache_level',
                    'optimal_value' => 'aggressive',
                    'description' => 'Cache Level'
                ),
                'always_online' => array(
                    'endpoint' => 'settings/always_online',
                    'optimal_value' => 'off',
                    'description' => 'Always Online'
                ),
                'development_mode' => array(
                    'endpoint' => 'settings/development_mode',
                    'optimal_value' => 'off',
                    'description' => 'Development Mode'
                ),
                'edge_cache_ttl' => array(
                    'endpoint' => 'settings/edge_cache_ttl',
                    'optimal_value' => 7200, // 2 hours
                    'description' => 'Edge Cache TTL'
                )
            );

            $settings_status = array();

            // Check each setting
            foreach ($settings_to_check as $setting => $config) {
                $response = wp_remote_get(
                    "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/{$config['endpoint']}",
                    array(
                        'headers' => array(
                            'X-Auth-Email' => $credentials['email'],
                            'X-Auth-Key'   => $credentials['api_key'],
                            'Content-Type' => 'application/json'
                        )
                    )
                );

                if (is_wp_error($response)) {
                    $settings_status[$setting] = array(
                        'status' => 'error',
                        'current' => 'unknown',
                        'optimal' => $config['optimal_value'],
                        'message' => $response->get_error_message()
                    );
                    continue;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!$body['success']) {
                    $settings_status[$setting] = array(
                        'status' => 'error',
                        'current' => 'unknown',
                        'optimal' => $config['optimal_value'],
                        'message' => isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Unknown error'
                    );
                    continue;
                }

                $current_value = $body['result']['value'];
                $settings_status[$setting] = array(
                    'status' => ($current_value == $config['optimal_value']) ? 'optimal' : 'suboptimal',
                    'current' => $current_value,
                    'optimal' => $config['optimal_value'],
                    'description' => $config['description'],
                    'message' => ($current_value == $config['optimal_value']) 
                        ? sprintf(__('%s is optimally configured', 'holler-cache-control'), $config['description'])
                        : sprintf(__('%s should be set to %s (currently %s)', 'holler-cache-control'), 
                            $config['description'], 
                            $config['optimal_value'], 
                            $current_value)
                );

                // If setting is not optimal, try to update it
                if ($settings_status[$setting]['status'] === 'suboptimal') {
                    $update_response = wp_remote_post(
                        "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/{$config['endpoint']}",
                        array(
                            'method' => 'PATCH',
                            'headers' => array(
                                'X-Auth-Email' => $credentials['email'],
                                'X-Auth-Key'   => $credentials['api_key'],
                                'Content-Type' => 'application/json'
                            ),
                            'body' => json_encode(array(
                                'value' => $config['optimal_value']
                            ))
                        )
                    );

                    if (!is_wp_error($update_response)) {
                        $update_body = json_decode(wp_remote_retrieve_body($update_response), true);
                        if ($update_body['success']) {
                            $settings_status[$setting]['status'] = 'updated';
                            $settings_status[$setting]['message'] .= ' - Updated successfully';
                        }
                    }
                }
            }

            // Check for Page Rules that might affect caching
            $page_rules_response = wp_remote_get(
                "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/pagerules",
                array(
                    'headers' => array(
                        'X-Auth-Email' => $credentials['email'],
                        'X-Auth-Key'   => $credentials['api_key'],
                        'Content-Type' => 'application/json'
                    )
                )
            );

            if (!is_wp_error($page_rules_response)) {
                $rules_body = json_decode(wp_remote_retrieve_body($page_rules_response), true);
                if ($rules_body['success']) {
                    $cache_affecting_rules = array();
                    foreach ($rules_body['result'] as $rule) {
                        foreach ($rule['actions'] as $action) {
                            if (in_array($action['id'], array('cache_level', 'browser_cache_ttl', 'edge_cache_ttl'))) {
                                $cache_affecting_rules[] = array(
                                    'url' => $rule['targets'][0]['constraint']['value'],
                                    'action' => $action['id'],
                                    'value' => $action['value']
                                );
                            }
                        }
                    }
                    if (!empty($cache_affecting_rules)) {
                        $settings_status['page_rules'] = array(
                            'status' => 'warning',
                            'rules' => $cache_affecting_rules,
                            'message' => __('Found page rules that may affect caching behavior', 'holler-cache-control')
                        );
                    }
                }
            }

            $result['success'] = true;
            $result['settings'] = $settings_status;
            
            // Generate overall message
            $messages = array();
            foreach ($settings_status as $setting => $status) {
                if ($setting !== 'page_rules') {
                    $messages[] = $status['message'];
                }
            }
            if (isset($settings_status['page_rules'])) {
                $messages[] = $settings_status['page_rules']['message'];
            }
            
            $result['message'] = implode("\n", $messages);

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get DNS records
     *
     * @return array
     */
    public static function get_dns_records() {
        $result = array(
            'success' => false,
            'message' => '',
            'records' => array()
        );

        try {
            $credentials = self::get_credentials();
            if (!$credentials['valid']) {
                throw new \Exception(__('Cloudflare credentials not configured', 'holler-cache-control'));
            }

            // Get the site URL and domain
            $site_url = parse_url(get_site_url(), PHP_URL_HOST);
            
            // Get DNS records
            $response = wp_remote_get(
                "https://api.cloudflare.com/client/v4/zones/{$credentials['zone_id']}/dns_records",
                array(
                    'headers' => array(
                        'X-Auth-Email' => $credentials['email'],
                        'X-Auth-Key'   => $credentials['api_key'],
                        'Content-Type' => 'application/json'
                    )
                )
            );

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($body) || !isset($body['success']) || !$body['success']) {
                $error_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : __('Failed to get DNS records', 'holler-cache-control');
                throw new \Exception($error_msg . ' Response: ' . wp_remote_retrieve_body($response));
            }

            $result['success'] = true;
            $result['message'] = __('DNS records retrieved successfully', 'holler-cache-control');
            $result['records'] = $body['result'];

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check if the current request should be cached
     *
     * @return bool
     */
    private static function should_cache_request() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return false;
        }

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!$path) {
            return false;
        }

        // Static content directories
        $cache_dirs = array(
            '/wp-content/uploads/',
            '/wp-content/themes/',
            '/wp-content/plugins/',
            '/wp-content/fonts/',
            '/wp-includes/',
            '/wp-content/uploads/elementor/css/'
        );

        // Static file extensions
        $cache_extensions = array(
            '.woff2',
            '.woff',
            '.ttf',
            '.otf',
            '.eot',
            '.css',
            '.js',
            '.svg',
            '.json',
            '.ico'
        );

        // Check if path starts with any of the cache directories
        foreach ($cache_dirs as $dir) {
            if (strpos($path, $dir) === 0) {
                return true;
            }
        }

        // Check if path ends with any of the cache extensions
        foreach ($cache_extensions as $ext) {
            if (substr($path, -strlen($ext)) === $ext) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initialize cache headers
     */
    public static function init() {
        // Only set headers if this request should be cached
        if (!self::should_cache_request()) {
            return;
        }

        // Set cache control headers for browser caching
        // Note: Cloudflare will ignore these and use its own 1-year TTL
        $cache_time = YEAR_IN_SECONDS;
        header('Cache-Control: public, max-age=' . $cache_time);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_time) . ' GMT');

        // Set vary header to respect device type if APO is enabled
        if (self::is_configured()) {
            header('Vary: Accept-Encoding, User-Agent');
        } else {
            header('Vary: Accept-Encoding');
        }

        // Add stale-while-revalidate and stale-if-error directives for better performance
        header('Cache-Control: public, max-age=' . $cache_time . ', stale-while-revalidate=86400, stale-if-error=604800');
    }

    /**
     * Disable APO caching for the current request
     */
    public static function disable_apo() {
        if (!defined('CLOUDFLARE_APO_BYPASS')) {
            define('CLOUDFLARE_APO_BYPASS', true);
        }
    }

    /**
     * Add cache control headers to Cloudflare API requests
     */
    public static function add_api_headers($args) {
        if (!isset($args['headers'])) {
            $args['headers'] = array();
        }
        
        $args['headers']['Cache-Control'] = 'private, no-store, no-cache, must-revalidate, max-age=0';
        $args['headers']['Pragma'] = 'no-cache';
        
        return $args;
    }

    /**
     * Sync browser TTL settings from Cloudflare
     *
     * @return array Result of the sync operation
     */
    public static function sync_browser_ttl() {
        $result = array(
            'success' => false,
            'message' => ''
        );

        try {
            // Skip permission check if running via CLI
            if (!defined('WP_CLI') && !current_user_can('manage_options')) {
                throw new \Exception(__('You do not have permission to sync settings', 'holler-cache-control'));
            }

            // Get Cloudflare credentials
            $credentials = self::get_credentials();
            if (empty($credentials['zone_id']) || empty($credentials['api_key'])) {
                throw new \Exception(__('Cloudflare credentials not configured', 'holler-cache-control'));
            }

            // Try both authentication methods
            $auth_methods = array(
                // Bearer token method
                array(
                    'Authorization' => 'Bearer ' . $credentials['api_key']
                ),
                // API key method
                array(
                    'X-Auth-Key' => $credentials['api_key'],
                    'X-Auth-Email' => $credentials['email']
                )
            );

            $success = false;
            $last_error = '';

            foreach ($auth_methods as $auth_headers) {
                $response = wp_remote_get(
                    'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'] . '/settings/browser_cache_ttl',
                    array(
                        'headers' => array_merge($auth_headers, array(
                            'Content-Type' => 'application/json'
                        )),
                        'timeout' => 30
                    )
                );

                if (is_wp_error($response)) {
                    $last_error = $response->get_error_message();
                    continue;
                }

                $response_code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if (defined('WP_CLI') && WP_CLI) {
                    \WP_CLI::debug('Cloudflare API Response Code: ' . $response_code);
                    \WP_CLI::debug('Cloudflare API Response: ' . wp_remote_retrieve_body($response));
                }

                if ($response_code === 200 && $body && isset($body['success']) && $body['success']) {
                    $success = true;
                    break;
                }

                $last_error = isset($body['errors'][0]['message']) 
                    ? $body['errors'][0]['message'] 
                    : 'HTTP ' . $response_code;
            }

            if (!$success) {
                throw new \Exception(sprintf(
                    __('Failed to authenticate with Cloudflare: %s', 'holler-cache-control'),
                    $last_error
                ));
            }

            // Get APO settings if enabled
            $apo_response = wp_remote_get(
                'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'] . '/settings/automatic_platform_optimization',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $credentials['api_key'],
                        'Content-Type' => 'application/json'
                    )
                )
            );

            if (!is_wp_error($apo_response)) {
                $apo_body = json_decode(wp_remote_retrieve_body($apo_response), true);
                if ($apo_body && isset($apo_body['success']) && $apo_body['success']) {
                    // If APO is enabled, it might have different cache settings
                    if (isset($apo_body['result']['value']) && $apo_body['result']['value']) {
                        // APO is enabled, use its cache duration if available
                        $apo_ttl = isset($apo_body['result']['ttl']) ? intval($apo_body['result']['ttl']) : null;
                        if ($apo_ttl) {
                            update_option('cloudflare_browser_ttl', $apo_ttl);
                            $result['success'] = true;
                            $result['message'] = sprintf(
                                __('Successfully synced browser TTL from Cloudflare APO: %d seconds', 'holler-cache-control'),
                                $apo_ttl
                            );
                            return $result;
                        }
                    }
                }
            }

            // If no APO TTL, use browser cache TTL
            $ttl = intval($body['result']['value']);
            if ($ttl > 0) {
                update_option('cloudflare_browser_ttl', $ttl);
                $result['success'] = true;
                $result['message'] = sprintf(
                    __('Successfully synced browser TTL from Cloudflare: %d seconds', 'holler-cache-control'),
                    $ttl
                );
            } else {
                throw new \Exception(__('Invalid TTL value received from Cloudflare', 'holler-cache-control'));
            }

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check if site is proxied through Cloudflare
     *
     * @return bool
     */
    protected static function is_proxied() {
        if (isset($_SERVER['HTTP_CF_RAY'])) {
            return true;
        }

        $credentials = self::get_credentials();
        if (!$credentials['valid']) {
            return false;
        }

        $response = \wp_remote_get(
            'https://api.cloudflare.com/client/v4/zones/' . $credentials['zone_id'],
            array(
                'headers' => array(
                    'X-Auth-Key' => $credentials['api_key'],
                    'X-Auth-Email' => $credentials['email'],
                    'Content-Type' => 'application/json'
                )
            )
        );

        if (\is_wp_error($response)) {
            return false;
        }

        $body = \json_decode(\wp_remote_retrieve_body($response), true);
        if (!$body || !isset($body['success']) || !$body['success']) {
            return false;
        }

        return isset($body['result']['status']) && $body['result']['status'] === 'active';
    }
}
