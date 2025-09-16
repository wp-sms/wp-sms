<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Newsletter;
use WP_SMS\Option;
use WP_SMS\Services\Subscriber\SubscriberUtil;

class PublicUnsubscribeAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_unsubscribe';
    public $requiredFields = [
        'name',
        'mobile',
    ];

    /**
     * @throws Exception
     */
    protected function run()
    {
        $name           = $this->get('name');
        $number         = $this->get('mobile');
        $group_id       = $this->get('group_id', 0);
        $groups_enabled = Option::getOption('newsletter_form_groups');

        if (!Newsletter::getSubscriberByMobile($number)) {
            throw new Exception(esc_html__('The provided mobile number is not subscribed.', 'wp-sms'));
        }

        $groupIds = is_array($group_id) ? $group_id : array($group_id);

        if ($groups_enabled && !empty(array_filter($groupIds))) {
            $subscriberGroups = Newsletter::getSubscriberGroupsByNumber($number);

            if (empty($subscriberGroups)) {
                $groupIds = array();
            } else if (!Newsletter::subscriberExistsInGroup($number, $group_id)) {
                throw new Exception(esc_html__('This mobile number is not subscribed to the selected group(s).', 'wp-sms'));
            }
        }

        if (!empty($groupIds)) {
            foreach ($groupIds as $groupId) {
                $result = SubscriberUtil::unSubscribe($name, $number, $groupId);

                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
            }
        } else {
            $result = SubscriberUtil::unSubscribe($name, $number);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
        }

        wp_send_json_success(esc_html__('You have successfully unsubscribed from the newsletter.', 'wp-sms'));
    }
}
