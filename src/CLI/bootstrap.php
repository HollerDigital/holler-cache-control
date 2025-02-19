<?php
if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    // Load the Commands class
    require_once dirname(__FILE__) . '/Commands.php';
    
    // Add the command
    WP_CLI::add_command('holler-cache', new \HollerCacheControl\CLI\Commands());
}
