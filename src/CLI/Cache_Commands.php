<?php
/**
 * WP-CLI commands for Holler Cache Control
 *
 * @package HollerCacheControl
 */

namespace Holler\CacheControl\CLI;

use WP_CLI;
use Holler\CacheControl\Admin\Cache\Redis;
use Holler\CacheControl\Admin\Cache\Nginx;
use Holler\CacheControl\Admin\Cache\Cloudflare;
use Holler\CacheControl\Admin\Cache\CloudflareAPI;
use Holler\CacheControl\Admin\Cache\CloudflareAPO;
use Holler\CacheControl\Core\ErrorHandler;
use Holler\CacheControl\Core\CachePathDetector;
use function Holler\CacheControl\{get_slack_integration_status, enable_slack_integration, disable_slack_integration};

/**
 * Manage Holler Cache Control from the command line.
 */
class Cache_Commands {

    /**
     * Run a GridPane CLI command
     */
    private function run_gp_command($command) {
        $output = array();
        $return_var = 0;
        \exec("gp $command 2>&1", $output, $return_var);
        
        return array(
            'success' => $return_var === 0,
            'output' => implode("\n", $output),
            'code' => $return_var
        );
    }

    /**
     * Get cache status
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Format output. Options: table, json, csv, yaml
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     # Get cache status in table format
     *     $ wp holler-cache status
     *
     *     # Get cache status in JSON format
     *     $ wp holler-cache status --format=json
     */
    public function status($args, $assoc_args) {
        $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

        $status = array(
            'redis_object' => Redis::get_status(),
            'nginx' => Nginx::get_status(),
            'cloudflare' => Cloudflare::get_status(),
            'cloudflare_apo' => CloudflareAPO::get_status(),
            'slack_integration' => get_slack_integration_status()
        );

        if ($format === 'table') {
            $items = array();
            foreach ($status as $cache => $info) {
                $items[] = array(
                    'cache' => $cache,
                    'status' => $info['status'],
                    'message' => $info['message']
                );
            }
            \WP_CLI\Utils\format_items($format, $items, array('cache', 'status', 'message'));
        } else {
            \WP_CLI::print_value($status, array('format' => $format));
        }
    }

    /**
     * Purge cache
     *
     * ## OPTIONS
     *
     * [<type>]
     * : Type of cache to purge (all, opcache, redis_object, nginx, gridpane, cloudflare, cloudflare_apo)
     *
     * ## EXAMPLES
     *
     *     # Purge all caches
     *     $ wp holler-cache purge all
     *
     *     # Purge only Redis object cache
     *     $ wp holler-cache purge redis_object
     *
     *     # Purge only Nginx cache
     *     $ wp holler-cache purge nginx
     *
     * @when after_wp_load
     */
    public function purge($args) {
        $type = isset($args[0]) ? $args[0] : 'all';
        $results = array();

        try {
            switch ($type) {
                case 'all':
                    // Track the cache clear event
                    // Tools::track_cache_clear('all', 'cli');

                    // Redis Object Cache
                    if (function_exists('wp_cache_flush')) {
                        wp_cache_flush();
                        $results['redis'] = array(
                            'success' => true,
                            'message' => 'Redis object cache cleared.'
                        );
                    }

                    // Nginx Cache
                    $nginx_result = Nginx::purge_cache();
                    if ($nginx_result['success']) {
                        $results['nginx'] = array(
                            'success' => true,
                            'message' => $nginx_result['message']
                        );
                    }

                    // Cloudflare Cache
                    $cloudflare_result = Cloudflare::purge_cache();
                    if ($cloudflare_result['success']) {
                        $results['cloudflare'] = array(
                            'success' => true,
                            'message' => $cloudflare_result['message']
                        );
                    }

                    // Cloudflare APO Cache
                    $cloudflare_apo_result = CloudflareAPO::purge_cache();
                    if ($cloudflare_apo_result['success']) {
                        $results['cloudflare_apo'] = array(
                            'success' => true,
                            'message' => $cloudflare_apo_result['message']
                        );
                    }
                    break;

                case 'redis_object':
                    if (function_exists('wp_cache_flush')) {
                        wp_cache_flush();
                        $results['redis'] = array(
                            'success' => true,
                            'message' => 'Redis object cache cleared.'
                        );
                    }
                    break;

                case 'nginx':
                    $nginx_result = Nginx::purge_cache();
                    if ($nginx_result['success']) {
                        $results['nginx'] = array(
                            'success' => true,
                            'message' => $nginx_result['message']
                        );
                    }
                    break;

                case 'cloudflare':
                    $cloudflare_result = Cloudflare::purge_cache();
                    if ($cloudflare_result['success']) {
                        $results['cloudflare'] = array(
                            'success' => true,
                            'message' => $cloudflare_result['message']
                        );
                    }
                    break;

                case 'cloudflare_apo':
                    $cloudflare_apo_result = CloudflareAPO::purge_cache();
                    if ($cloudflare_apo_result['success']) {
                        $results['cloudflare_apo'] = array(
                            'success' => true,
                            'message' => $cloudflare_apo_result['message']
                        );
                    }
                    break;

                default:
                    WP_CLI::error("Invalid cache type. Valid types are: all, redis_object, nginx, cloudflare, cloudflare_apo");
            }

            if (!empty($results)) {
                foreach ($results as $cache_type => $result) {
                    if ($result['success']) {
                        WP_CLI::success($result['message']);
                    } else {
                        WP_CLI::warning($result['message']);
                    }
                }
            }

        } catch (\Exception $e) {
            WP_CLI::error("Failed to purge cache: " . $e->getMessage());
        }
    }

