<?php

namespace WP_SMS\Controller;

use WP_SMS\Helper;

class UserRolesMobileCountAjax extends AjaxControllerAbstract
{
    /**
     * Action slug used for admin-ajax and nonce.
     * Nonce name => 'wp_sms_get_user_roles_mobile_count'
     */
    protected $action = 'wp_sms_get_user_roles_mobile_count';

    protected function run()
    {
        $result = Helper::getUsersMobileNumberCountsWithRoleDetails();

        $roles = [];
        if (isset($result['roles']) && is_array($result['roles'])) {
            foreach ($result['roles'] as $role_key => $role_data) {
                $roles[] = [
                    'id'    => $role_key,
                    'name'  => $role_data['name'] ?? '',
                    'count' => isset($role_data['count']) ? (int)$role_data['count'] : 0,
                ];
            }
        }

        $total = isset($result['total']['count']) ? (int)$result['total']['count'] : 0;

        wp_send_json_success([
            'total_mobile_count' => $total,
            'roles'              => $roles,
        ]);
    }
}
