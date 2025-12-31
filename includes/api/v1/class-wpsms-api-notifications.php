<?php

namespace WP_SMS\Api\V1;

use WP_REST_Server;
use WP_REST_Request;
use WP_SMS\RestApi;
use WP_SMS\Admin\Notification\NotificationFactory;
use WP_SMS\Admin\Notification\NotificationProcessor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Notifications REST API Controller
 *
 * Provides endpoints for fetching and managing admin notifications
 * in the new React-based settings dashboard.
 */
class NotificationsApi extends RestApi
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        parent::__construct();
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes()
    {
        // Get all notifications
        register_rest_route($this->namespace . '/v1', '/notifications', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getNotifications'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // Dismiss notification(s)
        register_rest_route($this->namespace . '/v1', '/notifications/dismiss', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'dismissNotification'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'Notification ID or "all" to dismiss all',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Check if user has permission to access notifications
     *
     * @return bool
     */
    public function checkPermission()
    {
        return current_user_can('manage_options');
    }

    /**
     * Get all notifications
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getNotifications(WP_REST_Request $request)
    {
        $decoratedNotifications = NotificationFactory::getAllNotifications();
        $notifications = [];

        foreach ($decoratedNotifications as $notification) {
            $notifications[] = [
                'id'              => $notification->getID(),
                'title'           => $notification->getTitle(),
                'icon'            => $notification->getIcon(),
                'description'     => $notification->getDescription(),
                'activatedAt'     => $notification->activatedAt(),
                'backgroundColor' => $this->mapBackgroundColor($notification->backgroundColor()),
                'primaryButton'   => $this->formatButton(
                    $notification->primaryButtonTitle(),
                    $notification->primaryButtonUrl()
                ),
                'secondaryButton' => $this->formatButton(
                    $notification->secondaryButtonTitle(),
                    $notification->secondaryButtonUrl()
                ),
                'dismissed'       => (bool) $notification->getDismiss(),
            ];
        }

        $unreadCount = NotificationFactory::getNewNotificationCount();
        $hasUnread = NotificationFactory::hasUpdatedNotifications();

        return self::response(__('Notifications retrieved successfully', 'wp-sms'), 200, [
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'hasUnread'     => $hasUnread,
        ]);
    }

    /**
     * Dismiss notification(s)
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function dismissNotification(WP_REST_Request $request)
    {
        $id = $request->get_param('id');

        if (empty($id)) {
            return self::response(__('Missing notification ID', 'wp-sms'), 400);
        }

        if ($id === 'all') {
            NotificationProcessor::dismissAllNotifications();
            return self::response(__('All notifications dismissed', 'wp-sms'), 200, [
                'dismissed' => 'all',
            ]);
        }

        if (!ctype_digit($id) || (int) $id <= 0) {
            return self::response(__('Invalid notification ID', 'wp-sms'), 400);
        }

        NotificationProcessor::dismissNotification((int) $id);

        return self::response(__('Notification dismissed', 'wp-sms'), 200, [
            'dismissed' => (int) $id,
        ]);
    }

    /**
     * Map CSS class to color name for frontend
     *
     * @param string|null $cssClass
     * @return string
     */
    private function mapBackgroundColor($cssClass)
    {
        $mapping = [
            'wpsms-notification-sidebar__danger'  => 'danger',
            'wpsms-notification-sidebar__info'    => 'info',
            'wpsms-notification-sidebar__warning' => 'warning',
            'wpsms-notification-sidebar__success' => 'success',
        ];

        return $mapping[$cssClass] ?? '';
    }

    /**
     * Format button data for response
     *
     * @param string|null $title
     * @param string|null $url
     * @return array|null
     */
    private function formatButton($title, $url)
    {
        if (empty($title) || empty($url)) {
            return null;
        }

        return [
            'title' => $title,
            'url'   => $url,
        ];
    }
}

new NotificationsApi();
