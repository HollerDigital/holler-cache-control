<?php

namespace Holler\CacheControl\Admin\Cache;

use Holler\CacheControl\Admin\Tools;

/**
 * Smart Cache Invalidation System
 * 
 * Provides intelligent, selective cache purging with user control.
 * Especially useful for Cloudflare where full purges can be expensive.
 */
class SmartInvalidation {
    
    /**
     * Available invalidation strategies
     */
    const STRATEGY_FULL = 'full';
    const STRATEGY_SELECTIVE = 'selective';
    const STRATEGY_SMART = 'smart';
    
    /**
     * Cache invalidation scopes
     */
    const SCOPE_PAGE = 'page';
    const SCOPE_CATEGORY = 'category';
    const SCOPE_SITE = 'site';
    const SCOPE_ASSETS = 'assets';
    
    /**
     * Get user's preferred invalidation strategy
     *
     * @return string Strategy preference
     */
    public static function get_strategy() {
        return get_option('holler_cache_invalidation_strategy', self::STRATEGY_SMART);
    }
    
    /**
     * Set user's preferred invalidation strategy
     *
     * @param string $strategy Strategy to set
     * @return bool Success status
     */
    public static function set_strategy($strategy) {
        $valid_strategies = [self::STRATEGY_FULL, self::STRATEGY_SELECTIVE, self::STRATEGY_SMART];
        if (!in_array($strategy, $valid_strategies)) {
            return false;
        }
        
        return update_option('holler_cache_invalidation_strategy', $strategy);
    }
    
    /**
     * Smart cache invalidation based on content type and user preferences
     *
     * @param int|null $post_id Post ID for context-aware invalidation
     * @param string $context Context of the invalidation (save_post, delete_post, etc.)
     * @param array $options Additional options for invalidation
     * @return array Results from invalidation operations
     */
    public static function invalidate($post_id = null, $context = 'manual', $options = []) {
        $strategy = self::get_strategy();
        $results = [];
        
        error_log("Holler Cache Control: Smart invalidation started - Strategy: {$strategy}, Context: {$context}, Post ID: " . ($post_id ?? 'none'));
        
        switch ($strategy) {
            case self::STRATEGY_FULL:
                $results = self::full_invalidation($options);
                break;
                
            case self::STRATEGY_SELECTIVE:
                $results = self::selective_invalidation($post_id, $context, $options);
                break;
                
            case self::STRATEGY_SMART:
            default:
                $results = self::smart_invalidation($post_id, $context, $options);
                break;
        }
        
        // Invalidate status caches after any cache operation
        StatusCache::invalidate_all();
        
        return $results;
    }
    
    /**
     * Full cache invalidation (traditional approach)
     *
     * @param array $options Invalidation options
     * @return array Results from cache operations
     */
    private static function full_invalidation($options = []) {
        error_log('Holler Cache Control: Executing full invalidation');
        
        // Use existing CacheManager for full purge
        return CacheManager::purge_all_caches();
    }
    
    /**
     * Selective cache invalidation based on user configuration
     *
     * @param int|null $post_id Post ID for context
     * @param string $context Invalidation context
     * @param array $options Invalidation options
     * @return array Results from selective operations
     */
    private static function selective_invalidation($post_id = null, $context = 'manual', $options = []) {
        $results = [];
        $enabled_layers = self::get_enabled_cache_layers();
        $enabled_scopes = self::get_enabled_invalidation_scopes();
        
        error_log('Holler Cache Control: Executing selective invalidation - Layers: ' . implode(', ', $enabled_layers) . ', Scopes: ' . implode(', ', $enabled_scopes));
        
        // Local caches (always fast to clear)
        if (in_array('local', $enabled_layers)) {
            $local_results = self::purge_local_caches();
            $results = array_merge($results, $local_results);
        }
        
        // Server caches with scope awareness
        if (in_array('server', $enabled_layers)) {
            $server_results = self::purge_server_caches_selective($post_id, $enabled_scopes);
            $results = array_merge($results, $server_results);
        }
        
        // External caches with selective purging
        if (in_array('external', $enabled_layers)) {
            $external_results = self::purge_external_caches_selective($post_id, $enabled_scopes, $context);
            $results = array_merge($results, $external_results);
        }
        
        return [
            'success' => true,
            'results' => $results,
            'strategy' => 'selective',
            'layers_processed' => $enabled_layers,
            'scopes_processed' => $enabled_scopes
        ];
    }
    
