<?php

namespace WP_SMS\Api\V1;

use WP_REST_Server;
use WP_REST_Request;
use WP_SMS\Admin\ModalHandler\Modal;
use WP_SMS\RestApi;
use WP_SMS\Utils\OptionUtil;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Notices REST API Controller
 *
 * Provides endpoints for dismissing and acting on admin notices
 * displayed in the React dashboard.
 */
class AdminNoticesApi extends RestApi
{
    /**
     * Allowed options that can be updated via the action endpoint
     *
     * @var array
     */
    private $allowedOptions = [
        'share_anonymous_data',
    ];

    /**
     * Allowed modal IDs that can be marked as seen via the REST endpoint
     *
     * @var array
     */
    private $allowedModalIds = [
        'welcome-premium',
    ];

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
        // Dismiss a notice
        register_rest_route($this->namespace . '/v1', '/admin-notices/dismiss', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'dismissNotice'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'store' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'enum'              => ['static', 'handler'],
                    ],
                ],
            ],
        ]);

        // Mark a modal as seen (syncs with legacy Modal::showOnce)
        register_rest_route($this->namespace . '/v1', '/admin-notices/modal-seen', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'markModalSeen'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        // Execute an action on a notice (e.g. enable anonymous data)
        register_rest_route($this->namespace . '/v1', '/admin-notices/action', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'executeAction'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'action_type' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'option' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'value' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Check if user has permission
     *
     * @return bool
     */
    public function checkPermission()
    {
        return current_user_can('manage_options');
    }

    /**
     * Dismiss a notice
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function dismissNotice(WP_REST_Request $request)
    {
        $id    = $request->get_param('id');
        $store = $request->get_param('store');

        if (empty($id) || !in_array($store, ['static', 'handler'], true)) {
            return self::response(__('Invalid parameters', 'wp-sms'), 400);
        }

        // Dismissing the recent normalization failures notice also clears the underlying log
        // — the message tells the admin "review and dismiss to clear" so this is the contract.
        if ($id === 'recent_phone_failures') {
            \WP_SMS\Helper::clearRecentNormalizationFailures();
        }

        if ($store === 'static') {
            // Static notices use wpsms_notices option (boolean flags keyed by ID)
            $notices      = get_option('wpsms_notices', []);
            $notices[$id] = true;
            update_option('wpsms_notices', $notices);

            // Activation notices also use a dedicated option to prevent re-registration
            if (preg_match('/^wp_sms_(.+)_activation$/', $id, $matches)) {
                update_option('wp_sms_' . $matches[1] . '_activation_notice_shown', true);
            }
        } else {
            // Handler notices use wp_sms_dismissed_notices option (array of IDs)
            $dismissed = get_option('wp_sms_dismissed_notices', []);
            if (!is_array($dismissed)) {
                $dismissed = [];
            }
            if (!in_array($id, $dismissed)) {
                $dismissed[] = $id;
                update_option('wp_sms_dismissed_notices', $dismissed);
            }

            // The number-migration notice uses a time-bounded dismissal: after 7 days
            // it comes back if there's still local-format data. Capture the dismissal
            // timestamp so Dashboard::getAdminNotices can compare.
            if ($id === 'number_migration') {
                update_option('wpsms_number_migration_notice_dismissed_at', time());
            }
        }

        return self::response(__('Notice dismissed', 'wp-sms'), 200, [
            'dismissed' => $id,
        ]);
    }

    /**
     * Mark a modal as seen (writes to the same storage as legacy Modal::showOnce)
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function markModalSeen(WP_REST_Request $request)
    {
        $id = $request->get_param('id');

        if (empty($id) || !in_array($id, $this->allowedModalIds, true)) {
            return self::response(__('Invalid parameters', 'wp-sms'), 400);
        }

        Modal::updateState($id);

        return self::response(__('Modal marked as seen', 'wp-sms'), 200, [
            'modal' => $id,
        ]);
    }

    /**
     * Execute an action on a notice (e.g. update option) and auto-dismiss
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function executeAction(WP_REST_Request $request)
    {
        $id          = $request->get_param('id');
        $actionType  = $request->get_param('action_type');
        $option      = $request->get_param('option');
        $value       = $request->get_param('value');

        if ($actionType !== 'update_option') {
            return self::response(__('Unsupported action type', 'wp-sms'), 400);
        }

        // Whitelist check — only allowed options can be updated
        if (!in_array($option, $this->allowedOptions, true)) {
            return self::response(__('Option not allowed', 'wp-sms'), 403);
        }

        // Update the option using OptionUtil (stores in wpsms_settings array)
        OptionUtil::update($option, sanitize_text_field($value));

        // Auto-dismiss via handler store
        $dismissed = get_option('wp_sms_dismissed_notices', []);
        if (!is_array($dismissed)) {
            $dismissed = [];
        }
        if (!in_array($id, $dismissed)) {
            $dismissed[] = $id;
            update_option('wp_sms_dismissed_notices', $dismissed);
        }

        return self::response(__('Action executed', 'wp-sms'), 200, [
            'dismissed' => $id,
            'option'    => $option,
            'value'     => $value,
        ]);
    }
}

new AdminNoticesApi();
