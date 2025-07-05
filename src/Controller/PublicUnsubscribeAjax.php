<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Settings\Option;
use WP_SMS\Services\Subscriber\SubscriberUtil;

class PublicUnsubscribeAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_unsubscribe';
    public $requiredFields = [
        'name',
        'mobile',
    ];

    protected function run()
    {
        $name           = $this->get('name');
        $number         = $this->get('mobile');
        $group_id       = $this->get('group_id', 0);
        $groups_enabled = Option::getOption('newsletter_form_groups');

        //  If admin enabled groups and user did not select any group, then return error
        if ($groups_enabled && !$group_id) {
            throw new Exception(esc_html__('Please select a specific group.', 'wp-sms'));
        }

        $groupIds = is_array($group_id) ? $group_id : array($group_id);

        foreach ($groupIds as $groupId) {
            $result = SubscriberUtil::unSubscribe($name, $number, $groupId);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
        }

        return wp_send_json_success(esc_html__('Your mobile number has been successfully unsubscribed.', 'wp-sms'));
    }
}