    /**
     * Smart cache invalidation with automatic decision making
     *
     * @param int|null $post_id Post ID for context
     * @param string $context Invalidation context
     * @param array $options Invalidation options
     * @return array Results from smart operations
     */
    private static function smart_invalidation($post_id = null, $context = 'manual', $options = []) {
        $results = [];
        
        // Analyze the context to determine optimal strategy
        $analysis = self::analyze_invalidation_context($post_id, $context, $options);
        
        error_log('Holler Cache Control: Executing smart invalidation - Analysis: ' . json_encode($analysis));
        
        // Always clear local caches (fast and safe)
        $local_results = self::purge_local_caches();
        $results = array_merge($results, $local_results);
        
        // Server cache decisions based on analysis
        if ($analysis['needs_server_purge']) {
            if ($analysis['scope'] === self::SCOPE_PAGE && $post_id) {
                $server_results = self::purge_server_caches_selective($post_id, [self::SCOPE_PAGE]);
            } else {
                $server_results = self::purge_server_caches_selective($post_id, $analysis['recommended_scopes']);
            }
            $results = array_merge($results, $server_results);
        }
        
        // External cache decisions (Cloudflare)
        if ($analysis['needs_external_purge']) {
            if ($analysis['cloudflare_strategy'] === 'selective' && $post_id) {
                $external_results = self::purge_cloudflare_selective($post_id, $analysis['recommended_scopes']);
            } else {
                $external_results = self::purge_external_caches_full();
            }
            $results = array_merge($results, $external_results);
        }
        
        return [
            'success' => true,
            'results' => $results,
            'strategy' => 'smart',
            'analysis' => $analysis,
            'recommendations' => $analysis['recommendations'] ?? []
        ];
    }
    
    /**
     * Analyze invalidation context to determine optimal strategy
     *
     * @param int|null $post_id Post ID
     * @param string $context Context
     * @param array $options Options
     * @return array Analysis results
     */
    private static function analyze_invalidation_context($post_id, $context, $options) {
        $analysis = [
            'needs_server_purge' => true,
            'needs_external_purge' => true,
            'scope' => self::SCOPE_SITE,
            'recommended_scopes' => [self::SCOPE_SITE],
            'cloudflare_strategy' => 'full',
            'recommendations' => []
        ];
        
        // Context-based analysis
        switch ($context) {
            case 'save_post':
            case 'publish_post':
                if ($post_id) {
                    $post_type = get_post_type($post_id);
                    $post_status = get_post_status($post_id);
                    
                    if ($post_status === 'publish') {
                        $analysis['scope'] = self::SCOPE_PAGE;
                        $analysis['recommended_scopes'] = [self::SCOPE_PAGE, self::SCOPE_CATEGORY];
                        $analysis['cloudflare_strategy'] = 'selective';
                        $analysis['recommendations'][] = 'Page-specific invalidation recommended for published content';
                    }
                }
                break;
                
            case 'delete_post':
                $analysis['scope'] = self::SCOPE_CATEGORY;
                $analysis['recommended_scopes'] = [self::SCOPE_CATEGORY, self::SCOPE_SITE];
                $analysis['recommendations'][] = 'Category and site-wide invalidation recommended for deleted content';
                break;
                
            case 'wp_update_nav_menu':
                $analysis['scope'] = self::SCOPE_SITE;
                $analysis['recommended_scopes'] = [self::SCOPE_SITE];
                $analysis['recommendations'][] = 'Site-wide invalidation recommended for menu changes';
                break;
                
            case 'switch_theme':
            case 'customize_save_after':
                $analysis['scope'] = self::SCOPE_SITE;
                $analysis['recommended_scopes'] = [self::SCOPE_SITE, self::SCOPE_ASSETS];
                $analysis['recommendations'][] = 'Full site and asset invalidation recommended for theme changes';
                break;
                
            case 'manual':
            default:
                // For manual purges, respect user preferences more heavily
                $user_preference = get_option('holler_cache_manual_purge_scope', self::SCOPE_SITE);
                $analysis['scope'] = $user_preference;
                $analysis['recommended_scopes'] = [$user_preference];
                break;
        }
        
        // Check Cloudflare plan limits (if we can detect them)
        $cloudflare_status = Cloudflare::get_status();
        if ($cloudflare_status['configured']) {
            // For now, assume selective purging is available
            // In the future, we could check plan limits via API
            if ($analysis['cloudflare_strategy'] === 'selective') {
                $analysis['recommendations'][] = 'Selective Cloudflare purging will be used to preserve cache performance';
            }
        }
        
        return $analysis;
    }
    
