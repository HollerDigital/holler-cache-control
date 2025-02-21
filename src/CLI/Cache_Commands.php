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
use Holler\CacheControl\Admin\Cache\CloudflareAPO;

/**
 * Manage Holler Cache Control from the command line.
 */
class Cache_Commands {

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
            'redis_page' => Nginx::get_status(),
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
     * <type>
     * : Type of cache to purge (all, redis_object, redis_page, cloudflare, cloudflare_apo)
     * ---
     * options:
     *   - all
     *   - redis_object
     *   - redis_page
     *   - cloudflare
     *   - cloudflare_apo
     * ---
     *
     * ## EXAMPLES
     *
     *     # Purge all caches
     *     $ wp holler-cache purge all
     *
     *     # Purge Redis object cache
     *     $ wp holler-cache purge redis_object
     */
    public function purge($args, $assoc_args) {
        $type = $args[0];
        $results = array();

        switch ($type) {
            case 'all':
                $results['redis_object'] = Redis::purge_cache();
                $results['redis_page'] = Nginx::purge_cache();
                $results['cloudflare'] = Cloudflare::purge_cache();
                $results['cloudflare_apo'] = CloudflareAPO::purge_cache();
                break;
            case 'redis_object':
                $results['redis_object'] = Redis::purge_cache();
                break;
            case 'redis_page':
                $results['redis_page'] = Nginx::purge_cache();
                break;
            case 'cloudflare':
                $results['cloudflare'] = Cloudflare::purge_cache();
                break;
            case 'cloudflare_apo':
                $results['cloudflare_apo'] = CloudflareAPO::purge_cache();
                break;
        }

        foreach ($results as $cache => $result) {
            if ($result['success']) {
                \WP_CLI::success("$cache: " . $result['message']);
            } else {
                \WP_CLI::warning("$cache: " . $result['message']);
            }
        }
    }
}

\WP_CLI::add_command('holler-cache', __NAMESPACE__ . '\\Cache_Commands');
