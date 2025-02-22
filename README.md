# Holler Cache Control

A comprehensive cache management solution for WordPress, integrating Redis Object Cache, Nginx Page Cache, and Cloudflare with an easy-to-use interface.

## Features

- Redis Object Cache integration and management
- Nginx Page Cache integration
- Cloudflare cache management with APO support
- Admin bar quick actions for cache purging
- Flexible credential management (config file or UI-based)
- Cache status overview with detailed metrics
- One-click cache purging
- WP-CLI commands for automation
- Slack integration for remote cache management
- Granular visibility controls for plugins and features

## Installation

1. Upload the plugin files to the `/wp-content/plugins/holler-cache-control` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your Cloudflare credentials either through the settings page or via wp-config.php

## Configuration

### Cloudflare Configuration

You can configure your Cloudflare credentials in two ways:

#### Method 1: WordPress Admin Interface

1. Go to Cache Control in the WordPress admin menu
2. Look for the Cloudflare Cache card
3. Enter your Cloudflare credentials:
   - Email
   - API Key
   - Zone ID
4. Save Changes

#### Method 2: Configuration File

Add the following constants to your wp-config.php:

```php
define('CLOUDFLARE_EMAIL', 'your-email@example.com');
define('CLOUDFLARE_API_KEY', 'your-api-key');
define('CLOUDFLARE_ZONE_ID', 'your-zone-id');
```

Note: Constants defined in configuration files will take precedence over values set in the admin interface.

### Slack Integration

To enable Slack integration, add the following to wp-config.php:

```php
define('HOLLER_CACHE_SLACK_TOKEN', 'your-slack-token');
define('HOLLER_CACHE_SLACK_CHANNEL', '#your-channel'); // Optional, default channel for notifications
```

## Usage

### Admin Interface

The plugin adds a Cache Control menu item to your WordPress admin menu with:
- Cache status overview for Redis, Nginx, and Cloudflare
- Detailed metrics for each caching system
- One-click purge buttons for individual or all caches
- Plugin and feature visibility settings

### Admin Bar

Quick access to cache controls from the admin bar:
- Purge All Caches
- Individual purge options for Redis, Nginx, and Cloudflare
- Cache status indicators

### WP-CLI Commands

The plugin provides WP-CLI commands for cache management:

```bash
# Get cache status
wp holler-cache status [--format=<table|json|csv|yaml>]

# Purge specific cache
wp holler-cache purge <type>
# Available types: all, redis_object, nginx, cloudflare, cloudflare_apo

# Examples:
wp holler-cache purge all
wp holler-cache purge redis_object
wp holler-cache status --format=json
```

### Slack Commands

Use the following Slack slash commands to manage cache:

```
/holler-cache                   # Clear cache for default site
/holler-cache example.com      # Clear cache for specific site
/holler-cache help             # Show help message
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Redis server (for Redis Object Cache features)
- Nginx server (for Page Cache features)
- Cloudflare account with API access (for Cloudflare features)

## Support

For support, please visit [https://hollerdigital.com](https://hollerdigital.com) or create an issue in our GitHub repository.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Holler Digital](https://hollerdigital.com)
