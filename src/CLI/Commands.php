<?php
namespace HollerCacheControl\CLI;

use WP_CLI;
use Holler\CacheControl\Admin\Tools;
use Holler\CacheControl\Admin\Cache\Nginx;
use Holler\CacheControl\Admin\Cache\Redis;
use Holler\CacheControl\Admin\Cache\Cloudflare;
use Holler\CacheControl\Admin\Cache\CloudflareAPO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manage Holler Cache Control caches
 */
class Commands {
    private $tools;

    public function __construct() {
        $this->tools = new Tools('holler-cache-control', '1.0.0');
    }

    /**
     * Get cache status
     *
     * ## EXAMPLES
     *
     *     wp holler-cache status
     *
     * @when after_wp_load
     */
    public function status($args, $assoc_args) {
        $nginx_status = Nginx::get_status();
        $redis_status = Redis::get_status();
        $cloudflare_status = Cloudflare::get_status();
        $cloudflare_apo_status = CloudflareAPO::get_status();

        WP_CLI::line('Cache Status:');
        WP_CLI::line('------------');
        WP_CLI::line(sprintf('Nginx Cache: %s - %s', $nginx_status['status'], $nginx_status['message']));
        WP_CLI::line(sprintf('Redis Cache: %s - %s', $redis_status['status'], $redis_status['message']));
        WP_CLI::line(sprintf('Cloudflare Cache: %s - %s', $cloudflare_status['status'], $cloudflare_status['message']));
        WP_CLI::line(sprintf('Cloudflare APO: %s - %s', $cloudflare_apo_status['status'], $cloudflare_apo_status['message']));
    }

    /**
     * Purge all caches
     *
     * ## OPTIONS
     * 
     * <type>
     * : Type of cache to purge (all, redis_page, redis_object, cloudflare, cloudflare_apo)
     *
     * ## EXAMPLES
     *
     *     wp holler-cache purge all
     *     wp holler-cache purge redis_page
     *     wp holler-cache purge redis_object
     *     wp holler-cache purge cloudflare
     *     wp holler-cache purge cloudflare_apo
     *
     * @when after_wp_load
     */
    public function purge($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify what to purge: all, redis_page, redis_object, cloudflare, cloudflare_apo');
            return;
        }

        $type = $args[0];
        switch ($type) {
            case 'all':
                $result = $this->tools->purge_all_caches();
                break;
            case 'redis_page':
                $result = Redis::purge_page_cache();
                break;
            case 'redis_object':
                $result = Redis::purge_object_cache();
                break;
            case 'cloudflare':
                $result = Cloudflare::purge_cache();
                break;
            case 'cloudflare_apo':
                $result = CloudflareAPO::purge_cache();
                break;
            default:
                WP_CLI::error('Invalid cache type. Use: all, redis_page, redis_object, cloudflare, cloudflare_apo');
                return;
        }

        if ($result['success']) {
            WP_CLI::success('✓');
            WP_CLI::success($result['message']);
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
        $result = Cloudflare::sync_browser_ttl();
        if ($result['success']) {
            WP_CLI::success('✓');
            WP_CLI::success($result['message']);
        } else {
            WP_CLI::error('✗ ' . $result['message']);
        }
    }
}
