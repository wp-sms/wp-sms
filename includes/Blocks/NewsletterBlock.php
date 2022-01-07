<?php

namespace WP_SMS\Blocks;

use WP_SMS\Blocks\BlockAbstract;
use WP_SMS\Blocks\Helper;

class NewsletterBlock extends BlockAbstract
{
    protected $blockName = 'newsletter';
    protected $blockVersion = '1.0';

    protected function output($attributes)
    {
	    $international_mobile = wp_sms_get_option('international_mobile');
	    $gdpr_compliance = wp_sms_get_option('gdpr_compliance');
	    $newsletter_form_gdpr_confirm_checkbox = wp_sms_get_option('newsletter_form_gdpr_confirm_checkbox');
	    $newsletter_form_gdpr_text = wp_sms_get_option('newsletter_form_gdpr_text');


        return Helper::loadTemplate('subscribe-form.php', [
            'attributes' => $attributes,
	        'international_mobile' => $international_mobile,
	        'gdpr_compliance' => $gdpr_compliance,
	        'newsletter_form_gdpr_confirm_checkbox' => $newsletter_form_gdpr_confirm_checkbox,
	        'newsletter_form_gdpr_text' => $newsletter_form_gdpr_text
        ]);
    }
}