    /**
     * Purge specific URL from cache
     *
     * ## OPTIONS
     *
     * <url>
     * : URL to purge from cache
     *
     * ## EXAMPLES
     *
     *     # Purge homepage
     *     $ wp holler-cache purge_url https://example.com/
     */
    public function purge_url($args, $assoc_args) {
        $url = $args[0];
        $cf = new CloudflareAPI();
        $result = $cf->purge_url($url);

        if ($result['success']) {
            \WP_CLI::success($result['message']);
        } else {
            \WP_CLI::error($result['message']);
        }
    }

    /**
     * Setup Elementor compatibility with Cloudflare
     *
     * ## EXAMPLES
     *
     *     # Setup Elementor compatibility
     *     $ wp holler-cache elementor-setup
     */
    public function elementor_setup($args, $assoc_args) {
        $cf = new \Holler\CacheControl\Admin\Cache\CloudflareAPI();
        $result = $cf->setup_elementor_compatibility();

        if ($result['success']) {
            \WP_CLI::success($result['message']);
        } else {
            \WP_CLI::error($result['message']);
        }
    }

    /**
     * Check Elementor page rules status
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Format output. Options: table, json, csv, yaml
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     # Check Elementor page rules
     *     $ wp holler-cache elementor-status
     *
     *     # Check Elementor page rules in JSON format
     *     $ wp holler-cache elementor-status --format=json
     */
    public function elementor_status($args, $assoc_args) {
        $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        
        $cf = new \Holler\CacheControl\Admin\Cache\CloudflareAPI();
        $rules = $cf->get_elementor_page_rules();

        if ($format === 'table') {
            if (empty($rules)) {
                \WP_CLI::warning('No Elementor page rules found.');
                return;
            }

            $items = array();
            foreach ($rules as $rule) {
                $items[] = array(
                    'id' => $rule['id'],
                    'url' => $rule['targets'][0]['constraint']['value'],
                    'status' => $rule['status'],
                    'priority' => $rule['priority']
                );
            }
            \WP_CLI\Utils\format_items($format, $items, array('id', 'url', 'status', 'priority'));
        } else {
            \WP_CLI::print_value($rules, array('format' => $format));
        }
    }

    /**
     * Create test page rule to disable caching
     *
     * ## EXAMPLES
     *
     *     # Create test page rule
     *     $ wp holler-cache create_test_rule
     */
    public function create_test_rule($args, $assoc_args) {
        $cf = new CloudflareAPI();
        $result = $cf->create_test_page_rule();

        if ($result['success']) {
            \WP_CLI::success($result['message']);
        } else {
            \WP_CLI::error($result['message']);
        }
    }

    /**
     * List all page rules
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Format output. Options: table, json, csv, yaml
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     # List all page rules
     *     $ wp holler-cache list_rules
     */
    public function list_rules($args, $assoc_args) {
        $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        
        $cf = new CloudflareAPI();
        $rules = $cf->get_page_rules();

        if ($format === 'table') {
            if (empty($rules)) {
                \WP_CLI::warning('No page rules found.');
                return;
            }

            $items = array();
            foreach ($rules as $rule) {
                $items[] = array(
                    'id' => $rule['id'],
                    'url' => $rule['targets'][0]['constraint']['value'],
                    'status' => $rule['status'],
                    'priority' => $rule['priority']
                );
            }
            \WP_CLI\Utils\format_items($format, $items, array('id', 'url', 'status', 'priority'));
        } else {
            \WP_CLI::print_value($rules, array('format' => $format));
        }
    }

