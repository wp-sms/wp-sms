<?php

namespace WP_SMS\Services\Forminator;

use Forminator_API;

class ForminatorManager
{
    public function init()
    {
        add_filter('wp_sms_registered_tabs', function ($tabs) {
            $tabs['forminator'] = __('Forminator', 'wp-sms');
            return $tabs;
        });

        add_filter('wp_sms_forminator_settings', array($this, 'setting_fields'));
    }

    public function setting_fields($options)
    {
        $forminator_forms = array();

        if (class_exists('Forminator')) {
            $forms       = Forminator_API::get_forms(null, 1, 20, "publish");
            $more_fields = '';
            foreach ($forms as $form) {
                $form_fields = Forminator_API::get_form_fields($form->id);
                if (is_array($form_fields) && count($form_fields)) {
                    $more_fields = ', ';
                    foreach ($form_fields as $key => $value) {
                        $more_fields .= "Field {$value->slug}: <code>%field-{$value->slug}%</code>, ";
                    }

                    $more_fields = rtrim($more_fields, ', ');
                }

                $forminator_forms['forminator_notify_form_' . $form->id]          = array(
                    'id'   => 'forminator_notify_form_' . $form->id,
                    'name' => sprintf(__('Form notifications (%s)', 'wp-sms'), $form->name),
                    'type' => 'header',
                    'desc' => sprintf(__('By enabling this option you can send SMS notification once the %s form is submitted', 'wp-sms'), $form->name),
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
                        sprintf(
                            __('site name: %s, site url: %s', 'wp-sms'),
                            '<code>%site_name%</code>',
                            '<code>%site_url%</code>',
                        ) . $more_fields
                );

                if ($form_fields) {
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
                        'options' => Forminator::formFields($form->id),
                        'desc'    => __('Select the field of your form.', 'wp-sms')
                    );
                    $forminator_forms['forminator_notify_message_field_form_' . $form->id]  = array(
                        'id'   => 'forminator_notify_message_field_form_' . $form->id,
                        'name' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'desc' => __('Enter your message content.', 'wp-sms') . '<br>' .
                            sprintf(
                                __('site name: %s, site url: %s', 'wp-sms'),
                                '<code>%site_name%</code>',
                                '<code>%site_url%</code>',
                            ) . $more_fields
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

}