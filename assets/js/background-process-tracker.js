(function($) {
    'use strict';

    var WpSmsBackgroundProcessTracker = {
        interval: null,
        isCompleted: false,
        noticeSelector: '#wp-sms-async-background-process-notice',

        init: function() {
            if (typeof Wp_Sms_Async_Background_Process_Data === 'undefined') {
                return;
            }

            var data = Wp_Sms_Async_Background_Process_Data;

            if (!data.current_process) {
                return;
            }

            // Start polling for progress updates
            this.startPolling(data);
        },

        startPolling: function(data) {
            var self = this;
            var pollInterval = data.interval || 5000;

            // Initial check
            this.checkProgress(data);

            this.interval = setInterval(function() {
                if (!self.isCompleted) {
                    self.checkProgress(data);
                }
            }, pollInterval);
        },

        checkProgress: function(data) {
            var self = this;

            if (this.isCompleted) {
                this.stopPolling();
                return;
            }

            $.ajax({
                url: data.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_sms_background_process_progress',
                    process_key: data.current_process,
                    _wpnonce: data.rest_api_nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateProgress(response.data, data);
                    }
                },
                error: function() {
                    // Silently fail - will retry on next interval
                }
            });
        },

        updateProgress: function(progressData, config) {
            var $notice = $(this.noticeSelector);

            if (!$notice.length) {
                return;
            }

            // Check if completed
            if (progressData.completed) {
                this.isCompleted = true;
                this.stopPolling();
                this.showCompletedNotice(config);
                return;
            }

            // Check for error
            if (progressData.has_error) {
                this.stopPolling();
                this.showErrorNotice(progressData.error_message);
                return;
            }

            // Update percentage
            var $percentage = $notice.find('.percentage');
            if ($percentage.length) {
                $percentage.text(progressData.percentage + '%');
            }

            // Update processed count
            var $processed = $notice.find('.processed');
            if ($processed.length) {
                $processed.text(progressData.processed);
            }
        },

        showErrorNotice: function(errorMessage) {
            var $notice = $(this.noticeSelector).closest('.notice');

            $notice
                .removeClass('notice-info')
                .addClass('notice-error')
                .html('<p><strong>' + errorMessage + '</strong></p>');
        },

        showCompletedNotice: function(config) {
            var $notice = $(this.noticeSelector).closest('.notice');
            var message = config.job_completed_message || config.completed_message;

            $notice
                .removeClass('notice-info')
                .addClass('notice-success')
                .html('<p><strong>' + message + '</strong></p>');

            // Remove notice after 10 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 10000);
        },

        stopPolling: function() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        }
    };

    $(document).ready(function() {
        WpSmsBackgroundProcessTracker.init();
    });

})(jQuery);
