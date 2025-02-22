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
        exec("gp $command 2>&1", $output, $return_var);
        
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
            'cloudflare_apo' => CloudflareAPO::get_status()
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
}

\WP_CLI::add_command('holler-cache', __NAMESPACE__ . '\\Cache_Commands');
