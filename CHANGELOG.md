# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.8] - 2025-01-24

### Added
- **Smart Auto-Purge Detection**: Implemented intelligent detection to prevent cache purging during page builder editing sessions
- **Elementor Compatibility**: Added comprehensive support for Elementor editing without AJAX timeouts or 504 Gateway errors
- **Multi-Page Builder Support**: Extended compatibility to Divi, Beaver Builder, Visual Composer, and Fusion Builder
- **WordPress Block Editor Support**: Enhanced compatibility with Gutenberg auto-saves and draft operations

### Fixed
- **Critical AJAX Timeout Issue**: Resolved 504 Gateway Timeout errors during Elementor editing sessions
- **Page Builder Conflicts**: Eliminated cache purging interference with live page builder editing
- **Auto-Save Interruptions**: Prevented cache operations from blocking editor auto-save functionality
- **Performance Bottlenecks**: Reduced server load during intensive editing sessions

### Changed
- **Auto-Purge Logic**: All automatic cache purging hooks now use smart detection to avoid editor conflicts
- **Hook Implementation**: Updated `save_post`, `delete_post`, and other content hooks to use `purge_all_caches_with_detection()`
- **Logging Enhancement**: Added debug logging when auto-purge is skipped during editing sessions

### Technical Details
- Added `should_skip_auto_purge()` method with detection for:
  - Elementor AJAX actions (`elementor`, `elementor_ajax`, `elementor_save_builder_content`)
  - WordPress block editor operations (`heartbeat`, `autosave`, `gutenberg`)
  - Other page builders (`divi`, `beaver`, `vc_`, `fusion`)
  - Post revisions, auto-drafts, and preview requests
  - Draft saves vs. published content updates
- Added `purge_all_caches_with_detection()` wrapper method for intelligent cache purging
- Updated all auto-purge hooks to prevent conflicts while maintaining cache freshness
- Maintains backward compatibility with existing manual purge functionality

This release resolves the critical compatibility issue between the cache plugin and modern page builders, ensuring smooth editing experience while maintaining effective cache management.

## [1.3.7] - 2025-01-24

### Added
- **Branded Plugin Details Modal**: Integrated official Holler Digital logo icons and banners into the plugin update checker modal
- **Professional Branding**: Plugin details modal now displays consistent Holler Digital branding matching the Holler Elementor plugin
- **Logo Assets**: Added high-resolution Holler logo icons (128x128 and 256x256) for professional appearance in WordPress admin

### Changed
- **Plugin Updater**: Enhanced PluginUpdater class to use local Holler logo assets instead of placeholder WordPress.org URLs
- **Visual Consistency**: Plugin details modal now matches the branded experience across all Holler Digital plugins

### Technical Details
- Copied official Holler logo assets from Holler Elementor plugin for brand consistency
- Updated `add_icons_to_update_info()` method to serve logos via `plugins_url()` with proper asset paths
- Removed placeholder banner URLs and consolidated icon definitions for cleaner code
- Icons are served in both 1x and 2x resolutions for high-DPI display support

## [1.3.6] - 2025-01-24

