# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.0] - 2025-01-25

### Added
- **🛠️ Robust System Command Execution**: Multi-method fallback system for server compatibility
  - **4 Execution Methods**: Automatic fallback through `exec()`, `shell_exec()`, `system()`, and `passthru()`
  - **Server Compatibility**: Works across different PHP configurations and security restrictions
  - **Detailed Diagnostics**: Reports which execution method succeeded and why others failed
  - **GridPane CLI Integration**: Reliable `gp fix perms` command execution for permissions repair
- **🔐 Enhanced GridPane Redis Authentication**: Full support for Redis 6.0+ ACL authentication
  - **Username + Password**: Proper authentication with GridPane's Redis ACL setup
  - **Fallback Support**: Maintains compatibility with password-only authentication
  - **Connection Diagnostics**: Enhanced logging and error reporting for Redis connection issues
  - **Timeout Handling**: 2-second connection timeout with proper error handling
- **📊 Comprehensive Cache Detection**: Enhanced GridPane cache method detection and diagnostics
  - **Dual Method Support**: Detects both Nginx FastCGI and Redis page caching methods
  - **Fallback Path Detection**: Smart cache path discovery when constants are not defined
  - **Detailed Status Messages**: Clear, emoji-enhanced status reporting with GridPane-specific feedback
  - **Advanced Diagnostics**: New `get_gridpane_diagnostics()` function for troubleshooting

### Fixed
- **🔥 Critical Production Bugs**: Resolved fatal errors affecting live site functionality
  - **Namespace Issues**: Fixed `Call to undefined function exec()` errors with proper global namespace usage
  - **AJAX Error Handling**: Resolved "Cache purge failed: Object" display issues with structured error responses
  - **Redis Authentication**: Fixed "WRONGPASS invalid username-password pair" errors with proper ACL authentication
- **🎯 User Experience Improvements**: Enhanced error messages and feedback systems
  - **Clear Error Messages**: Replaced confusing "Object" errors with meaningful, actionable messages
  - **Detailed Logging**: Enhanced error logging with context and debugging information
  - **Graceful Degradation**: Better handling of server restrictions and missing functions

### Changed
- **🚀 Production Stability**: Significantly improved reliability across different hosting environments
  - **Server Agnostic**: Plugin now works reliably regardless of PHP security restrictions
  - **Error Resilience**: Robust error handling prevents fatal errors from breaking site functionality
  - **GridPane Optimized**: Enhanced compatibility with GridPane hosting environment specifics
- **🔧 Developer Experience**: Improved debugging and troubleshooting capabilities
  - **Enhanced Logging**: Detailed error logs with execution method reporting
  - **Diagnostic Tools**: Comprehensive cache detection and connection testing
  - **Status Cache Management**: Added ability to clear status cache for fresh detection

### Technical Details
- **New Methods**: `execute_system_command()` - Multi-method system execution with fallbacks
- **Enhanced Classes**: Improved Redis connection handling in Nginx cache class
- **Error Handling**: Structured AJAX error responses with proper message extraction
- **Authentication**: Redis ACL support with `redis->auth(['username', 'password'])` format
- **Diagnostics**: Comprehensive GridPane environment detection and reporting
- **Compatibility**: Works across PHP 7.4+ with various security configurations

### Security
- **🛡️ Proper Namespace Usage**: All global PHP functions now use correct namespace prefixes
- **🔐 Secure Authentication**: Enhanced Redis authentication with proper credential handling
- **✅ Input Validation**: Improved command sanitization and error boundary handling

## [1.5.0] - 2025-01-25

### Added
- **🎛️ Comprehensive Cloudflare Settings Controls**: Complete management interface for all major Cloudflare zone settings
  - **Essential Controls**: Development Mode, Cache Level, Browser Cache TTL, Always Online
  - **Advanced Controls**: Rocket Loader, Auto Minify (HTML/CSS/JS), Security Level, SSL Mode
  - **Real-time Updates**: All settings update immediately via Cloudflare API with instant feedback
  - **One-click Optimization**: "Apply Recommended Settings" button for performance-focused defaults
  - **Current Settings Loader**: "Load Current Settings" button to populate controls with existing configuration
- **🔧 Enhanced Cloudflare Diagnostics**: Improved "Check & Configure Settings" functionality
  - **Detailed Configuration Display**: Shows comprehensive zone settings with emojis and clear formatting
  - **Reliable AJAX Implementation**: Standalone AJAX handler bypasses complex registration issues
  - **Professional UI**: Beautiful toggle switches, dropdowns, and grid layout with responsive design
- **⚡ Standalone AJAX System**: New `cloudflare-ajax.php` file for reliable Cloudflare API operations
  - **Multiple Handlers**: Support for loading, updating individual settings, minify controls, and bulk updates
  - **Robust Error Handling**: Comprehensive nonce verification, capability checks, and API error reporting
  - **Smart Minification**: Combined HTML/CSS/JS minification controls with individual toggle support

### Changed
- **🚀 Cloudflare Tab Experience**: Completely redesigned with professional controls and instant feedback
- **📊 Settings Display**: Enhanced configuration details with emojis, clear labeling, and organized layout
- **🔄 API Integration**: Improved Cloudflare API communication with better error handling and timeout management
- **💡 User Experience**: Streamlined workflow from diagnostics to configuration to optimization

### Fixed
- **✅ AJAX Handler Registration**: Resolved complex class loading issues with standalone handler approach
- **🔧 Credential Detection**: Fixed `$has_credentials` variable definition for proper UI visibility
- **📱 Responsive Design**: Improved mobile and tablet compatibility for settings controls
- **🛡️ Security**: Enhanced nonce verification and capability checks across all AJAX endpoints

