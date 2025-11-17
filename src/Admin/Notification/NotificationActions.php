<?php

namespace WP_SMS\Admin\Notification;

use WP_SMS\Traits\AjaxUtilityTrait;
use WP_SMS\Components\Ajax;
use WP_SMS\Utils\Request;
use Exception;

class NotificationActions
{
    use AjaxUtilityTrait;

    /**
     * Register actions.
     *
     * @return void
     */
    public function register()
    {
        Ajax::register('dismiss_notification', [$this, 'dismissNotification']);
    }

    /**
     * Handles AJAX request to dismiss notifications.
     *
     * @return void Outputs JSON response and exits execution.
     */
    public function dismissNotification()
    {
        try {
            $this->verifyAjaxRequest();
            $this->checkAdminReferrer('wp_rest', 'wpsms_nonce');
            $this->checkCapability('manage_options');

            $rawId = Request::get('notification_id');

            if (empty($rawId)) {
                throw new Exception(esc_html__('Missing notification_id parameter.', 'wp-sms'), 400);
            }

            if ($rawId === 'all') {
                NotificationProcessor::dismissAllNotifications();

                Ajax::success(esc_html__('All notifications have been dismissed.', 'wp-sms'));
            }

            if (!ctype_digit($rawId) || $rawId <= 0) {
                throw new Exception(esc_html__('Invalid notification_id.', 'wp-sms'), 400);
            }

            $notificationId = (int)$rawId;
            NotificationProcessor::dismissNotification($notificationId);

            Ajax::success(esc_html__('Notification has been dismissed.', 'wp-sms'));
        } catch (Exception $e) {
            Ajax::error($e->getMessage(), null, $e->getCode());
        }
    }
}