<?php

namespace WP_SMS\User;

use WP_SMS\Option;

class MobileFieldManager
{
    private $mobileFieldHandler = [
        'disable'                        => \WP_SMS\User\MobileFieldHandler\DefaultFieldHandler::class,
        'add_mobile_field_in_profile'    => \WP_SMS\User\MobileFieldHandler\WordPressMobileFieldHandler::class,
        'add_mobile_field_in_wc_billing' => \WP_SMS\User\MobileFieldHandler\WooCommerceAddMobileFieldHandler::class,
        'use_phone_field_in_wc_billing'  => \WP_SMS\User\MobileFieldHandler\WooCommerceUsePhoneFieldHandler::class,
    ];

    public function getHandler()
    {
        $field = wp_sms_get_option('add_mobile_field');

        $this->mobileFieldHandler = apply_filters('wp_sms_mobile_filed_handler', $this->mobileFieldHandler);

        if (isset($this->mobileFieldHandler[$field]) && class_exists($this->mobileFieldHandler[$field])) {
            return new $this->mobileFieldHandler[$field];
        }

        /**
         * WooCommerce Backward compatibility
         * This will use the exists billing phone field in checkout even the option is not configured.
         */
        if (class_exists('WooCommerce')) {
            Option::updateOption('add_mobile_field', 'use_phone_field_in_wc_billing');

            return new $this->mobileFieldHandler['use_phone_field_in_wc_billing'];
        }

        /**
         * Old version Backward compatibility
         */
        Option::updateOption('add_mobile_field', 'disable');

        return new $this->mobileFieldHandler['disable'];
    }

    public function init()
    {
        $handler = $this->getHandler();

        if ($handler) {
            $handler->register();
        }
    }
}