### Technical Details
- **New Files**: `cloudflare-ajax.php` - Standalone AJAX handler system
- **Enhanced Files**: Cloudflare tab UI with comprehensive settings controls and professional styling
- **API Integration**: Direct Cloudflare API v4 integration for real-time settings management
- **Performance**: Optimized AJAX calls with proper error handling and user feedback

## [1.4.0] - 2024-12-24

### Added
- **Asynchronous Cache Purging**: Implemented background cache purging for AJAX requests to prevent 504 Gateway Timeout errors during Elementor and page builder operations
- **Enhanced Smart Detection**: Improved post ID detection for Elementor AJAX actions, looking for post IDs in multiple request parameters (`editor_post_id`, `post`, GET parameters)
- **WordPress Cron Integration**: Added `holler_cache_control_async_purge` cron hook for background cache operations
- **Comprehensive Debug Logging**: Enhanced logging for troubleshooting cache purging behavior and smart detection decisions

### Changed
- **AJAX Request Handling**: Cache purging for AJAX requests (like Elementor publish) now schedules background processing instead of blocking the request
- **Smart Detection Logic**: Refined to allow cache purging on legitimate publish/update actions while preventing purges during auto-saves and drafts
- **Performance Optimization**: Eliminated AJAX timeouts during page builder editing and publishing workflows

### Fixed
- **504 Gateway Timeout Errors**: Resolved timeout issues when publishing pages through Elementor and other page builders
- **Elementor Publish Cache Clearing**: Cache now properly clears when publishing pages through Elementor without causing editor conflicts
- **File Structure Corruption**: Fixed duplicate method definitions and corrupted code structure from previous edits
- **AJAX Performance**: Publishing and updating pages is now significantly faster with no blocking cache operations

### Technical Details
- Added `schedule_async_cache_purge()` method for background cache scheduling
- Added `handle_async_cache_purge()` method as WordPress cron callback
- Enhanced `should_skip_auto_purge()` with multi-parameter post ID detection
- Improved `purge_all_caches_with_detection()` with async scheduling for AJAX contexts
- Registered `holler_cache_control_async_purge` action hook in plugin constructor

### Developer Notes
- Cache purging now uses WordPress's built-in cron system for background processing
- AJAX requests complete immediately while cache operations happen asynchronously
- Smart detection maintains compatibility with multiple page builders and editors
- Debug logging provides detailed insights into cache purging decisions and execution

## [1.3.9] - 2025-01-24

### Added
- **Comprehensive Cache Plugin Conflict Detection**: New intelligent detection system for conflicting cache plugins
- **Enhanced Nginx Helper Analysis**: Detailed detection of all 9 specific Nginx Helper purge triggers:
  - `purge_homepage_on_edit`
  - `purge_homepage_on_del`
  - `purge_page_on_mod`
  - `purge_page_on_new_comment`
  - `purge_page_on_deleted_comment`
  - `purge_archive_on_edit`
  - `purge_archive_on_del`
  - `purge_archive_on_new_comment`
  - `purge_archive_on_deleted_comment`
- **Smart Conflict Risk Assessment**: Automatic categorization of conflicts as High, Medium, Low, or Informational
- **Redis Connection Diagnostics**: Real-time Redis server connection testing and status reporting
- **Cache Plugin Conflicts Section**: New dedicated section in Diagnostics tab with:
  - Visual conflict summary with color-coded status indicators
  - Detailed plugin cards showing conflict levels and recommendations
  - Granular Nginx Helper purge trigger analysis with ✓/✗ status display
  - Redis connection status with detailed connection information
  - Best practices documentation for cache management
- **Informational Plugin Support**: Redis Object Cache treated as informational rather than conflicting
- **Actionable Recommendations**: Specific guidance for each detected plugin with exact settings to modify

### Enhanced
- **Diagnostics Tab UI**: Comprehensive visual overhaul with professional styling and responsive design
- **Conflict Detection Logic**: Intelligent assessment based on enabled purge triggers (6+ = High, 3-5 = Medium, 1-2 = Low)
- **User Experience**: Clear visual indicators, hover effects, and mobile-friendly responsive layout
- **Plugin Compatibility**: Enhanced detection for W3 Total Cache, WP Rocket, WP Super Cache, and LiteSpeed Cache

### Fixed
- **PHP Namespace Conflicts**: Resolved Redis class namespace collision with fully qualified class names
- **Constructor Arguments**: Fixed Tools class instantiation in diagnostics with proper constructor parameters
- **Duplicate Method Declarations**: Removed accidentally duplicated methods causing fatal PHP errors

### Technical
- **Detection Methods**: Added `detect_conflicting_cache_plugins()` and `get_cache_plugin_conflict_warnings()` methods
- **UI Components**: New conflict plugin cards with detailed settings analysis and status indicators
- **CSS Styling**: Comprehensive styling for conflict levels, status indicators, and responsive design
- **Error Handling**: Robust error handling for Redis connection testing and plugin detection

### Documentation
- **Best Practices Guide**: Integrated documentation promoting single-plugin cache management approach
- **Conflict Resolution**: Clear guidance on disabling conflicting auto-purge settings
- **Redis Information**: Educational content about Redis Object Cache compatibility

This release provides users with comprehensive visibility into cache plugin conflicts and actionable guidance for optimizing their cache management setup with Holler Cache Control as the unified solution.

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
