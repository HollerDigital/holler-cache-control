=== Holler Cache Control ===
Contributors: hollerdigital
Tags: cache, redis, cloudflare, nginx, gridpane, performance, optimization
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Control Nginx FastCGI Cache, Redis Object Cache, and Cloudflare Cache from the WordPress admin. Designed for GridPane Hosted Sites.

== Description ==

Holler Cache Control is a comprehensive cache management plugin designed specifically for GridPane-hosted WordPress sites. It provides a unified interface to manage multiple cache layers including Nginx FastCGI cache, Redis Object cache, and Cloudflare cache.

= Key Features =

* **Unified Cache Management** - Control all cache layers from a single interface
* **One-Click Cache Purging** - Clear all caches with a single button click
* **Admin Bar Integration** - Quick cache controls directly from the WordPress admin bar
* **Cloudflare Integration** - Full Cloudflare cache and APO management
* **Development Mode Toggle** - Easily enable/disable Cloudflare Development Mode
* **Redis Cache Support** - Complete Redis Object cache integration
* **Nginx FastCGI Cache** - Native support for Nginx page caching
* **Automatic Cache Purging** - Smart cache invalidation on content updates
* **Granular Controls** - Fine-tune which events trigger cache purging
* **GridPane Optimized** - Built specifically for GridPane hosting environment
* **Security First** - Proper nonce verification and capability checks
* **Performance Diagnostics** - Built-in tools to analyze cache performance

= Supported Cache Types =

* **Nginx FastCGI Cache** - Server-level page caching
* **Redis Object Cache** - Database query and object caching
* **Cloudflare Cache** - CDN edge caching
* **Cloudflare APO** - Automatic Platform Optimization
* **WordPress Core Caches** - Transients and object cache

= Perfect For =

* GridPane hosted WordPress sites
* High-performance WordPress installations
* E-commerce sites requiring cache control
* Development and staging environments
* Sites using Elementor, Astra, and Perfmatters

= Automatic Cache Purging =

Configure automatic cache purging for:
* Post/Page updates and publishing
* Menu changes
* Widget updates
* Theme customizations
* Plugin activation/deactivation
* Scheduled daily purging

= Cloudflare Features =

* One-click cache purging
* APO cache management
* Development Mode toggle
* Connection testing
* Credential management via wp-config.php or admin UI
* Real-time status monitoring

= Admin Bar Integration =

* Quick "Clear All Caches" button
* Real-time cache status indicators
* Frontend and backend compatibility
* Dynamic status updates

= Developer Friendly =

* WP-CLI commands for cache management
* Extensive hooks and filters
* Comprehensive logging
* GitHub-based automatic updates
* Well-documented codebase

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/holler-cache-control/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure your cache settings via Settings > Cache Control
4. (Optional) Add Cloudflare credentials to wp-config.php for enhanced security

= Cloudflare Configuration =

Add these constants to your wp-config.php file:

```php
define('CLOUDFLARE_EMAIL', 'your-email@example.com');
define('CLOUDFLARE_API_KEY', 'your-api-key');
define('CLOUDFLARE_ZONE_ID', 'your-zone-id');
```

== Frequently Asked Questions ==

= Is this plugin compatible with GridPane hosting? =

Yes! This plugin is specifically designed and optimized for GridPane-hosted WordPress sites.

= Can I use this plugin on other hosting providers? =

While designed for GridPane, the plugin will work on other hosting providers that support Nginx FastCGI cache and Redis.

= How do I configure Cloudflare integration? =

You can configure Cloudflare credentials either through the admin interface or by adding constants to your wp-config.php file (recommended for security).

= Does this plugin work with Elementor? =

Yes! The plugin includes specific optimizations and cache handling for Elementor page builder.

= What is Development Mode? =

Development Mode temporarily bypasses Cloudflare's cache for 3 hours, making it perfect for development and testing without affecting live site performance.

= Can I schedule automatic cache purging? =

Yes! The plugin includes granular controls for automatic cache purging based on various WordPress events.

== Screenshots ==

1. Main dashboard showing all cache statuses
2. Cloudflare settings and Development Mode toggle
3. Automatic cache purging configuration
4. Admin bar integration with quick controls
5. Diagnostics and performance analysis tools

== Changelog ==

= 1.3.4 - 2025-01-24 =
* Added: Plugin Details Modal with full "View details | Check for updates" functionality
* Added: Comprehensive readme.txt with complete plugin documentation
* Added: Enhanced plugin metadata with GitHub Plugin URI and Update URI
* Added: Plugin details sections for description, installation, FAQ, and changelog
* Enhanced: Plugin header with all necessary metadata for WordPress update system
* Enhanced: PluginUpdater class provides comprehensive plugin information
* Enhanced: Better integration with WordPress plugin management interface

= 1.3.3 - 2025-01-24 =
* Added: Cloudflare Development Mode Toggle with one-click enable/disable
* Added: Real-time status display for development mode
* Added: Interactive toggle button with loading states and visual feedback
* Added: Smart warning notices when development mode is active
* Enhanced: Cloudflare settings page with development mode status
* Enhanced: User experience with smooth transitions and real-time UI updates

= 1.3.2 - 2025-01-24 =
* Fixed: Critical plugin-update-checker fatal error on fresh installs
* Fixed: Graceful degradation when vendor directory is missing
* Enhanced: Error handling and logging for update checker

= 1.3.1 - 2025-01-24 =
* Added: GitHub-based automatic plugin updates
* Added: Plugin update checker integration
* Enhanced: Documentation and changelog
* Fixed: Various minor bugs and improvements

= 1.3.0 - 2025-01-24 =
* Added: Comprehensive admin bar cleanup and cache control
* Added: Frontend admin bar functionality with AJAX support
* Added: Perfmatters compatibility diagnostics and recommendations
* Added: Granular automatic cache purging settings
* Added: Unified tabbed admin interface
* Enhanced: Cache purging logic with event-based controls
* Enhanced: Error handling and user feedback
* Fixed: Settings persistence and form submission issues

= 1.2.0 - 2025-01-23 =
* Added: Elementor-specific cache handling and optimization
* Added: Google Fonts optimization for Elementor
* Added: Astra theme cache integration
* Enhanced: Cache purging with comprehensive WordPress core support
* Enhanced: Performance optimizations

= 1.0.0 - 2025-02-13 =
* Initial release
* Redis Object Cache integration
* Cloudflare cache management
* Admin bar quick actions
* Settings page with UI-based configuration
* One-click cache purging
* Comprehensive documentation

== Upgrade Notice ==

= 1.3.3 =
New Cloudflare Development Mode toggle! Easily enable/disable development mode directly from WordPress admin.

= 1.3.2 =
Critical fix for plugin updates. All users should upgrade immediately.

= 1.3.1 =
Automatic plugin updates now available! Stay up-to-date with the latest features and fixes.

== Support ==

For support, please visit: https://github.com/HollerDigital/holler-cache-control

== Contributing ==

Contributions are welcome! Please visit our GitHub repository: https://github.com/HollerDigital/holler-cache-control
