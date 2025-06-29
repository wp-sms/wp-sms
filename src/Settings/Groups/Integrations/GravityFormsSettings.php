<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Gravityforms;

class GravityFormsSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'gravity_forms';
    }

    public function getLabel(): string
    {
        return __('Gravity Forms', 'wp-sms');
    }

    public function getSections(): array
    {
        if (!class_exists('RGFormsModel')) {
            return [
                new Section([
                    'id' => 'gravity_forms_not_active',
                    'title' => __('Gravity Forms Integration', 'wp-sms'),
                    'subtitle' => __('Configure SMS notifications for Gravity Forms submissions', 'wp-sms'),
                    'fields' => [
                        new Field([
                            'key' => 'gravity_forms_not_active_notice',
                            'label' => __('Not active', 'wp-sms'),
                            'type' => 'notice',
                            'description' => __('Gravity Forms plugin should be installed to show the options.', 'wp-sms')
                        ])
                    ]
                ])
            ];
        }

        $forms = \RGFormsModel::get_forms(null, 'title');
        
        if (empty($forms)) {
            return [
                new Section([
                    'id' => 'no_forms_available',
                    'title' => __('No data', 'wp-sms'),
                    'subtitle' => __('There is no form available on Gravity Forms plugin, please first add your forms.', 'wp-sms'),
                    'fields' => []
                ])
            ];
        }

        $sections = [];
        
        foreach ($forms as $form) {
            $formFields = Gravityforms::get_field($form->id);
            $moreFields = '';
            
            if (is_array($formFields) && count($formFields)) {
                $moreFields = ', ';
                foreach ($formFields as $key => $value) {
                    $moreFields .= "Field {$value}: <code>%field-{$key}%</code>, ";
                }
                $moreFields = rtrim($moreFields, ', ');
            }

            $sections[] = new Section([
                'id' => 'form_notifications_' . $form->id,
                'title' => sprintf(__('Form notifications (%s)', 'wp-sms'), $form->title),
                'subtitle' => sprintf(__('By enabling this option you can send SMS notification once the %s form is submitted', 'wp-sms'), $form->title),
                'help_url' => WP_SMS_SITE . '/resources/integrate-wp-sms-pro-with-gravity-forms/',
                'fields' => [
                    new Field([
                        'key' => 'gf_notify_enable_form_' . $form->id,
                        'label' => __('Send SMS to a number', 'wp-sms'),
                        'type' => 'checkbox'
                    ]),
                    new Field([
                        'key' => 'gf_notify_receiver_form_' . $form->id,
                        'label' => __('Phone number(s)', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms'),
                        'show_if' => ['gf_notify_enable_form_' . $form->id => true]
                    ]),
                    new Field([
                        'key' => 'gf_notify_message_form_' . $form->id,
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter your message content.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Form title, %2$s: IP address, %3$s: Form url, %4$s: User agent, %5$s: Content form
                                __('Form name: %1$s, IP: %2$s, Form url: %3$s, User agent: %4$s, Content form: %5$s', 'wp-sms'),
                                '<code>%title%</code>',
                                '<code>%ip%</code>',
                                '<code>%source_url%</code>',
                                '<code>%user_agent%</code>',
                                '<code>%content%</code>'
                            ) . $moreFields,
                        'show_if' => ['gf_notify_enable_form_' . $form->id => true]
                    ])
                ]
            ]);

            // Add field-based notifications if form has fields
            if ($formFields) {
                $fieldBasedFields = [
                    new Field([
                        'key' => 'gf_notify_enable_field_form_' . $form->id,
                        'label' => __('Send SMS to field', 'wp-sms'),
                        'type' => 'checkbox'
                    ]),
                    new Field([
                        'key' => 'gf_notify_receiver_field_form_' . $form->id,
                        'label' => __('A field of the form', 'wp-sms'),
                        'type' => 'select',
                        'options' => $formFields,
                        'description' => __('Select the field of your form.', 'wp-sms'),
                        'show_if' => ['gf_notify_enable_field_form_' . $form->id => true]
                    ]),
                    new Field([
                        'key' => 'gf_notify_message_field_form_' . $form->id,
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter your message content.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Form title, %2$s: IP address, %3$s: Form url, %4$s: User agent, %5$s: Content form
                                __('Form name: %1$s, IP: %2$s, Form url: %3$s, User agent: %4$s, Content form: %5$s', 'wp-sms'),
                                '<code>%title%</code>',
                                '<code>%ip%</code>',
                                '<code>%source_url%</code>',
                                '<code>%user_agent%</code>',
                                '<code>%content%</code>'
                            ) . $moreFields,
                        'show_if' => ['gf_notify_enable_field_form_' . $form->id => true]
                    ])
                ];
                
                $sections[count($sections) - 1]->setFields(array_merge($sections[count($sections) - 1]->getFields(), $fieldBasedFields));
            }
        }

        return $sections;
    }

    public function getFields(): array
    {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }
        return $allFields;
    }
} 