    /**
     * Delete a page rule
     *
     * ## OPTIONS
     *
     * <rule_id>
     * : ID of the page rule to delete
     *
     * ## EXAMPLES
     *
     *     # Delete a page rule
     *     $ wp holler-cache delete_rule <rule_id>
     */
    public function delete_rule($args, $assoc_args) {
        $rule_id = $args[0];
        $cf = new CloudflareAPI();
        $result = $cf->delete_page_rule($rule_id);

        if ($result['success']) {
            \WP_CLI::success($result['message']);
        } else {
            \WP_CLI::error($result['message']);
        }
    }

    /**
     * Manage Slack integration
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform (status, enable, disable)
     *
     * ## EXAMPLES
     *
     *     # Check Slack integration status
     *     $ wp holler-cache slack status
     *
     *     # Enable Slack integration
     *     $ wp holler-cache slack enable
     *
     *     # Disable Slack integration
     *     $ wp holler-cache slack disable
     */
    public function slack($args, $assoc_args) {
        $action = isset($args[0]) ? $args[0] : 'status';

        switch ($action) {
            case 'status':
                $status = get_slack_integration_status();
                \WP_CLI::line(sprintf('Slack Integration Status: %s', $status['status']));
                \WP_CLI::line($status['message']);
                if ($status['enabled'] && !$status['webhook_configured']) {
                    \WP_CLI::warning('Webhook URL not configured. Set holler_cache_control_slack_webhook option.');
                }
                break;

            case 'enable':
                if (enable_slack_integration()) {
                    \WP_CLI::success('Slack integration enabled.');
                    $status = get_slack_integration_status();
                    if (!$status['webhook_configured']) {
                        \WP_CLI::warning('Don\'t forget to configure the webhook URL: wp option update holler_cache_control_slack_webhook "https://hooks.slack.com/..."');
                    }
                } else {
                    \WP_CLI::error('Failed to enable Slack integration.');
                }
                break;

            case 'disable':
                if (disable_slack_integration()) {
                    \WP_CLI::success('Slack integration disabled.');
                } else {
                    \WP_CLI::error('Failed to disable Slack integration.');
                }
                break;

            default:
                \WP_CLI::error('Invalid action. Use: status, enable, or disable');
                break;
        }
    }

    /**
     * Run comprehensive cache diagnostics
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Format output. Options: table, json, csv, yaml
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     * ---
     *
     * [--paths-only]
     * : Show only cache path information
     *
     * [--validate-paths]
     * : Validate all detected cache paths
     *
     * ## EXAMPLES
     *
     *     # Run full diagnostics
     *     $ wp holler-cache diagnostics
     *
     *     # Show only cache paths
     *     $ wp holler-cache diagnostics --paths-only
     *
     *     # Validate cache paths
     *     $ wp holler-cache diagnostics --validate-paths
     *
     *     # Get diagnostics in JSON format
     *     $ wp holler-cache diagnostics --format=json
     */
    public function diagnostics($args, $assoc_args) {
        $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        $paths_only = \WP_CLI\Utils\get_flag_value($assoc_args, 'paths-only', false);
        $validate_paths = \WP_CLI\Utils\get_flag_value($assoc_args, 'validate-paths', false);

        try {
            if ($paths_only) {
                $this->show_cache_paths($format, $validate_paths);
            } else {
                $this->show_full_diagnostics($format, $validate_paths);
            }
        } catch (\Exception $e) {
            \WP_CLI::error('Diagnostics failed: ' . $e->getMessage());
        }
    }

