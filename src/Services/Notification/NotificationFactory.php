<?php

namespace WP_SMS\Services\Notification;

class NotificationFactory
{
    /**
     * Retrieves all notifications after processing and filtering.
     *
     * @return array Processed and decorated notifications.
     */
    public static function getAllNotifications()
    {
        $rawNotifications = get_option('wp_sms_notifications', []);
        $notifications    = NotificationProcessor::filterNotificationsByTags($rawNotifications['data'] ?? []);

        return NotificationProcessor::decorateNotifications($notifications);
    }

    /**
     * Retrieves the raw notification data from WordPress options.
     *
     * @return array The raw notification data stored in the database.
     */
    public static function getRawNotificationsData()
    {
        return get_option('wp_sms_notifications', []);
    }

    /**
     * Checks if there are updated notifications.Services/Notification/NotificationFactory
     *
     * @return bool
     */
    public static function hasUpdatedNotifications()
    {
        $rawNotifications = get_option('wp_sms_notifications', []);

        if (!is_array($rawNotifications)) {
            return false;
        }

        return !empty($rawNotifications['updated']) ? (bool)$rawNotifications['updated'] : false;
    }
}