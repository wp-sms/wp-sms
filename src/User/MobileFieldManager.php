<?php

namespace WP_SMS\User;

class MobileFieldManager
{
    private $mobileFieldHandler = [
        'add_mobile_field_in_profile'    => \WP_SMS\User\MobileFieldHandler\WordPressMobileFieldHandler::class,
        'add_mobile_field_in_wc_billing' => \WP_SMS\User\MobileFieldHandler\WooCommerceAddMobileFieldHandler::class,
        'use_phone_field_in_wc_billing'  => \WP_SMS\User\MobileFieldHandler\WooCommerceUsePhoneFieldHandler::class,
    ];

    public function getHandler()
    {
        $field = wp_sms_get_option('add_mobile_field');

        if (isset($this->mobileFieldHandler[$field]) && class_exists($this->mobileFieldHandler[$field])) {
            return new $this->mobileFieldHandler[$field];
        }
    }

    public function init()
    {
        $handler = $this->getHandler();

        if ($handler) {
            $handler->register();
        }
    }
}
