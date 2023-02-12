<?php

namespace WP_SMS\User\MobileFieldHandler;

use WP_SMS\Helper;
use WP_SMS\Option;

class WordPressMobileFieldHandler
{
    public function register()
    {
        add_action('user_new_form', array($this, 'add_mobile_field_to_newuser_form'));
        add_filter('wp_sms_user_profile_fields', array($this, 'add_mobile_field_to_profile_form'), 10, 2);

        add_action('register_form', array($this, 'add_mobile_field_to_register_form'));
        add_filter('registration_errors', array($this, 'frontend_registration_errors'), 10, 3);

        add_action('user_profile_update_errors', array($this, 'admin_registration_errors'), 10, 3);

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
        echo Helper::loadTemplate('mobile-field.php');
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
            'title'   => __('Mobile', 'wp-sms'),
            'content' => '<input class="wp-sms-input-mobile regular-text" type="text" name="mobile" value="' . esc_attr($currentValue) . '">'
        ];

        return $fields;
    }

    // add mobile filed input to add user in front-end
    public function add_mobile_field_to_register_form()
    {
        $mobile = (isset($_POST['mobile'])) ? Helper::sanitizeMobileNumber($_POST['mobile']) : '';

        echo Helper::loadTemplate('mobile-field-register.php', array(
            'mobile' => $mobile
        ));
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
        if (!Option::getOption('mobile_verify_optional', true) and empty($_POST['mobile'])) {
            $errors->add('first_name_error', __('<strong>ERROR</strong>: You must enter the mobile number.', 'wp-sms'));
        }

        if (isset($_POST['mobile']) and $_POST['mobile']) {
            $mobile   = Helper::sanitizeMobileNumber($_POST['mobile']);
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
        if (isset($_POST['mobile'])) {
            $mobile = Helper::sanitizeMobileNumber($_POST['mobile']);
            update_user_meta($user_id, $this->getUserMobileFieldName(), $mobile);
        }
    }

    /**
     * Handle the mobile field update errors
     *
     * @param $errors
     * @param $update
     * @param $user
     *
     * @return void|\WP_Error
     */
    public function admin_registration_errors($errors, $update, $user)
    {
        if (isset($_POST['mobile']) && $_POST['mobile']) {
            $mobile   = Helper::sanitizeMobileNumber($_POST['mobile']);
            $validity = Helper::checkMobileNumberValidity($mobile, isset($user->ID) ? $user->ID : false);

            if (is_wp_error($validity)) {
                $errors->add($validity->get_error_code(), $validity->get_error_message());
            }

            return $errors;
        }
    }
}
