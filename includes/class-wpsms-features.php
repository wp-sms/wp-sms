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

        if (wp_sms_get_option('add_mobile_field')) {
            add_action('user_new_form', array($this, 'add_mobile_field_to_newuser_form'));
            add_filter('user_contactmethods', array($this, 'add_mobile_field_to_profile_form'));
            add_action('register_form', array($this, 'add_mobile_field_to_register_form'));
            add_filter('registration_errors', array($this, 'registration_errors'), 10, 3);
            add_action('user_register', array($this, 'save_register'));

            add_action('user_register', array($this, 'checkAdminDuplicateNumber'));
            add_action('profile_update', array($this, 'checkAdminDuplicateNumber'));

            add_action('user_profile_update_errors', array($this, 'MobileFieldErrors'), 10, 3);
        }

        if (wp_sms_get_option('international_mobile')) {
            add_action('wp_enqueue_scripts', array($this, 'load_international_input'));
            add_action('admin_enqueue_scripts', array($this, 'load_international_input'));
            add_action('login_enqueue_scripts', array($this, 'load_international_input'));
        }
    }

    /**
     * @param $mobileNumber
     * @param null $userID
     *
     * @return bool
     */
    private function checkMobileNumber($mobileNumber, $userID = null)
    {
        if ($userID) {
            $result = $this->db->get_results("SELECT * from `{$this->tb_prefix}usermeta` WHERE meta_key = '{$this->mobileField}' AND meta_value = '{$mobileNumber}' AND user_id != '{$userID}'");
        } else {
            $result = $this->db->get_results("SELECT * from `{$this->tb_prefix}usermeta` WHERE meta_key = '{$this->mobileField}' AND meta_value = '{$mobileNumber}'");
        }

        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     */
    private function delete_user_mobile($user_id)
    {
        $this->db->delete(
            $this->tb_prefix . "usermeta",
            array(
                'user_id'  => $user_id,
                'meta_key' => $this->mobileField,
            )
        );
    }

    /**
     * Handle the mobile field update errors
     *
     * @param $errors
     * @param $update
     * @param $user Object
     */
    public function MobileFieldErrors($errors, $update, $user)
    {

        // Check mobile duplicate value
        if (!empty($_POST['mobile'])) {

            if ($this->checkMobileNumber(sanitize_text_field($_POST['mobile']), $update ? $user->ID : null)) {
                $errors->add('mobile_error', __('This mobile is already registered, please choose another one.', 'wp-sms'));
            }
        }
    }

    /**
     * @param $userID
     */
    public function checkAdminDuplicateNumber($userID)
    {

        // Get user mobile
        $user_mobile = get_user_meta($userID, $this->mobileField, true);

        if (empty($user_mobile)) {
            return;
        }

        // Delete user mobile
        if ($this->checkMobileNumber($user_mobile, $userID)) {
            $this->delete_user_mobile($userID);
        }
    }

    public function add_mobile_field_to_newuser_form()
    {
        include_once WP_SMS_DIR . "includes/templates/mobile-field.php";
    }

    /**
     * @param $fields
     *
     * @return mixed
     */
    public function add_mobile_field_to_profile_form($fields)
    {
        $fields['mobile'] = __('Mobile', 'wp-sms');

        return $fields;
    }

    public function add_mobile_field_to_register_form()
    {
        $mobile = (isset($_POST['mobile'])) ? sanitize_text_field($_POST['mobile']) : '';
        include_once WP_SMS_DIR . "includes/templates/mobile-field-register.php";
    }

    /**
     * @param $errors
     * @param $sanitized_user_login
     * @param $user_email
     *
     * @return mixed
     */
    public function registration_errors($errors, $sanitized_user_login, $user_email)
    {
        if (!Option::getOption('mobile_verify_optional', true) and empty($_POST['mobile'])) {
            $errors->add('first_name_error', __('<strong>ERROR</strong>: You must enter the mobile number.', 'wp-sms'));
        }

        if (isset($_POST['mobile']) and !empty($_POST['mobile'])) {
            $error = false;

            $mobile = sanitize_text_field($_POST['mobile']);

            if (preg_match('/^[0-9\-\(\)\/\+\s]*$/', $mobile, $matches) == false) {
                $errors->add('invalid_mobile_number', __('Please enter a valid mobile number', 'wp-sms'));
                $error = true;
            }

            if (!$error && isset($matches[0]) && strlen($matches[0]) < 10) {
                $errors->add('invalid_mobile_number', __('Please enter a valid mobile number', 'wp-sms'));
                $error = true;
            }

            if (!$error && isset($matches[0]) && strlen($matches[0]) > 14) {
                $errors->add('invalid_mobile_number', __('Please enter a valid mobile number', 'wp-sms'));
            }

            if ($this->checkMobileNumber($mobile)) {
                $errors->add('duplicate_mobile_number', __('<strong>ERROR</strong>: This mobile is already registered, please choose another one.', 'wp-sms'));
            }
        }

        return $errors;
    }

    /**
     * @param $user_id
     */
    public function save_register($user_id)
    {
        if (isset($_POST['mobile'])) {
            $mobile = sanitize_text_field($_POST['mobile']);
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

        if (Option::getOption('international_mobile_auto_hide')) {
            $tel_intel_vars['auto_hide'] = true;
        } else {
            $tel_intel_vars['auto_hide'] = false;
        }

        if (Option::getOption('international_mobile_national_mode')) {
            $tel_intel_vars['national_mode'] = true;
        } else {
            $tel_intel_vars['national_mode'] = false;
        }

        $tel_intel_vars['util_js'] = WP_SMS_URL . 'assets/js/intel/utils.js';

        wp_localize_script('wpsms-intel-script', 'wp_sms_intel_tel_input', $tel_intel_vars);
    }


}

new Features();