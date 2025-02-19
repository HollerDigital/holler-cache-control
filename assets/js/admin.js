jQuery(function($) {
    // Update cache status in admin bar
    function updateCacheStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'holler_cache_control_status',
                _wpnonce: hollerCacheControl.nonces.status
            },
            success: function(response) {
                if (!response.success) {
                    return;
                }

                const status = response.data;
                
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
            }
        });
    }

    // Purge cache function
    function purgeCache(cacheType, $button) {
        console.log('Purging cache:', cacheType);
        const originalText = $button.text();
        $button.prop('disabled', true).text(hollerCacheControl.i18n.purging);

        // Get the correct nonce based on cache type
        const nonceKey = cacheType;
        const nonce = hollerCacheControl.nonces[nonceKey];
        
        console.log('Nonce key:', nonceKey);
        console.log('Nonce value:', nonce);
        console.log('Available nonces:', hollerCacheControl.nonces);

        if (!nonce) {
            console.error('No nonce found for cache type:', cacheType);
            showNotice('Error: Invalid cache type', 'error');
            $button.prop('disabled', false).text(originalText);
            return;
        }

        const data = {
            action: 'holler_purge_cache',
            type: cacheType,
            _ajax_nonce: nonce
        };

        //console.log('Sending AJAX request with data:', data);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                console.log('Purge response:', response);
                
                // Show the message directly without counting successes/failures
                showNotice(response.data, response.success ? 'success' : 'error');
                $button.prop('disabled', false).text(originalText);
                updateCacheStatus();
            },
            error: function(xhr, status, error) {
                console.error('Purge error details:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status,
                    statusText: xhr.statusText
                });
                showNotice('Failed to purge cache: ' + (xhr.responseText || error), 'error');
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    // Handle purge button clicks (both admin bar and tools page)
    function handlePurgeClick(e) {
        e.preventDefault();
        const $button = $(this);
        const cacheType = $button.data('cache-type') || 'all';
        purgeCache(cacheType, $button);
    }

    // Show notice function
    function showNotice(message, type = 'success') {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible" style="background: #fff; border-left: 4px solid #72aee6; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 1px 12px; margin: 5px 0;"><p>' + message + '</p></div>');
        
        // Set border color based on type
        if (type === 'success') {
            $notice.css('border-left-color', '#00a32a');
        } else if (type === 'error') {
            $notice.css('border-left-color', '#d63638');
        } else if (type === 'warning') {
            $notice.css('border-left-color', '#dba617');
        }
        
        // Add dismiss button
        const $button = $('<button type="button" class="notice-dismiss" style="position: absolute; top: 0; right: 1px; border: none; margin: 0; padding: 9px; background: none; color: #787c82; cursor: pointer;"><span class="screen-reader-text">Dismiss this notice.</span></button>');
        $button.on('click', function() {
            $notice.fadeOut(function() { $(this).remove(); });
        });
        $notice.append($button);
        
        // Find notice container
        let $container = $('#holler-cache-control-notices');
        if (!$container.length) {
            // For admin bar, create a floating container
            $container = $('<div id="holler-cache-control-notices" style="position: fixed; top: 32px; right: 20px; z-index: 999999; max-width: 300px; background: transparent;"></div>');
            $('body').append($container);
        }
        
        // Add notice to container
        $container.empty().append($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() { $(this).remove(); });
        }, 5000);
    }

    // Initial cache status update
    updateCacheStatus();

    // Update cache status periodically
    setInterval(updateCacheStatus, 30000);

    // Attach click handlers to all purge buttons
    $(document).on('click', '.purge-cache', handlePurgeClick);

    // Handle settings form submission
    $('#holler-cache-control-settings').submit(function(e) {
        e.preventDefault();
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"]');
        const originalText = $submitButton.val();

        $submitButton.val('Saving...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=holler_cache_control_save_settings&_wpnonce=' + hollerCacheControl.nonces.settings,
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data || 'Failed to save settings', 'error');
                }
                $submitButton.val(originalText).prop('disabled', false);
            },
            error: function() {
                showNotice('Failed to save settings', 'error');
                $submitButton.val(originalText).prop('disabled', false);
            }
        });
    });

    // Handle purge all caches button clicks
    $('.holler-purge-all-caches').on('click', function(e) {
        e.preventDefault();
        let confirmMessage = hollerCacheControl.i18n.confirm_purge_all;
        if (confirm(confirmMessage)) {
            const $button = $(this);
            const originalText = $button.text();
            $button.prop('disabled', true).text(hollerCacheControl.i18n.purging);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'holler_purge_all_caches',
                    _ajax_nonce: hollerCacheControl.nonces.all_caches
                },
                success: function(response) {
                    console.log('Purge response:', response);
                    if (response.success) {
                        showNotice(response.data, 'success');
                    } else {
                        showNotice(response.data || 'Failed to purge cache', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Purge error:', error);
                    showNotice('Failed to purge cache', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                    updateCacheStatus();
                }
            });
        }
    });
});
