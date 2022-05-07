<?php

namespace WP_SMS\Blocks;

use WP_SMS;
use WP_SMS\Option;
use WP_SMS\Newsletter;
use WP_SMS\Helper;

class SubscribeBlock extends BlockAbstract
{
    protected $blockName = 'subscribe';
    protected $blockVersion = '1.0';

    protected function output($attributes)
    {
        $international_mobile                 = wp_sms_get_option('international_mobile');
        $gdpr_compliance                      = wp_sms_get_option('gdpr_compliance');
        $subscribe_form_gdpr_confirm_checkbox = wp_sms_get_option('newsletter_form_gdpr_confirm_checkbox');
        $subscribe_form_gdpr_text             = wp_sms_get_option('newsletter_form_gdpr_text');
        $specified_groups_ids_for_widget      = wp_sms_get_option('newsletter_form_specified_groups');
        $get_group_result                     = Newsletter::getGroups($specified_groups_ids_for_widget);


        return Helper::loadTemplate(
            'subscribe-form.php',
            [
                'attributes'                           => $attributes,
                'international_mobile'                 => $international_mobile,
                'gdpr_compliance'                      => $gdpr_compliance,
                'subscribe_form_gdpr_confirm_checkbox' => $subscribe_form_gdpr_confirm_checkbox,
                'subscribe_form_gdpr_text'             => $subscribe_form_gdpr_text,
                'get_group_result'                     => $get_group_result,
            ]
        );
    }
}
