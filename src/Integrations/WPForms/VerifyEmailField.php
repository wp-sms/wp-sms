<?php

namespace WSms\Integrations\WPForms;

defined('ABSPATH') || exit;

class VerifyEmailField extends WPFormsVerifyField
{
    protected function initField(): void
    {
        $this->name     = esc_html__('WSMS: Verify Email', 'wp-sms');
        $this->type     = 'wsms_verify_email';
        $this->icon     = 'fa-envelope-o';
        $this->order    = 300;
        $this->group    = 'fancy';
        $this->keywords = esc_html__('email, verify, otp, wsms', 'wp-sms');
        $this->channel  = 'email';
    }
}
