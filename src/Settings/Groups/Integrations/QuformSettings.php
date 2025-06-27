<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Services\Formidable\Quform;

class QuformSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'quform';
    }

    public function getLabel(): string
    {
        return __('Quform', 'wp-sms');
    }

    public function getSections(): array
    {
        if (!class_exists('Quform_Repository')) {
            return [
                new Section([
                    'id' => 'quform_not_active',
                    'title' => __('Quform Integration', 'wp-sms'),
                    'subtitle' => __('Configure SMS notifications for Quform submissions', 'wp-sms'),
                    'fields' => [
                        new Field([
                            'key' => 'quform_not_active_notice',
                            'label' => __('Not active', 'wp-sms'),
                            'type' => 'notice',
                            'description' => __('Quform plugin should be installed to show the options.', 'wp-sms')
                        ])
                    ]
                ])
            ];
        }

        $quform = new \Quform_Repository();
        $forms = $quform->allForms();
        
        if (!$forms) {
            return [
                new Section([
                    'id' => 'no_forms_available',
                    'title' => __('No data', 'wp-sms'),
                    'subtitle' => __('There is no form available on Quform plugin, please first add your forms.', 'wp-sms'),
                    'fields' => []
                ])
            ];
        }

        $sections = [];
        
        foreach ($forms as $form) {
            $formFields = Quform::get_fields($form['id']);
            $moreQfFields = '';
            
            if (is_array($formFields) && count($formFields)) {
                $moreQfFields = ', ';
                foreach ($formFields as $key => $value) {
                    $moreQfFields .= "Field {$value}: <code>%field-{$key}%</code>, ";
                }
                $moreQfFields = rtrim($moreQfFields, ', ');
            }

            $sections[] = new Section([
                'id' => 'form_notifications_' . $form['id'],
                'title' => sprintf(__('Form notifications: (%s)', 'wp-sms'), $form['name']),
                'subtitle' => sprintf(__('By enabling this option you can send SMS notification once the %s form is submitted', 'wp-sms'), $form['name']),
                'help_url' => '/resources/integrate-wp-sms-pro-with-quform/',
                'fields' => [
                    new Field([
                        'key' => 'qf_notify_enable_form_' . $form['id'],
                        'label' => __('Send SMS to a number', 'wp-sms'),
                        'type' => 'checkbox'
                    ]),
                    new Field([
                        'key' => 'qf_notify_receiver_form_' . $form['id'],
                        'label' => __('Phone number(s)', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms'),
                        'show_if' => ['qf_notify_enable_form_' . $form['id'] => true]
                    ]),
                    new Field([
                        'key' => 'qf_notify_message_form_' . $form['id'],
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter your message content.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Form name, %2$s: Form URL, %3$s: Referring URL, %4$s: Form content
                                __('Form name: %1$s, Form url: %2$s, Referring url: %3$s, Form content: %4$s', 'wp-sms'),
                                '<code>%post_title%</code>',
                                '<code>%form_url%</code>',
                                '<code>%referring_url%</code>',
                                '<code>%content%</code>'
                            ) . $moreQfFields,
                        'show_if' => ['qf_notify_enable_form_' . $form['id'] => true]
                    ])
                ]
            ]);

            // Add field-based notifications if form has elements
            if ($form['elements']) {
                $fieldBasedFields = [
                    new Field([
                        'key' => 'qf_notify_enable_field_form_' . $form['id'],
                        'label' => __('Send SMS to field', 'wp-sms'),
                        'type' => 'checkbox'
                    ]),
                    new Field([
                        'key' => 'qf_notify_receiver_field_form_' . $form['id'],
                        'label' => __('A field of the form', 'wp-sms'),
                        'type' => 'select',
                        'options' => $formFields,
                        'description' => __('Select the field of your form.', 'wp-sms'),
                        'show_if' => ['qf_notify_enable_field_form_' . $form['id'] => true]
                    ]),
                    new Field([
                        'key' => 'qf_notify_message_field_form_' . $form['id'],
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter your message content.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Form name, %2$s: Form URL, %3$s: Referring URL, %4$s: Form content
                                __('Form name: %1$s, Form url: %2$s, Referring url: %3$s, Form content: %4$s', 'wp-sms'),
                                '<code>%post_title%</code>',
                                '<code>%form_url%</code>',
                                '<code>%referring_url%</code>',
                                '<code>%content%</code>'
                            ) . $moreQfFields,
                        'show_if' => ['qf_notify_enable_field_form_' . $form['id'] => true]
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