    /**
     * Get enabled cache layers from user settings
     *
     * @return array Enabled cache layers
     */
    private static function get_enabled_cache_layers() {
        return get_option('holler_cache_selective_layers', ['local', 'server', 'external']);
    }
    
    /**
     * Get enabled invalidation scopes from user settings
     *
     * @return array Enabled scopes
     */
    private static function get_enabled_invalidation_scopes() {
        return get_option('holler_cache_selective_scopes', [self::SCOPE_PAGE, self::SCOPE_CATEGORY, self::SCOPE_SITE]);
    }
    
    /**
     * Purge local caches (OPcache, Redis Object Cache)
     *
     * @return array Results
     */
    private static function purge_local_caches() {
        return CacheManager::purge_local_caches();
    }
    
    /**
     * Purge server caches with selective scope
     *
     * @param int|null $post_id Post ID for context
     * @param array $scopes Enabled scopes
     * @return array Results
     */
    private static function purge_server_caches_selective($post_id, $scopes) {
        // For now, Nginx cache is all-or-nothing
        // Future enhancement: implement path-based Nginx purging
        return CacheManager::purge_server_caches();
    }
    
    /**
     * Purge external caches with selective scope
     *
     * @param int|null $post_id Post ID for context
     * @param array $scopes Enabled scopes
     * @param string $context Context
     * @return array Results
     */
    private static function purge_external_caches_selective($post_id, $scopes, $context) {
        if (in_array(self::SCOPE_PAGE, $scopes) && $post_id) {
            return self::purge_cloudflare_selective($post_id, $scopes);
        } else {
            return self::purge_external_caches_full();
        }
    }
    
    /**
     * Purge external caches completely
     *
     * @return array Results
     */
    private static function purge_external_caches_full() {
        return CacheManager::purge_external_caches();
    }
    
    /**
     * Selective Cloudflare cache purging
     *
     * @param int $post_id Post ID
     * @param array $scopes Purge scopes
     * @return array Results
     */
    private static function purge_cloudflare_selective($post_id, $scopes) {
        $results = [];
        
        try {
            $urls_to_purge = self::get_urls_for_post($post_id, $scopes);
            
            if (empty($urls_to_purge)) {
                error_log('Holler Cache Control: No URLs found for selective Cloudflare purge');
                return self::purge_external_caches_full();
            }
            
            error_log('Holler Cache Control: Selective Cloudflare purge for URLs: ' . implode(', ', $urls_to_purge));
            
            // Use Cloudflare API for selective purging
            $cloudflare_result = Cloudflare::purge_urls($urls_to_purge);
            if ($cloudflare_result['success']) {
                $results['cloudflare_selective'] = [
                    'success' => true,
                    'message' => sprintf(__('Cloudflare cache cleared for %d URLs.', 'holler-cache-control'), count($urls_to_purge)),
                    'urls_purged' => count($urls_to_purge)
                ];
            }
            
            // Handle APO if enabled
            $apo_status = CloudflareAPO::get_status();
            if ($apo_status['enabled']) {
                $apo_result = CloudflareAPO::purge_urls($urls_to_purge);
                if ($apo_result['success']) {
                    $results['cloudflare_apo_selective'] = [
                        'success' => true,
                        'message' => sprintf(__('Cloudflare APO cache cleared for %d URLs.', 'holler-cache-control'), count($urls_to_purge)),
                        'urls_purged' => count($urls_to_purge)
                    ];
                }
            }
            
        } catch (\Exception $e) {
            error_log('Holler Cache Control - Selective Cloudflare purge failed: ' . $e->getMessage());
            // Fallback to full purge on error
            return self::purge_external_caches_full();
        }
        
        return $results;
    }
    
