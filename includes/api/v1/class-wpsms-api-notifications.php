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
                'badge'           => $notification->getBadge(),
                'code'            => $notification->getCode(),
                'highlight'       => $notification->getHighlight(),
                'endsAt'          => $notification->getEndsAt(),
                'dismissed'       => (bool) $notification->getDismiss(),
            ];
        }

        // Offers come first, then licence problems (local, cannot be dismissed), then the rest.
        $isOffer = function ($n) {
            return ($n['backgroundColor'] ?? '') === 'promo' && empty($n['dismissed']);
        };
        $offers = array_values(array_filter($notifications, $isOffer));
        $others = array_values(array_filter($notifications, function ($n) use ($isOffer) {
            return !$isOffer($n);
        }));

        $licenseNotice = $this->licenseNotice();
        $notifications = array_merge($offers, $licenseNotice ? [$licenseNotice] : [], $others);

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
     * The license alert as a notification, or null when everything is in order.
     *
     * @return array|null
     */
    private function licenseNotice()
    {
        $alerts = \WP_SMS\Admin\LicenseManagement\LicenseHelper::getLicenseAlerts();

        if (empty($alerts['count'])) {
            return null;
        }

        $addonsUrl = admin_url('admin.php?page=wsms&tab=add-ons');
        $renew     = $alerts['renew'];
        $offer     = $renew['offer'] ?? null;
        $expired   = $alerts['expired'] > 0;

        $inactive = !$expired && $alerts['missing'] === 0;

        if ($expired) {
            /* translators: %d: number of add-ons */
            $title       = sprintf(_n('License expired for %d add-on', 'Licenses expired for %d add-ons', $alerts['expired'], 'wp-sms'), $alerts['expired']);
            $description = __('Updates and support stop until you renew.', 'wp-sms');
        } elseif ($inactive) {
            /* translators: %d: number of add-ons */
            $title       = sprintf(_n('Activate %d add-on', 'Activate %d add-ons', $alerts['inactive'], 'wp-sms'), $alerts['inactive']);
            $description = __('It is licensed and installed, but turned off.', 'wp-sms');
        } else {
            /* translators: %d: number of add-ons */
            $title       = sprintf(_n('License missing for %d add-on', 'Licenses missing for %d add-ons', $alerts['missing'], 'wp-sms'), $alerts['missing']);
            $description = __('Enter your license key to unlock updates and support.', 'wp-sms');
        }

        if ($expired && $offer && ($offer['discount_type'] ?? '') === 'percentage' && !empty($offer['discount_value'])) {
            /* translators: %s: discount percent */
            $description .= ' ' . sprintf(__('Renew now and save %s%%.', 'wp-sms'), rtrim(rtrim(number_format((float) $offer['discount_value'], 2, '.', ''), '0'), '.'));
        }

        return [
            'id'              => 'license-alert',
            'title'           => $title,
            'icon'            => $inactive ? '🔌' : '🔑',
            'description'     => esc_html($description),
            'activatedAt'     => null,
            'backgroundColor' => $expired ? 'danger' : ($inactive ? 'info' : 'warning'),
            'primaryButton'   => $expired && !empty($renew['renew_url'])
                ? ['title' => __('Renew license', 'wp-sms'), 'url' => $renew['renew_url']]
                : ['title' => $inactive ? __('Open Add-Ons', 'wp-sms') : __('Enter license', 'wp-sms'), 'url' => $addonsUrl],
            'secondaryButton' => $expired && !empty($renew['renew_url'])
                ? ['title' => __('Manage licenses', 'wp-sms'), 'url' => $addonsUrl]
                : null,
            'dismissed'       => false,
            'localOnly'       => true,
        ];
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
            'wpsms-notification-sidebar__promo'   => 'promo',
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
