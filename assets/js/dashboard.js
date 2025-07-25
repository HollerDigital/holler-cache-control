/**
 * Holler Cache Control - Real-time Dashboard
 */
(function($) {
    'use strict';

    const Dashboard = {
        // Configuration
        config: {
            refreshInterval: 15000, // Default 15 seconds
            autoRefresh: true,
            maxRetries: 3,
            retryDelay: 2000
        },

        // State
        state: {
            isLoading: false,
            retryCount: 0,
            lastUpdate: null,
            refreshTimer: null
        },

        // Initialize dashboard
        init: function() {
            this.config = $.extend(this.config, window.hollerCacheDashboard || {});
            this.bindEvents();
            this.loadDashboardData();
            this.startAutoRefresh();
            
            console.log('Holler Cache Dashboard initialized');
        },

        // Bind event handlers
        bindEvents: function() {
            const self = this;

            // Manual refresh button
            $(document).on('click', '.dashboard-refresh-btn', function(e) {
                e.preventDefault();
                self.loadDashboardData();
            });

            // Auto-refresh toggle
            $(document).on('change', '.dashboard-auto-refresh', function() {
                self.config.autoRefresh = $(this).is(':checked');
                if (self.config.autoRefresh) {
                    self.startAutoRefresh();
                } else {
                    self.stopAutoRefresh();
                }
            });

            // Refresh interval selector
            $(document).on('change', '.dashboard-refresh-interval', function() {
                self.config.refreshInterval = parseInt($(this).val()) * 1000;
                if (self.config.autoRefresh) {
                    self.startAutoRefresh();
                }
            });

            // Quick action buttons
            $(document).on('click', '.dashboard-quick-action', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                const target = $(this).data('target') || '';
                const confirm = $(this).data('confirm') || false;
                
                if (confirm && !window.confirm(self.config.strings.confirm_purge)) {
                    return;
                }
                
                self.performAction(action, target, $(this));
            });

            // Cache service action buttons
            $(document).on('click', '.cache-service-action', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                const service = $(this).data('service');
                const confirm = $(this).data('confirm') || false;
                
                if (confirm && !window.confirm(self.config.strings.confirm_purge)) {
                    return;
                }
                
                self.performAction(action, service, $(this));
            });

            // Performance metrics toggle
            $(document).on('click', '.toggle-performance-metrics', function(e) {
                e.preventDefault();
                self.togglePerformanceMetrics();
            });
        },

        // Load dashboard data via AJAX
        loadDashboardData: function() {
            const self = this;
            
            if (this.state.isLoading) {
                return;
            }

            this.state.isLoading = true;
            this.showLoadingState();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'holler_cache_get_dashboard_data',
                    nonce: this.config.nonce
                },
                timeout: 10000,
                success: function(response) {
                    if (response.success) {
                        self.updateDashboard(response.data);
                        self.state.retryCount = 0;
                        self.state.lastUpdate = new Date();
                    } else {
                        self.showError('Failed to load dashboard data: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Dashboard AJAX error:', status, error);
                    self.handleLoadError();
                },
                complete: function() {
                    self.state.isLoading = false;
                    self.hideLoadingState();
                }
            });
        },

        // Update dashboard with new data
        updateDashboard: function(data) {
            this.updateCacheStatus(data.cache_status);
            this.updatePerformanceSummary(data.performance);
            this.updateRecentActivity(data.recent_activity);
            this.updateSystemHealth(data.system_health);
            this.updateQuickActions(data.quick_actions);
            this.updateLastRefresh(data.timestamp);
            
            // Update load time indicator
            if (data.load_time) {
                $('.dashboard-load-time').text(data.load_time + 'ms');
            }
        },

        // Update cache status cards
        updateCacheStatus: function(cacheStatus) {
            $.each(cacheStatus, function(service, status) {
                const $card = $('.cache-status-card[data-service="' + service + '"]');
                if ($card.length === 0) return;

                // Update status indicator
                $card.removeClass('status-active status-disabled status-not-configured status-error')
                     .addClass('status-' + status.status);

                // Update status message
                $card.find('.cache-status-message').text(status.message);

                // Update service-specific metrics
                if (status.cache_size) {
                    $card.find('.cache-size').text(status.cache_size);
                }
                if (status.memory_usage) {
                    $card.find('.memory-usage').text(status.memory_usage);
                }
                if (status.cache_files) {
                    $card.find('.cache-files').text(status.cache_files);
                }

                // Update action buttons
                const $actions = $card.find('.cache-actions');
                $actions.empty();
                
                if (status.actions && status.actions.length > 0) {
                    $.each(status.actions, function(i, action) {
                        const $btn = $('<button>')
                            .addClass('button cache-service-action')
                            .addClass('button-' + action.type)
                            .attr('data-action', action.id)
                            .attr('data-service', service)
                            .attr('data-confirm', action.confirm)
                            .text(action.label);
                        $actions.append($btn);
                    });
                }
            });
        },

        // Update performance summary
        updatePerformanceSummary: function(performance) {
            if (!performance) return;

            $('.cache-hit-rate').text(performance.cache_hit_rate + '%');
            $('.avg-response-time').text(performance.average_response_time + 'ms');
            $('.total-cache-size').text(performance.total_cache_size);
            $('.purges-today').text(performance.purges_today);
            
            if (performance.last_purge) {
                const lastPurge = new Date(performance.last_purge * 1000);
                $('.last-purge-time').text(this.formatRelativeTime(lastPurge));
            }

            // Update status cache efficiency
            if (performance.status_cache_efficiency) {
                $('.status-cache-hits').text(performance.status_cache_efficiency.hits || 0);
                $('.status-cache-misses').text(performance.status_cache_efficiency.misses || 0);
            }
        },

        // Update recent activity list
        updateRecentActivity: function(activities) {
            const $list = $('.recent-activity-list');
            $list.empty();

            if (!activities || activities.length === 0) {
                $list.append('<li class="no-activity">No recent activity</li>');
                return;
            }

            $.each(activities.slice(-5), function(i, activity) {
                const time = new Date(activity.timestamp * 1000);
                const $item = $('<li>')
                    .addClass('activity-item')
                    .addClass('activity-' + activity.action)
                    .html(
                        '<span class="activity-time">' + Dashboard.formatRelativeTime(time) + '</span>' +
                        '<span class="activity-description">' + activity.description + '</span>' +
                        '<span class="activity-user">' + activity.user + '</span>'
                    );
                $list.append($item);
            });
        },

        // Update system health indicators
        updateSystemHealth: function(health) {
            if (!health) return;

            $('.php-version').text(health.php_version);
            $('.wp-version').text(health.wp_version);
            $('.memory-usage-percent').text(health.memory_usage + '%');
            
            if (health.disk_space) {
                $('.disk-usage-percent').text(health.disk_space.used_percentage + '%');
                $('.disk-free-space').text(health.disk_space.free);
            }

            // Update recommendations
            const $recommendations = $('.health-recommendations');
            $recommendations.empty();
            
            if (health.recommendations && health.recommendations.length > 0) {
                $.each(health.recommendations, function(i, rec) {
                    $recommendations.append('<li>' + rec + '</li>');
                });
            } else {
                $recommendations.append('<li class="no-recommendations">No recommendations at this time</li>');
            }
        },

        // Update quick actions
        updateQuickActions: function(actions) {
            const $container = $('.dashboard-quick-actions');
            $container.empty();

            $.each(actions, function(i, action) {
                const $btn = $('<button>')
                    .addClass('button dashboard-quick-action')
                    .addClass('button-' + action.type)
                    .attr('data-action', action.id)
                    .attr('data-confirm', action.confirm)
                    .html('<span class="dashicons ' + action.icon + '"></span> ' + action.label);
                $container.append($btn);
            });
        },

        // Perform dashboard action
        performAction: function(action, target, $button) {
            const self = this;
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Processing...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'holler_cache_dashboard_action',
                    dashboard_action: action,
                    target: target,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message || self.config.strings.success);
                        // Refresh dashboard data after action
                        setTimeout(function() {
                            self.loadDashboardData();
                        }, 1000);
                    } else {
                        self.showError(response.data.message || 'Action failed');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Action AJAX error:', status, error);
                    self.showError('Network error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        // Toggle performance metrics display
        togglePerformanceMetrics: function() {
            const $metrics = $('.performance-metrics-detail');
            
            if ($metrics.is(':visible')) {
                $metrics.slideUp();
                $('.toggle-performance-metrics').text('Show Details');
            } else {
                this.loadPerformanceMetrics();
                $metrics.slideDown();
                $('.toggle-performance-metrics').text('Hide Details');
            }
        },

        // Load detailed performance metrics
        loadPerformanceMetrics: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'holler_cache_get_performance_metrics',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updatePerformanceMetrics(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Performance metrics error:', status, error);
                }
            });
        },

        // Update detailed performance metrics
        updatePerformanceMetrics: function(metrics) {
            // Update cache operations stats
            if (metrics.cache_operations) {
                $('.purges-24h').text(metrics.cache_operations.purges_last_24h);
                $('.purges-7d').text(metrics.cache_operations.purges_last_7d);
                $('.avg-purge-time').text(metrics.cache_operations.avg_purge_time + 's');
            }

            // Update error rates
            if (metrics.error_rates) {
                $('.error-rate-24h').text(metrics.error_rates.error_rate_24h + '%');
                $('.total-errors').text(metrics.error_rates.total_errors);
            }

            // Update API usage
            if (metrics.api_usage) {
                $('.cf-requests-today').text(metrics.api_usage.cloudflare_requests_today);
                $('.cf-quota-remaining').text(metrics.api_usage.cloudflare_quota_remaining);
            }
        },

        // Auto-refresh functionality
        startAutoRefresh: function() {
            this.stopAutoRefresh();
            
            if (this.config.autoRefresh) {
                const self = this;
                this.state.refreshTimer = setInterval(function() {
                    self.loadDashboardData();
                }, this.config.refreshInterval);
            }
        },

        stopAutoRefresh: function() {
            if (this.state.refreshTimer) {
                clearInterval(this.state.refreshTimer);
                this.state.refreshTimer = null;
            }
        },

        // Error handling
        handleLoadError: function() {
            this.state.retryCount++;
            
            if (this.state.retryCount < this.config.maxRetries) {
                const self = this;
                setTimeout(function() {
                    self.loadDashboardData();
                }, this.config.retryDelay);
            } else {
                this.showError('Failed to load dashboard data after ' + this.config.maxRetries + ' attempts');
            }
        },

        // UI state management
        showLoadingState: function() {
            $('.dashboard-loading').show();
            $('.dashboard-refresh-btn').prop('disabled', true);
        },

        hideLoadingState: function() {
            $('.dashboard-loading').hide();
            $('.dashboard-refresh-btn').prop('disabled', false);
        },

        showSuccess: function(message) {
            this.showNotification(message, 'success');
        },

        showError: function(message) {
            this.showNotification(message, 'error');
        },

        showNotification: function(message, type) {
            const $notification = $('<div>')
                .addClass('dashboard-notification')
                .addClass('notification-' + type)
                .text(message);
            
            $('.dashboard-notifications').append($notification);
            
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        updateLastRefresh: function(timestamp) {
            const time = new Date(timestamp * 1000);
            $('.last-refresh-time').text(this.formatRelativeTime(time));
        },

        // Utility functions
        formatRelativeTime: function(date) {
            const now = new Date();
            const diff = now - date;
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);

            if (days > 0) return days + ' day' + (days > 1 ? 's' : '') + ' ago';
            if (hours > 0) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            if (minutes > 0) return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
            return 'Just now';
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        Dashboard.init();
    });

    // Expose Dashboard object globally for debugging
    window.HollerCacheDashboard = Dashboard;

})(jQuery);
