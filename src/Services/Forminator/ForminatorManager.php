<?php

namespace WP_SMS\Services\Forminator;

use Forminator_API;
use WP_SMS\Notification\NotificationFactory;

if (!defined('ABSPATH')) exit;

class ForminatorManager
{
    public function init()
    {
        add_filter('wp_sms_registered_integration_tabs', function ($tabs) {
            $tabs['forminator'] = __('Forminator', 'wp-sms');
            return $tabs;
        });

        add_filter('wp_sms_forminator_settings', array($this, 'setting_fields'));

        $forminator = new Forminator();
        $forminator->init();
    }

    public function setting_fields($options)
    {
        $forminator_forms = array();

        if (class_exists('Forminator')) {
            $forms = $this->getPublishedForms();

            if (empty($forms)) {
                $forminator_forms['forminator_notify_form'] = array(
                    'id'   => 'forminator_notify_form',
                    'name' => esc_html__('No data', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => esc_html__('There is no form available on Forminator plugin, please first add your forms.', 'wp-sms')
                );
            }

            foreach ($forms as $form) {
                $formFields                                                       = Forminator::formFields($form->id);
                $forminator_forms['forminator_notify_form_' . $form->id]          = array(
                    'id'   => 'forminator_notify_form_' . $form->id,
                    // translators: 1: Form name, 2: Form ID
                    'name' => sprintf(__('Form notifications (%1$s, ID %2$s)', 'wp-sms'), $form->name, $form->id),
                    'type' => 'header',
                    // The ID is shown because settings are saved per form. Sites often have
                    // several forms with near identical names, and the ID is the only way to be
                    // sure this is the form that is actually on the page.
                    // translators: 1: Form name, 2: Form ID
                    'desc' => sprintf(__('By enabling this option you can send SMS notification once the %1$s form is submitted. This form has the ID %2$s, which you can check against the form on your page.', 'wp-sms'), $form->name, $form->id),
                    'doc'  => '',
                );
                $forminator_forms['forminator_notify_enable_form_' . $form->id]   = array(
                    'id'      => 'forminator_notify_enable_form_' . $form->id,
                    'name'    => __('Send SMS to a number', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                );
                $forminator_forms['forminator_notify_receiver_form_' . $form->id] = array(
                    'id'   => 'forminator_notify_receiver_form_' . $form->id,
                    'name' => __('Phone number(s)', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms')
                );
                $forminator_forms['forminator_notify_message_form_' . $form->id]  = array(
                    'id'   => 'forminator_notify_message_form_' . $form->id,
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter your message content.', 'wp-sms') . '<br>' .
                        $this->printVariables(
                            NotificationFactory::getForminator($form->id)->getVariables()
                        )
                );

                if ($formFields) {
                    $forminator_forms['forminator_notify_enable_field_form_' . $form->id]   = array(
                        'id'      => 'forminator_notify_enable_field_form_' . $form->id,
                        'name'    => __('Send SMS to field', 'wp-sms'),
                        'type'    => 'checkbox',
                        'options' => $options,
                    );
                    $forminator_forms['forminator_notify_receiver_field_form_' . $form->id] = array(
                        'id'      => 'forminator_notify_receiver_field_form_' . $form->id,
                        'name'    => __('A field of the form', 'wp-sms'),
                        'type'    => 'select',
                        'options' => $formFields,
                        'desc'    => __('Select the field of your form.', 'wp-sms')
                    );
                    $forminator_forms['forminator_notify_message_field_form_' . $form->id]  = array(
                        'id'   => 'forminator_notify_message_field_form_' . $form->id,
                        'name' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'desc' => __('Enter your message content.', 'wp-sms') . '<br>' .
                            $this->printVariables(
                                NotificationFactory::getForminator($form->id)->getVariables()
                            )
                    );
                }
            }
        } else {
            $forminator_forms['forminator_notify_form'] = array(
                'id'   => 'forminator_notify_form',
                'name' => __('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => __('Forminator plugin should be enable to run this tab', 'wp-sms')
            );
        }
        return $forminator_forms;
    }

    /**
     * Every published Forminator form, not just the first page of them.
     *
     * Forminator returns forms one page at a time. Asking for a single page meant sites
     * with more forms than the page size simply never saw the rest on this screen, so
     * those forms could not be given SMS settings at all and the omission looked like
     * the integration ignoring them.
     *
     * @return array
     */
    private function getPublishedForms()
    {
        $perPage  = 50;
        $page     = 1;
        $maxPages = 100;
        $forms    = [];

        do {
            $batch = Forminator_API::get_forms(null, $page, $perPage, 'publish');

            if (is_wp_error($batch) || empty($batch) || !is_array($batch)) {
                break;
            }

            $forms = array_merge($forms, $batch);
            $page++;
        } while (count($batch) === $perPage && $page <= $maxPages);

        return $forms;
    }

    private function printVariables($variables)
    {
        $result = "";
        foreach ($variables as $key => $value) {
            preg_match("/(%field-|%)(.+)*\%/", $key, $match);
            $label  = $match[1] ? $match[2] : "";
            $result .= esc_html($label) . ": <code>" . esc_html($key) . "</code> ";
        }
        return $result;
    }
}
