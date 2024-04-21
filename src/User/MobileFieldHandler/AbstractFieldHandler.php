<?php

namespace WP_SMS\User\MobileFieldHandler;

use WP_SMS\Option;
use WP_SMS\Helper;
use WP_Error;

abstract class AbstractFieldHandler
{
    
    abstract public function register();

    abstract public function getMobileNumberByUserId($userId);

    abstract public function getUserMobileFieldName();

    /**
     * Validate phone number upon being saved in profile page 
     *
     * @return $check
    */
    public function profilePhoneValidation($check, $objectId, $metaKey, $metaValue, $prevValue) 
    {
        if ($this->getUserMobileFieldName() == $metaKey) {
            $phoneNumber = $metaValue;

            // Check if the phone is not empty
            if (Option::getOption('optional_mobile_field') !== 'optional' && empty($phoneNumber)) {
                return false;
            }

            // Validate phone number
            if ($phoneNumber) {
                $mobile   = Helper::sanitizeMobileNumber($phoneNumber);
                $validity = Helper::checkMobileNumberValidity($mobile);

                if (is_wp_error($validity)) return false;
            }
        }
        
        return $check;
    }

    /**
     * Handle the mobile field validation errors on user profile page 
     *
     * @param $errors
     * @param $update
     * @param $user
     *
     * @return void|WP_Error
    */
    public function profilePhoneValidationError($errors, $update, $user)
    {
        $phoneNumber = isset($_POST[$this->getUserMobileFieldName()]) ? $_POST[$this->getUserMobileFieldName()] : null;

        // Check if the phone is not empty
        if (Option::getOption('optional_mobile_field') !== 'optional' && empty($phoneNumber)) {
            $errors->add('mobile_number_error', __('<strong>ERROR</strong>: You must enter the mobile number.', 'wp-sms'));
        }

        // Validate phone number
        if ($phoneNumber) {
            $mobile   = Helper::sanitizeMobileNumber($phoneNumber);
            $validity = Helper::checkMobileNumberValidity($mobile, isset($user->ID) ? $user->ID : false);

            if (is_wp_error($validity)) {
                $errors->add($validity->get_error_code(), $validity->get_error_message());
            }
        }

        return $errors;
    }
}
