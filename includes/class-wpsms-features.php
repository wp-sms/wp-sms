<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Features
{
    public $sms;
    public $date;
    public $options;

    protected $db;
    protected $tb_prefix;
    private $mobileField;

    /**
     * WP_SMS_Features constructor.
     */
    public function __construct()
    {
        global $sms, $wpdb;

        $this->sms         = $sms;
        $this->db          = $wpdb;
        $this->tb_prefix   = $wpdb->prefix;
        $this->date        = WP_SMS_CURRENT_DATE;
        $this->options     = Option::getOptions();
        $this->mobileField = Helper::getUserMobileFieldName();

        $this->add_wpsms_user_profile_fields_group();

        if (wp_sms_get_option('add_mobile_field')) {
            add_action('user_new_form', array($this, 'add_mobile_field_to_newuser_form'));
            add_filter('wp_sms_user_profile_fields', array($this, 'add_mobile_field_to_profile_form'), 10, 2);

            add_action('register_form', array($this, 'add_mobile_field_to_register_form'));
            add_filter('registration_errors', array($this, 'frontend_registration_errors'), 10, 3);
            add_action('user_register', array($this, 'save_register'), 999999);

            add_action('user_profile_update_errors', array($this, 'admin_registration_errors'), 10, 3);
        }

        if (wp_sms_get_option('international_mobile')) {
            add_action('wp_enqueue_scripts', array($this, 'load_international_input'), 999999);
            add_action('admin_enqueue_scripts', array($this, 'load_international_input'), 999999);
            add_action('login_enqueue_scripts', array($this, 'load_international_input'), 999999);
        }
    }

    /**
     * Add WPSMS fields to user profile
     *
     * @return void
     */
    private function add_wpsms_user_profile_fields_group()
    {
        $renderFields = function ($user) {
            $fields = apply_filters('wp_sms_user_profile_fields', [], $user->ID);
            if (empty($fields)) {
                return;
            }

            echo Helper::loadTemplate('admin/user-profile-fields.php', [
                'fields' => $fields,
            ]);
        };

        add_action('show_user_profile', $renderFields);
        add_action('edit_user_profile', $renderFields);

        $saveFields = function ($userId) {
            $fields = apply_filters('wp_sms_user_profile_fields', [], $userId);
            foreach ($fields as $field) {
                if (isset($field['saveCallback']) && is_callable($field['saveCallback'])) {
                    call_user_func($field['saveCallback'], $userId);
                }
            }
        };

        add_action('personal_options_update', $saveFields);
        add_action('edit_user_profile_update', $saveFields);
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
        $mobileFieldName = Helper::getUserMobileFieldName();
        $currentValue    = Helper::getUserMobileNumberByUserId($userId);

        $fields['mobile'] = [
            'id'           => 'mobile',
            'title'        => __('Mobile', 'wp-sms'),
            'content'      => '<input class="wp-sms-input-mobile" type="text" name="mobile" value="' . esc_attr($currentValue) . '">',
            'saveCallback' => function ($userId) use ($mobileFieldName) {
                if (isset($_POST['mobile'])) {
                    $value = Helper::sanitizeMobileNumber($_POST['mobile']);
                    update_user_meta($userId, $mobileFieldName, $value);
                }
            }
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

    /**
     * save user mobile number in database
     *
     * @param $user_id
     */
    public function save_register($user_id)
    {
        if (isset($_POST['mobile'])) {
            $mobile = Helper::sanitizeMobileNumber($_POST['mobile']);
            update_user_meta($user_id, $this->mobileField, $mobile);
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.2.0
     */
    public function load_international_input()
    {
        //Register IntelTelInput Assets
        wp_enqueue_style('wpsms-intel-tel-input', WP_SMS_URL . 'assets/css/intlTelInput.min.css', true, '17.0.0');
        wp_enqueue_script('wpsms-intel-tel-input', WP_SMS_URL . 'assets/js/intel/intlTelInput.min.js', array('jquery'), '17.0.0', true);
        wp_enqueue_script('wpsms-intel-script', WP_SMS_URL . 'assets/js/intel/intel-script.js', true, WP_SMS_VERSION, true);

        // Localize the IntelTelInput
        $tel_intel_vars             = array();
        $only_countries_option      = Option::getOption('international_mobile_only_countries');
        $preferred_countries_option = Option::getOption('international_mobile_preferred_countries');

        if ($only_countries_option) {
            $tel_intel_vars['only_countries'] = $only_countries_option;
        } else {
            $tel_intel_vars['only_countries'] = '';
        }

        if ($preferred_countries_option) {
            $tel_intel_vars['preferred_countries'] = $preferred_countries_option;
        } else {
            $tel_intel_vars['preferred_countries'] = '';
        }

        $tel_intel_vars['util_js'] = WP_SMS_URL . 'assets/js/intel/utils.js';

        wp_localize_script('wpsms-intel-script', 'wp_sms_intel_tel_input', $tel_intel_vars);
    }
}

new Features();
