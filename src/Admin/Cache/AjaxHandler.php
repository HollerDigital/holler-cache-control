<?php
namespace Holler\CacheControl\Admin\Cache;

/**
 * Handles AJAX requests for cache operations
 */
class AjaxHandler {
    /**
     * Handle cache status AJAX request
     */
    public function handle_cache_status() {
        check_ajax_referer('holler_cache_control_status', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $statuses = array(
            'nginx' => Nginx::get_status(),
            'redis' => Redis::get_status(),
            'cloudflare' => Cloudflare::get_status(),
            'cloudflare_apo' => CloudflareAPO::get_status()
        );

        wp_send_json_success($statuses);
    }

    /**
     * Handle cache purge AJAX request
     */
    public function handle_purge_cache() {
        check_ajax_referer('holler_cache_control_purge_all', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : 'ajax';

        try {
            switch ($type) {
                case 'nginx':
                    $result = Nginx::purge_cache();
                    break;
                case 'redis':
                    $result = Redis::purge_cache();
                    break;
                case 'cloudflare':
                    $result = Cloudflare::purge_cache();
                    break;
                case 'cloudflare_apo':
                    $result = CloudflareAPO::purge_cache();
                    break;
                case 'all':
                    $results = array();
                    $results['nginx'] = Nginx::purge_cache();
                    $results['redis'] = Redis::purge_cache();
                    $results['cloudflare'] = Cloudflare::purge_cache();
                    $results['cloudflare_apo'] = CloudflareAPO::purge_cache();
                    
                    $success = true;
                    $messages = array();
                    foreach ($results as $cache_type => $result) {
                        if (!$result['success']) {
                            $success = false;
                        }
                        $messages[] = $result['message'];
                    }
                    
                    $result = array(
                        'success' => $success,
                        'message' => implode("\n", array_filter($messages))
                    );
                    break;
                default:
                    throw new \Exception("Unknown cache type: {$type}");
            }

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
}