    /**
     * Show cache path information
     */
    private function show_cache_paths($format, $validate = false) {
        \WP_CLI::line('🔍 Cache Path Detection Report');
        \WP_CLI::line('');

        // Get comprehensive cache path report
        $report = CachePathDetector::get_comprehensive_report();

        if ($format === 'json') {
            \WP_CLI::print_value($report, array('format' => 'json'));
            return;
        }

        // Show detected paths
        if (!empty($report['detected_paths'])) {
            \WP_CLI::line('📁 Detected Cache Paths:');
            $path_items = array();
            foreach ($report['detected_paths'] as $path_info) {
                $path_items[] = array(
                    'path' => $path_info['path'],
                    'environment' => $path_info['environment'],
                    'priority' => $path_info['priority'],
                    'writable' => $path_info['metadata']['writable'] ? 'Yes' : 'No',
                    'size' => $path_info['metadata']['size_human'],
                    'files' => $path_info['metadata']['file_count']
                );
            }
            \WP_CLI\Utils\format_items('table', $path_items, array('path', 'environment', 'priority', 'writable', 'size', 'files'));
        } else {
            \WP_CLI::warning('No cache paths detected automatically.');
        }

        // Show config-based paths
        if (!empty($report['config_paths'])) {
            \WP_CLI::line('');
            \WP_CLI::line('⚙️  Configuration-based Paths:');
            $config_items = array();
            foreach ($report['config_paths'] as $path_info) {
                $config_items[] = array(
                    'path' => $path_info['path'],
                    'source' => $path_info['source'],
                    'priority' => $path_info['priority'],
                    'writable' => $path_info['metadata']['writable'] ? 'Yes' : 'No'
                );
            }
            \WP_CLI\Utils\format_items('table', $config_items, array('path', 'source', 'priority', 'writable'));
        }

        // Show best path
        if ($report['best_path']) {
            \WP_CLI::line('');
            \WP_CLI::success('🎯 Best Cache Path: ' . $report['best_path']['path']);
            \WP_CLI::line('   Environment: ' . $report['best_path']['environment']);
            \WP_CLI::line('   Priority: ' . $report['best_path']['priority']);
            \WP_CLI::line('   Writable: ' . ($report['best_path']['metadata']['writable'] ? 'Yes' : 'No'));
        }

        // Show recommendations
        if (!empty($report['recommendations'])) {
            \WP_CLI::line('');
            \WP_CLI::line('💡 Recommendations:');
            foreach ($report['recommendations'] as $rec) {
                switch ($rec['type']) {
                    case 'error':
                        \WP_CLI::error_multi_line(array('   ❌ ' . $rec['message']));
                        break;
                    case 'warning':
                        \WP_CLI::warning('   ⚠️  ' . $rec['message']);
                        break;
                    default:
                        \WP_CLI::line('   ℹ️  ' . $rec['message']);
                        break;
                }
            }
        }
    }

    /**
     * Show full diagnostics information
     */
    private function show_full_diagnostics($format, $validate_paths = false) {
        if ($format === 'json') {
            $diagnostics = array(
                'cache_status' => array(
                    'redis_object' => Redis::get_status(),
                    'nginx' => Nginx::get_status(),
                    'cloudflare' => Cloudflare::get_status(),
                    'cloudflare_apo' => CloudflareAPO::get_status(),
                    'slack_integration' => get_slack_integration_status()
                ),
                'cache_paths' => CachePathDetector::get_comprehensive_report(),
                'system_info' => $this->get_system_diagnostics()
            );
            \WP_CLI::print_value($diagnostics, array('format' => 'json'));
            return;
        }

        \WP_CLI::line('🔧 Holler Cache Control - Full Diagnostics Report');
        \WP_CLI::line('================================================');
        \WP_CLI::line('');

        // Cache Status
        \WP_CLI::line('📊 Cache System Status:');
        $status = array(
            'redis_object' => Redis::get_status(),
            'nginx' => Nginx::get_status(),
            'cloudflare' => Cloudflare::get_status(),
            'cloudflare_apo' => CloudflareAPO::get_status(),
            'slack_integration' => get_slack_integration_status()
        );

        $status_items = array();
        foreach ($status as $cache => $info) {
            $status_items[] = array(
                'system' => $cache,
                'status' => $info['status'],
                'message' => $info['message']
            );
        }
        \WP_CLI\Utils\format_items('table', $status_items, array('system', 'status', 'message'));

        \WP_CLI::line('');
        
        // Cache Paths
        $this->show_cache_paths('table', $validate_paths);

        \WP_CLI::line('');
        
        // System Information
        \WP_CLI::line('🖥️  System Information:');
        $system_info = $this->get_system_diagnostics();
        $system_items = array();
        foreach ($system_info as $key => $value) {
            $system_items[] = array(
                'property' => str_replace('_', ' ', ucfirst($key)),
                'value' => is_array($value) ? implode(', ', $value) : (string) $value
            );
        }
        \WP_CLI\Utils\format_items('table', $system_items, array('property', 'value'));

        \WP_CLI::line('');
        \WP_CLI::success('✅ Diagnostics complete!');
    }

    /**
     * Get system diagnostics information
     */
    private function get_system_diagnostics() {
        return array(
            'plugin_version' => HOLLER_CACHE_CONTROL_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
            'wp_debug' => WP_DEBUG ? 'Enabled' : 'Disabled',
            'wp_cache' => defined('WP_CACHE') && WP_CACHE ? 'Enabled' : 'Disabled',
            'redis_extension' => class_exists('Redis') ? 'Available' : 'Not Available',
            'current_user' => get_current_user(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'gridpane_hook' => has_action('rt_nginx_helper_purge_all') ? 'Available' : 'Not Available'
        );
    }
}

\WP_CLI::add_command('holler-cache', __NAMESPACE__ . '\\Cache_Commands');
