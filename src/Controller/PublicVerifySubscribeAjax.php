<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Services\Subscriber\SubscriberUtil;

class PublicVerifySubscribeAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_verify_subscribe';
    public $requiredFields = [
        'name',
        'mobile',
        'activation',
    ];

    protected function run()
    {
        // Get parameters from request
        $name       = $this->get('name');
        $number     = $this->get('mobile');
        $group_id   = $this->get('group_id', 0);
        $groupIds   = is_array($group_id) ? $group_id : array($group_id);
        $activation = $this->get('activation');

        foreach ($groupIds as $groupId) {
            // Remove additional space and make compatible with auto-fill
            $activation = trim($activation);

            // Add subscribe to database
            $result = SubscriberUtil::verifySubscriber($name, $number, $activation, $groupId);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
        }

        return wp_send_json_success(esc_html__('Your mobile number has been successfully subscribed.', 'wp-sms'));
    }
}