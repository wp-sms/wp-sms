<?php

namespace WP_SMS;

use WPCF7_MailTag;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Integrations
{
    public $options;
    public $cf7_data;

    public function __construct()
    {
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
                'title'    => esc_html__('SMS Notifications', 'wp-sms'),
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

        $get_group_result = Newsletter::getGroups();

        $args = [
            'get_group_result'  => $get_group_result,
            'cf7_options'       => $cf7_options,
            'cf7_options_field' => $cf7_options_field,
        ];

        echo Helper::loadTemplate('wpcf7-form.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

        $cf7_tags  = ['_post_id', '_post_title', '_post_url', '_post_name', '_site_url', '_site_title'];
        $form_tags = $this->cf7_data;

        $replace_tags = function ($text) use ($cf7_tags, $form_tags) {
            return preg_replace_callback('/%([a-zA-Z0-9._-]+)%/', function ($matches) use ($cf7_tags, $form_tags) {
                $tag = $matches[1];

                if (in_array($tag, $cf7_tags)) {
                    return apply_filters(
                        'wpcf7_special_mail_tags',
                        null,
                        $tag,
                        '',
                        new WPCF7_MailTag($tag, 'text', $tag)
                    );
                } elseif (array_key_exists($tag, $form_tags)) {
                    return $form_tags[$tag];
                } else {
                    return $matches[0];
                }
            }, $text);
        };

        /**
         * Send SMS to the specific number or subscribers' group
         */
        if ((isset($cf7_options['phone']) || isset($cf7_options['recipient'])) && isset($cf7_options['message'])) {

            switch ($cf7_options['recipient']) {
                case 'subscriber':
                    $to = Newsletter::getSubscribers($cf7_options['groups'], true);
                    break;

                default:
                    $to = explode(',', $cf7_options['phone']);
                    break;
            }

            $message = $replace_tags($cf7_options['message']);

            if ($to && count($to) && $message) {
                foreach ($to as $number) {
                    wp_sms_send($number, $message);
                }
            }
        }

        /**
         * Send SMS to a specific field
         */
        if (!empty($cf7_options_field['message']) && !empty($cf7_options_field['phone'])) {
            $to = $replace_tags($cf7_options_field['phone']);

            // Check if the type of the field is select.
            foreach ($form->scan_form_tags() as $scan_form_tag) {
                if ($scan_form_tag['basetype'] === 'select') {
                    foreach ($scan_form_tag['raw_values'] as $raw_value) {
                        $option = explode('|', $raw_value);
                        if (isset($option[0], $option[1]) && $option[0] === $to) {
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

            $to      = is_array($to) ? $to : [$to];
            $message = $replace_tags($cf7_options_field['message']);

            if ($to && count($to) && $message) {
                foreach ($to as $number) {
                    wp_sms_send($number, $message);
                }
            }
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