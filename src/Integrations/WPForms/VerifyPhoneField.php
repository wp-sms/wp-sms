<?php

namespace WSms\Integrations\WPForms;

defined('ABSPATH') || exit;

class VerifyPhoneField extends WPFormsVerifyField
{
    protected function initField(): void
    {
        $this->name     = esc_html__('WSMS: Verify Phone', 'wp-sms');
        $this->type     = 'wsms_verify_phone';
        $this->icon     = 'fa-phone';
        $this->order    = 310;
        $this->group    = 'fancy';
        $this->keywords = esc_html__('phone, verify, otp, sms, wsms', 'wp-sms');
        $this->channel  = 'phone';
    }
}
