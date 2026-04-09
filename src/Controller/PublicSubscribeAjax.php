<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Option;
use WP_SMS\Services\Subscriber\SubscriberUtil;

if (!defined('ABSPATH')) exit;

class PublicSubscribeAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_subscribe';
    /**
     * Public endpoint: visitors subscribe to the SMS newsletter without logging in.
     *
     * @var string|null
     */
    protected $capability = null;
    public $requiredFields = [
        'name',
        'mobile',
    ];

    protected function run()
    {
        // Check GDPR consent if enabled
        if (Option::getOption('gdpr_compliance') === '1' && !$this->get('gdpr_consent')) {
            throw new Exception(esc_html__('Please accept the privacy checkbox to continue.', 'wp-sms'));
        }

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
            throw new Exception(esc_html($result->get_error_message()));
        }

        return wp_send_json_success($result);
    }
}