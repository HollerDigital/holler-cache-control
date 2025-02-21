(function($) {
    'use strict';

    // Wait for document ready
    $(function() {
        // Check if we're on an admin page
        const isAdminPage = $('body').hasClass('wp-admin');
        
        // Check if hollerCacheControl object exists
        if (typeof window.hollerCacheControl === 'undefined') {
            console.error('hollerCacheControl object not properly initialized');
            return;
        }

        // Update cache status in admin bar
        function updateCacheStatus() {
            $.ajax({
                url: hollerCacheControl.ajaxurl,
                type: 'POST',
                data: {
                    action: 'holler_cache_control_status',
                    _wpnonce: hollerCacheControl.nonces.status
                },
                success: function(response) {
                    if (!response || typeof response !== 'object') {
                        console.error('Invalid response from server:', response);
                        return;
                    }

                    if (!response.success) {
                        console.error('Status update failed:', response.data ? response.data.message : 'Unknown error');
                        return;
                    }

                    const status = response.data;
                    if (!status) {
                        console.error('No status data in response');
                        return;
                    }
                    
                    // Update Nginx status
                    if (status.nginx) {
                        const $nginxStatus = $('#wp-admin-bar-holler-nginx-status');
                        if ($nginxStatus.length) {
                            const text = status.nginx.active ? 'Running' : 'Not Active';
                            const icon = status.nginx.active ? '✓' : '✗';
                            $nginxStatus.find('.ab-item').html(icon + ' Nginx Cache: ' + text);
                        }
                    }
                    
                    // Update Redis status
                    if (status.redis) {
                        const $redisStatus = $('#wp-admin-bar-holler-redis-status');
                        if ($redisStatus.length) {
                            const text = status.redis.active ? 'Running' : 'Not Active';
                            const icon = status.redis.active ? '✓' : '✗';
                            $redisStatus.find('.ab-item').html(icon + ' Redis Cache: ' + text);
                        }
                    }
                    
                    // Update Cloudflare status
                    if (status.cloudflare) {
                        const $cloudflareStatus = $('#wp-admin-bar-holler-cloudflare-status');
                        if ($cloudflareStatus.length) {
                            const text = status.cloudflare.active ? 'Running' : 'Not Active';
                            const icon = status.cloudflare.active ? '✓' : '✗';
                            $cloudflareStatus.find('.ab-item').html(icon + ' Cloudflare Cache: ' + text);
                        }
                    }
                    
                    // Update Cloudflare APO status
                    if (status.cloudflare_apo) {
                        const $apoStatus = $('#wp-admin-bar-holler-cloudflare-apo-status');
                        if ($apoStatus.length) {
                            const text = status.cloudflare_apo.active ? 'Running' : 'Not Active';
                            const icon = status.cloudflare_apo.active ? '✓' : '✗';
                            $apoStatus.find('.ab-item').html(icon + ' Cloudflare APO: ' + text);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Cache status update failed:', error);
                }
            });
        }

        // Purge cache function
        function purgeCache(cacheType, $button) {
            const originalText = $button.text();
            $button.prop('disabled', true).text(hollerCacheControl.i18n.purging);

            // Get the correct nonce based on cache type
            const nonceKey = cacheType === 'all' ? 'all' : cacheType;
            const nonce = hollerCacheControl.nonces[nonceKey];

            if (!nonce) {
                console.error('Invalid cache type or missing nonce:', cacheType);
                $button.prop('disabled', false).text(originalText);
                showNotice(hollerCacheControl.i18n.error, 'error');
                return;
            }
            
            $.ajax({
                url: hollerCacheControl.ajaxurl,
                type: 'POST',
                data: {
                    action: 'holler_purge_cache',
                    type: cacheType,
                    _wpnonce: nonce
                },
                success: function(response) {
                    console.log('Raw AJAX response:', response);
                    try {
                        if (!response) {
                            throw new Error('Empty response from server');
                        }

                        // WordPress AJAX sends JSON responses
                        let message = hollerCacheControl.i18n.error;
                        let isSuccess = false;

                        if (typeof response === 'object') {
                            isSuccess = response.success === true;
                            if (response.data && typeof response.data.message === 'string') {
                                message = response.data.message;
                            }
                        } else if (typeof response === 'string') {
                            try {
                                const parsed = JSON.parse(response);
                                isSuccess = parsed.success === true;
                                if (parsed.data && typeof parsed.data.message === 'string') {
                                    message = parsed.data.message;
                                }
                            } catch (e) {
                                console.error('Failed to parse response:', e);
                            }
                        }

                        // Show notice based on success/error
                        showNotice(message, isSuccess ? 'success' : 'error');

                        // Always update cache status after successful operation
                        if (isSuccess) {
                            updateCacheStatus();
                        }
                    } catch (e) {
                        console.error('Error processing response:', e, response);
                        showNotice(hollerCacheControl.i18n.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Cache purge failed:', {xhr, status, error});
                    showNotice(hollerCacheControl.i18n.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }

        // Poll for purge status
        function pollPurgeStatus(attempts = 0) {
            if (attempts >= 10) { // Stop after 10 attempts (50 seconds)
                return;
            }

            setTimeout(function() {
                $.ajax({
                    url: hollerCacheControl.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'holler_cache_control_status',
                        _wpnonce: hollerCacheControl.nonces.status
                    },
                    success: function(response) {
                        try {
                            if (!response || typeof response !== 'object') {
                                throw new Error('Invalid response from server');
                            }

                            if (!response.success || !response.data) {
                                console.error('Status check failed:', response);
                                return;
                            }

                            updateCacheStatus();
                            
                            // Check if all operations are complete
                            const results = response.data.last_purge_results;
                            if (results && results.timestamp) {
                                const allComplete = Object.values(results.results || {}).every(r => 
                                    r && r.status && r.status !== 'pending'
                                );
                                
                                if (!allComplete && attempts < 10) {
                                    pollPurgeStatus(attempts + 1);
                                }
                            }
                        } catch (e) {
                            console.error('Error processing status response:', e);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to check purge status:', error);
                    }
                });
            }, 5000);
        }

        // Show notice function
        function showNotice(message, type = 'success') {
            if (!message) {
                console.error('No message provided for notice');
                return;
            }

            const $notices = $('#holler-cache-control-notices');
            if (!$notices.length) {
                console.error('Notice container not found');
                return;
            }

            const $notice = $('<div>', {
                class: 'notice notice-' + type + ' is-dismissible',
                css: {
                    position: 'fixed',
                    top: '32px',
                    right: '10px',
                    zIndex: 99999,
                    margin: '5px 15px 2px',
                    padding: '5px 15px'
                }
            }).append(
                $('<p>').text(message)
            ).append(
                $('<button>', {
                    type: 'button',
                    class: 'notice-dismiss',
                    html: '<span class="screen-reader-text">Dismiss this notice.</span>'
                })
            );

            $notices.append($notice);

            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                if ($notice.length) {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            }, 5000);
        }

        // Initialize if we have the admin bar or are on the settings page
        if ($('#wpadminbar').length || isAdminPage) {
            // Initial cache status update
            updateCacheStatus();

            // Handle purge cache button clicks
            $('.purge-cache').on('click', function(e) {
                e.preventDefault();
                const $button = $(this);
                const cacheType = $button.data('cache-type') || 'all';
                
                if (cacheType === 'all' && !confirm(hollerCacheControl.i18n.confirm_purge_all)) {
                    return;
                }
                
                purgeCache(cacheType, $button);
            });

            // Settings page specific initialization
            if (isAdminPage) {
                // Handle settings form submission
                $('#holler-cache-control-settings').on('submit', function(e) {
                    e.preventDefault();
                    const $form = $(this);
                    const $submitButton = $form.find('button[type="submit"]');
                    const originalText = $submitButton.text();

                    $submitButton.prop('disabled', true).text('Saving...');

                    $.ajax({
                        url: hollerCacheControl.ajaxurl,
                        type: 'POST',
                        data: $form.serialize() + '&action=holler_cache_control_save_settings&_wpnonce=' + hollerCacheControl.nonces.settings,
                        success: function(response) {
                            try {
                                if (!response || typeof response !== 'object') {
                                    throw new Error('Invalid response from server');
                                }

                                if (response.success) {
                                    showNotice(response.data || 'Settings saved successfully', 'success');
                                } else {
                                    const message = response.data && response.data.message ? 
                                        response.data.message : 'Failed to save settings';
                                    showNotice(message, 'error');
                                }
                            } catch (e) {
                                console.error('Error processing settings response:', e);
                                showNotice('Failed to save settings', 'error');
                            }
                        },
                        error: function() {
                            showNotice('Failed to save settings', 'error');
                        },
                        complete: function() {
                            $submitButton.prop('disabled', false).text(originalText);
                        }
                    });
                });
            }
        }
    });
})(jQuery);
