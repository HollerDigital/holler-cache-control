<?php
/**
 * Load WP CLI functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only load if WP CLI is present
if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'src/CLI/bootstrap.php';
}
