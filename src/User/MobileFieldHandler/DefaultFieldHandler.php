<?php

namespace WP_SMS\User\MobileFieldHandler;

class DefaultFieldHandler extends AbstractFieldHandler
{
    public function register()
    {
    }

    public function getMobileNumberByUserId($userId)
    {
        return apply_filters('wp_sms_user_mobile_number', '', $userId);
    }

    public function getUserMobileFieldName()
    {
        return apply_filters('wp_sms_user_mobile_field', 'mobile');
    }
}
