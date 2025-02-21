<?php
namespace HollerCacheControl\CLI;

use WP_CLI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manage Holler Cache Control caches
 */
class Commands {
    private $tools;

    public function __construct() {
        $this->tools = new \HollerCacheControl\Admin\Tools('holler-cache-control', '1.0.0');
    }

    /**
     * Purge all caches
     *
     * ## OPTIONS
     *
     * ## EXAMPLES
     *
     *     wp holler-cache purge all
     *
     * @when after_wp_load
     */
    public function purge($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify what to purge: all, nginx, redis, cloudflare, cloudflare-apo');
            return;
        }

        $type = $args[0];
        switch ($type) {
            case 'all':
                $result = $this->tools->purge_all_caches_on_update();
                break;
            case 'nginx':
                $result = \HollerCacheControl\Cache\Nginx::purge_cache();
                break;
            case 'redis':
                $result = \HollerCacheControl\Cache\Redis::purge_cache();
                break;
            case 'cloudflare':
                $result = \HollerCacheControl\Cache\Cloudflare::purge_cache();
                break;
            case 'cloudflare-apo':
                $result = \HollerCacheControl\Cache\CloudflareAPO::purge();
                break;
            default:
                WP_CLI::error('Invalid cache type. Use: all, nginx, redis, cloudflare, cloudflare-apo');
                return;
        }

        if ($result['success']) {
            WP_CLI::success('✓ ' . $result['message']);
        } else {
            WP_CLI::error('✗ ' . $result['message']);
        }
    }

    /**
     * Sync browser TTL settings from Cloudflare
     *
     * ## EXAMPLES
     *
     *     wp holler-cache sync-ttl
     *
     * @when after_wp_load
     */
    public function sync_ttl() {
        $result = \HollerCacheControl\Cache\Cloudflare::sync_browser_ttl();
        if ($result['success']) {
            WP_CLI::success('✓ ' . $result['message']);
        } else {
            WP_CLI::error('✗ ' . $result['message']);
        }
    }
}