    /**
     * Get URLs to purge for a given post and scopes
     *
     * @param int $post_id Post ID
     * @param array $scopes Purge scopes
     * @return array URLs to purge
     */
    private static function get_urls_for_post($post_id, $scopes) {
        $urls = [];
        
        if (in_array(self::SCOPE_PAGE, $scopes)) {
            $post_url = get_permalink($post_id);
            if ($post_url) {
                $urls[] = $post_url;
            }
        }
        
        if (in_array(self::SCOPE_CATEGORY, $scopes)) {
            $categories = get_the_category($post_id);
            foreach ($categories as $category) {
                $category_url = get_category_link($category->term_id);
                if ($category_url) {
                    $urls[] = $category_url;
                }
            }
            
            // Add tag archives if applicable
            $tags = get_the_tags($post_id);
            if ($tags) {
                foreach ($tags as $tag) {
                    $tag_url = get_tag_link($tag->term_id);
                    if ($tag_url) {
                        $urls[] = $tag_url;
                    }
                }
            }
        }
        
        if (in_array(self::SCOPE_SITE, $scopes)) {
            $urls[] = home_url('/');
            
            // Add common pages
            $urls[] = home_url('/feed/');
            $urls[] = home_url('/sitemap.xml');
            $urls[] = home_url('/sitemap_index.xml');
        }
        
        // Remove duplicates and return
        return array_unique($urls);
    }
    
    /**
     * Get smart invalidation settings for admin UI
     *
     * @return array Settings data
     */
    public static function get_settings() {
        return [
            'strategy' => self::get_strategy(),
            'enabled_layers' => self::get_enabled_cache_layers(),
            'enabled_scopes' => self::get_enabled_invalidation_scopes(),
            'manual_purge_scope' => get_option('holler_cache_manual_purge_scope', self::SCOPE_SITE),
            'cloudflare_selective_enabled' => get_option('holler_cache_cloudflare_selective', true),
            'auto_analysis_enabled' => get_option('holler_cache_auto_analysis', true)
        ];
    }
    
    /**
     * Update smart invalidation settings
     *
     * @param array $settings New settings
     * @return bool Success status
     */
    public static function update_settings($settings) {
        $updated = 0;
        
        if (isset($settings['strategy'])) {
            if (self::set_strategy($settings['strategy'])) {
                $updated++;
            }
        }
        
        if (isset($settings['enabled_layers'])) {
            update_option('holler_cache_selective_layers', $settings['enabled_layers']);
            $updated++;
        }
        
        if (isset($settings['enabled_scopes'])) {
            update_option('holler_cache_selective_scopes', $settings['enabled_scopes']);
            $updated++;
        }
        
        if (isset($settings['manual_purge_scope'])) {
            update_option('holler_cache_manual_purge_scope', $settings['manual_purge_scope']);
            $updated++;
        }
        
        if (isset($settings['cloudflare_selective_enabled'])) {
            update_option('holler_cache_cloudflare_selective', $settings['cloudflare_selective_enabled']);
            $updated++;
        }
        
        if (isset($settings['auto_analysis_enabled'])) {
            update_option('holler_cache_auto_analysis', $settings['auto_analysis_enabled']);
            $updated++;
        }
        
        // Invalidate status caches after settings change
        StatusCache::invalidate_all();
        
        return $updated > 0;
    }
}
