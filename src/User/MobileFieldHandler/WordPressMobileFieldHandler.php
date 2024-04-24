<?php

namespace WP_SMS\User\MobileFieldHandler;

use WP_SMS\Helper;
use WP_SMS\Option;

class WordPressMobileFieldHandler extends AbstractFieldHandler
{
    public function register()
    {
        add_action('user_new_form', array($this, 'add_mobile_field_to_newuser_form'));
        add_filter('wp_sms_user_profile_fields', array($this, 'add_mobile_field_to_profile_form'), 10, 2);

        add_action('register_form', array($this, 'add_mobile_field_to_register_form'));
        add_filter('registration_errors', array($this, 'frontend_registration_errors'), 10, 3);

        add_action('user_profile_update_errors', array($this, 'profilePhoneValidationError'), 10, 3);
        add_action('update_user_metadata', array($this, 'profilePhoneValidation'), 10, 5);

        add_action('user_register', array($this, 'updateMobileNumberCallback'), 999999);
        add_action('profile_update', array($this, 'updateMobileNumberCallback'));
    }

    public function getMobileNumberByUserId($userId)
    {
        $mobileNumber = get_user_meta($userId, $this->getUserMobileFieldName(), true);
        return apply_filters('wp_sms_user_mobile_number', $mobileNumber, $userId);
    }

    public function getUserMobileFieldName()
    {
        return apply_filters('wp_sms_user_mobile_field', 'mobile');
    }

    // add mobile field input to add user admin page
    public function add_mobile_field_to_newuser_form()
    {
        echo Helper::loadTemplate('mobile-field.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * @param $fields
     * @param $userId
     * @return mixed
     */
    public function add_mobile_field_to_profile_form($fields, $userId)
    {
        $currentValue = Helper::getUserMobileNumberByUserId($userId);

        $fields['mobile'] = [
            'id'      => 'mobile',
            'title'   => esc_html__('Mobile', 'wp-sms'),
            'content' => '<input class="wp-sms-input-mobile regular-text ltr" type="tel" name="mobile" value="' . esc_attr($currentValue) . '">'
        ];

        return $fields;
    }

    // add mobile filed input to add user in front-end
    public function add_mobile_field_to_register_form()
    {
        $mobile = (isset($_POST['mobile'])) ? Helper::sanitizeMobileNumber($_POST['mobile']) : '';

        $args = [
            'mobile' => $mobile
        ];

        echo Helper::loadTemplate('mobile-field-register.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Handle errors for registration through the front-end WordPress login form
     *
     * @param $errors
     * @param $sanitized_user_login
     * @param $user_email
     *
     * @return mixed
     */
    public function frontend_registration_errors($errors, $sanitized_user_login, $user_email)
    {
        $mobile_number = isset($_POST['mobile']) ? $_POST['mobile'] : (isset($_POST['phone_number']) ? $_POST['phone_number'] : null);
        if (Option::getOption('optional_mobile_field', false) !== 'optional' && !$mobile_number) {
            $errors->add('mobile_number_error', __('<strong>ERROR</strong>: You must enter the mobile number.', 'wp-sms'));
        }

        if ($mobile_number) {
            $mobile   = Helper::sanitizeMobileNumber($mobile_number);
            $validity = Helper::checkMobileNumberValidity($mobile);

            if (is_wp_error($validity)) {
                $errors->add($validity->get_error_code(), $validity->get_error_message());
            }
        }

        return $errors;
    }

    /**
     * save user mobile number in database
     *
     * @param $user_id
     */
    public function updateMobileNumberCallback($user_id)
    {
        $mobile_number = isset($_POST['mobile']) ? $_POST['mobile'] : null;
        if ($mobile_number) {
            $mobile = Helper::sanitizeMobileNumber($mobile_number);
            update_user_meta($user_id, $this->getUserMobileFieldName(), $mobile);
        }
    }
}
