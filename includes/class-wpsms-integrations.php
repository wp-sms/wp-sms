<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Integrations
{

    public $sms;
    public $date;
    public $options;
    public $cf7_data;

    public function __construct()
    {
        global $sms;

        $this->sms     = $sms;
        $this->date    = WP_SMS_CURRENT_DATE;
        $this->options = Option::getOptions();

        // Contact Form 7
        if (isset($this->options['cf7_metabox'])) {
            add_filter('wpcf7_editor_panels', array($this, 'cf7_editor_panels'));
            add_action('wpcf7_after_save', array($this, 'wpcf7_save_form'));
            add_action('wpcf7_before_send_mail', array($this, 'wpcf7_sms_handler'));
        }
    }

    public function cf7_editor_panels($panels)
    {
        $new_page = array(
            'wpsms' => array(
                'title'    => __('SMS Notification', 'wp-sms'),
                'callback' => array($this, 'cf7_setup_form')
            )
        );

        $panels = array_merge($panels, $new_page);

        return $panels;
    }

    public function cf7_setup_form($form)
    {
        $cf7_options       = get_option('wpcf7_sms_' . $form->id());
        $cf7_options_field = get_option('wpcf7_sms_form' . $form->id());

        include_once WP_SMS_DIR . "includes/templates/wpcf7-form.php";
    }

    public function wpcf7_save_form($form)
    {
        update_option('wpcf7_sms_' . $form->id(), wp_sms_sanitize_array($_POST['wpcf7-sms']));
        update_option('wpcf7_sms_form' . $form->id(), wp_sms_sanitize_array($_POST['wpcf7-sms-form']));
    }

    public function wpcf7_sms_handler($form)
    {
        $cf7_options       = get_option('wpcf7_sms_' . $form->id());
        $cf7_options_field = get_option('wpcf7_sms_form' . $form->id());
        $this->set_cf7_data();

        /**
         * Send SMS to the specific number
         */
        if ($cf7_options['message'] && $cf7_options['phone']) {
            $this->sms->to = explode(',', $cf7_options['phone']);

            $this->sms->msg = preg_replace_callback('/%([a-zA-Z0-9._-]+)%/', function ($matches) {
                foreach ($matches as $item) {
                    if (isset($this->cf7_data[$item])) {
                        return $this->cf7_data[$item];
                    }
                }
            }, $cf7_options['message']);

            $this->sms->SendSMS();
        }

        /**
         * Send SMS to a specific field
         */
        if ($cf7_options_field['message'] && $cf7_options_field['phone']) {
            $to = preg_replace_callback('/%([a-zA-Z0-9._-]+)%/', function ($matches) {
                foreach ($matches as $item) {
                    if (isset($this->cf7_data[$item])) {
                        return $this->cf7_data[$item];
                    }
                }
            }, $cf7_options_field['phone']);

            // Check if the type of the field is select.
            foreach ($form->scan_form_tags() as $scan_form_tag) {
                if ($scan_form_tag['basetype'] == 'select') {
                    foreach ($scan_form_tag['raw_values'] as $raw_value) {
                        $option = explode('|', $raw_value);

                        if (isset($option[0]) and $option[0] == $to) {
                            $to = $option[1];
                        }
                    }
                }
            }

            if (strpos($to, ',') !== false) {
                $to = explode(',', $to);
            } else if (strpos($to, '|') !== false) {
                $to = explode('|', $to);
            }

            $this->sms->to = is_array($to) ? $to : array($to);

            $this->sms->msg = preg_replace_callback('/%([a-zA-Z0-9._-]+)%/', function ($matches) {
                foreach ($matches as $item) {
                    if (isset($this->cf7_data[$item])) {
                        return $this->cf7_data[$item];
                    }
                }
            }, $cf7_options_field['message']);
            $this->sms->SendSMS();
        }
    }

    private function set_cf7_data()
    {
        foreach ($_POST as $index => $key) {
            if (is_array($key)) {
                $this->cf7_data[$index] = implode(', ', $key);
            } else {
                $this->cf7_data[$index] = $key;
            }
        }
    }

}

new Integrations();