### Added
- **Cloudflare Security Tab**: New dedicated Security tab for comprehensive Cloudflare security management
- **Security Level Control**: Full security level management (Essentially Off, Low, Medium, High, I'm Under Attack!)
- **Bot Fight Mode Toggle**: One-click enable/disable bot protection with real-time status
- **Browser Integrity Check**: Toggle browser integrity checking to block malicious requests
- **Email Obfuscation**: Enable/disable email address protection from bots and scrapers
- **Security Diagnostics**: Real-time security status overview with visual indicators
- **Security Recommendations**: Intelligent recommendations based on current security configuration
- **Smart Tab Visibility**: Security tab only appears when Cloudflare credentials are configured

### Enhanced
- Complete Cloudflare API integration for all security settings with proper error handling
- AJAX-powered security settings updates without page reload
- Professional security-focused UI with status badges and recommendations
- Comprehensive nonce verification and capability checks for all security operations
- Real-time security status display with color-coded indicators
- Detailed security diagnostics with actionable recommendations

### Technical
- Added comprehensive CloudflareAPI security methods (get/update for all settings)
- Enhanced main Cloudflare class with security wrapper methods
- New AJAX handler for security settings with proper validation
- Security tab view with modern card-based layout and responsive design
- Integrated security status tracking and recommendation engine

## [1.3.5] - 2025-01-24

### Fixed
- **CRITICAL**: Fixed "View details | Check for updates" functionality in WordPress plugins page
- Replaced plugin-update-checker library v5.5 with proven working v4.11 from Holler Elementor
- Plugin now displays proper "View details | Check for updates" links instead of "Visit plugin site"
- Fixed compatibility issues with WordPress plugin update system
- Resolved plugin details modal not appearing correctly

### Enhanced
- Plugin update system now matches the working functionality of Holler Elementor plugin
- Improved WordPress plugin management interface integration
- Better plugin discovery and update experience for users
- Enhanced compatibility with WordPress core plugin update mechanisms

## [1.3.4] - 2025-01-24

### Added
- **Plugin Details Modal**: Full "View details | Check for updates" functionality now available in WordPress plugins page
- **Comprehensive readme.txt**: Complete plugin documentation with features, installation, FAQ, and changelog
- **Enhanced Plugin Metadata**: Added GitHub Plugin URI, Update URI, and WordPress compatibility information
- **Plugin Details Sections**: Description, installation, FAQ, and changelog sections for plugin details modal
- **WordPress.org Style Integration**: Plugin now displays with proper icons, banners, and metadata

### Enhanced
- Plugin header now includes all necessary metadata for WordPress plugin update system
- PluginUpdater class provides comprehensive plugin information for details modal
- Improved plugin discovery and update experience matching WordPress.org plugins
- Better integration with WordPress plugin management interface

## [1.3.3] - 2025-01-24

### Added
- **Cloudflare Development Mode Toggle**: New one-click toggle to enable/disable Cloudflare Development Mode directly from the WordPress admin
  - Real-time status display showing current development mode state (Enabled/Disabled)
  - Interactive toggle button with loading states and visual feedback
  - Smart warning notices when development mode is active (3-hour auto-disable info)
  - Full AJAX integration with proper security checks and error handling
  - Seamless integration into existing Cloudflare settings page

### Enhanced
- Cloudflare settings page now displays development mode status alongside cache and APO status
- Added comprehensive development mode API methods to CloudflareAPI and main Cloudflare classes
- Improved user experience with smooth transitions and real-time UI updates

## [1.3.2] - 2025-01-24

### Fixed
- **CRITICAL**: Fixed PHP fatal error "Class 'Puc_v4_Factory' not found" on fresh plugin installations
- Added comprehensive error handling for plugin-update-checker library loading
- Made plugin-update-checker completely optional to prevent fatal errors
- Added proper null checks and try-catch blocks for update checker initialization
- Plugin now works correctly even if vendor directory is missing

### Enhanced
- Improved plugin stability and error handling
- Added detailed error logging for troubleshooting update checker issues
- Enhanced plugin initialization process with graceful fallbacks

## [1.3.1] - 2025-01-24

### Added
- GitHub-based automatic plugin updates via plugin-update-checker
- Professional update system matching holler-elementor plugin
- One-click updates directly from WordPress admin
- GitHub release integration for seamless version management

### Enhanced
- Plugin now supports automatic updates from GitHub repository
- Improved plugin management and distribution workflow
- Enhanced user experience with built-in update notifications

## [1.3.0] - 2025-01-24

### Added
- Comprehensive Perfmatters compatibility diagnostics and documentation
- Frontend admin bar "Clear All Caches" button functionality
- Robust wp_footer hook implementation for frontend script loading
- Automatic detection of Perfmatters JavaScript delay/defer settings
- Visual compatibility status indicators in diagnostics tab
- Step-by-step troubleshooting documentation for admin bar issues
- Enhanced error handling for array/string format compatibility
- Debug logging and console output for frontend troubleshooting

### Fixed
- Admin bar "Clear All Caches" button not working on frontend pages
- Perfmatters JavaScript delay/defer preventing admin bar functionality
- AJAX action and nonce mismatch causing 400/403 errors
- Frontend script loading issues with performance optimization plugins
- PHP strpos() error when handling Perfmatters exclusion arrays
- Dynamic status text updates for cache purge operations

### Enhanced
- Admin bar cleanup with comprehensive duplicate removal
- Frontend compatibility for all admin bar cache management features
- Diagnostics tool with intelligent plugin conflict detection
- User experience with actionable recommendations and exclusion strings
- Documentation for common performance plugin compatibility issues

## [1.1.0] - 2025-02-14

### Added
- WordPress core cache integration
- Automatic cache purging on content updates
- Elementor-specific cache handling and optimization
- Google Fonts optimization for Elementor
- Comprehensive cache purging with Elementor support
- Astra theme cache integration and optimization
- Support for holler-agnt child theme cache
- Dynamic CSS preloading for Astra theme

### Changed
- Enhanced cache purging to include WordPress core caches
- Improved performance with Elementor integration
- Better handling of transient cache cleanup
- Optimized asset loading for Astra theme
- Added Astra-specific cache purging hooks

## [1.0.0] - 2025-02-13

### Added
- Initial release
- Redis Object Cache integration
- Cloudflare cache management
- Admin bar quick actions
- Settings page with UI-based configuration
- Support for config file-based credentials
- Cache status overview
- One-click cache purging
- Comprehensive documentation
