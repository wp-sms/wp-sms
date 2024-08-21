<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Option;
use WP_SMS\Services\Subscriber\SubscriberUtil;

class PublicSubscribeAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_subscribe';
    public $requiredFields = [
        'name',
        'mobile',
    ];

    protected function run()
    {
        $name           = $this->get('name');
        $number         = $this->get('mobile');
        $customFields   = $this->get('custom_fields');
        $group_id       = $this->get('group_id', 0);
        $groups_enabled = Option::getOption('newsletter_form_groups');

        //  If admin enabled groups and user did not select any group, then return error
        if ($groups_enabled && !$group_id) {
            throw new Exception(esc_html__('Please select a specific group.', 'wp-sms'));
        }

        $result = SubscriberUtil::subscribe($name, $number, $group_id, $customFields);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        return wp_send_json_success($result);
    }
}