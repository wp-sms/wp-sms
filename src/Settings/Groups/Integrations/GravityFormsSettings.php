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
        $isPluginActive = class_exists('RGFormsModel');
        
        $sections = [];
        
        // Always show plugin status notice first when plugin is inactive
        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'gravity_forms_not_active',
                'title' => __('Plugin Status', 'wp-sms'),
                'subtitle' => __('Gravity Forms Integration Status', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'gravity_forms_not_active_notice',
                        'label' => __('Plugin Status', 'wp-sms'),
                        'type' => 'notice',
                        'description' => __('Gravity Forms plugin is not installed or activated.', 'wp-sms')
                    ])
                ]
            ]);
        }

        // Get forms if plugin is active, otherwise use empty array
        $forms = $isPluginActive ? \RGFormsModel::get_forms(null, 'title') : [];
        
        // If no forms available, show notice but still show the fields structure
        if ($isPluginActive && empty($forms)) {
            $sections[] = new Section([
                'id' => 'no_forms_available',
                'title' => __('No forms found', 'wp-sms'),
                'subtitle' => __('Create a form in Gravity Forms, then return here to set up SMS notifications.', 'wp-sms'),
                'fields' => []
            ]);
        }
        
        // If plugin is active, show forms; if inactive, show a sample form structure
        if ($isPluginActive && !empty($forms)) {
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
                    'subtitle' => __('Send an SMS when this form is submitted.', 'wp-sms'),
                    'help_url' => WP_SMS_SITE . '/resources/integrate-wp-sms-pro-with-gravity-forms/',
                    'fields' => [
                        new Field([
                            'key' => 'gf_notify_enable_form_' . $form->id,
                            'label' => __('Send to fixed number(s)', 'wp-sms'),
                            'type' => 'checkbox',
                            'readonly' => !$isPluginActive
                        ]),
                        new Field([
                            'key' => 'gf_notify_receiver_form_' . $form->id,
                            'label' => __('Phone number(s)', 'wp-sms'),
                            'type' => 'text',
                            'description' => __('Enter one or more numbers separated by commas. Use the international format where possible. Example: +491701234567, +989121234567', 'wp-sms'),
                            'show_if' => ['gf_notify_enable_form_' . $form->id => true],
                            'readonly' => !$isPluginActive
                        ]),
                        new Field([
                            'key' => 'gf_notify_message_form_' . $form->id,
                            'label' => __('Message body', 'wp-sms'),
                            'type' => 'textarea',
                            'description' => __('Write your message. You can use these tags:', 'wp-sms') . '<br>' .
                                sprintf(
                                    // translators: %1$s: Form title, %2$s: IP address, %3$s: Form url, %4$s: User agent, %5$s: Content form
                                    __('Form name: %1$s, IP: %2$s, Form URL: %3$s, User-Agent: %4$s, Form content: %5$s', 'wp-sms'),
                                    '<code>%title%</code>',
                                    '<code>%ip%</code>',
                                    '<code>%source_url%</code>',
                                    '<code>%user_agent%</code>',
                                    '<code>%content%</code>'
                                ) . $moreFields,
                            'show_if' => ['gf_notify_enable_form_' . $form->id => true],
                            'readonly' => !$isPluginActive
                        ])
                    ]
                ]);

                // Add field-based notifications if form has fields
                if ($formFields) {
                    $fieldBasedFields = [
                        new Field([
                            'key' => 'gf_notify_enable_field_form_' . $form->id,
                            'label' => __('Send to phone field', 'wp-sms'),
                            'type' => 'checkbox',
                            'readonly' => !$isPluginActive
                        ]),
                        new Field([
                            'key' => 'gf_notify_receiver_field_form_' . $form->id,
                            'label' => __('Phone field', 'wp-sms'),
                            'type' => 'select',
                            'options' => $formFields,
                            'description' => __('Choose the field that contains the recipient phone number.', 'wp-sms'),
                            'show_if' => ['gf_notify_enable_field_form_' . $form->id => true],
                            'readonly' => !$isPluginActive
                        ]),
                        new Field([
                            'key' => 'gf_notify_message_field_form_' . $form->id,
                            'label' => __('Message body', 'wp-sms'),
                            'type' => 'textarea',
                            'description' => __('Write your message. You can use these tags:', 'wp-sms') . '<br>' .
                                sprintf(
                                    // translators: %1$s: Form title, %2$s: IP address, %3$s: Form url, %4$s: User agent, %5$s: Content form
                                    __('Form name: %1$s, IP: %2$s, Form URL: %3$s, User-Agent: %4$s, Form content: %5$s', 'wp-sms'),
                                    '<code>%title%</code>',
                                    '<code>%ip%</code>',
                                    '<code>%source_url%</code>',
                                    '<code>%user_agent%</code>',
                                    '<code>%content%</code>'
                                ) . $moreFields,
                            'show_if' => ['gf_notify_enable_field_form_' . $form->id => true],
                            'readonly' => !$isPluginActive
                        ])
                    ];
                    
                    $sections[count($sections) - 1]->setFields(array_merge($sections[count($sections) - 1]->getFields(), $fieldBasedFields));
                }
            }
        } else {
            // Show sample form structure when plugin is inactive or no forms available
            $sections[] = new Section([
                'id' => 'form_notifications_sample',
                'title' => __('Form notifications (Sample Form)', 'wp-sms'),
                'subtitle' => __('Send an SMS when this form is submitted.', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/integrate-wp-sms-pro-with-gravity-forms/',
                'fields' => [
                    new Field([
                        'key' => 'gf_notify_enable_form_sample',
                        'label' => __('Send to fixed number(s)', 'wp-sms'),
                        'type' => 'checkbox',
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'gf_notify_receiver_form_sample',
                        'label' => __('Phone number(s)', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter one or more numbers separated by commas. Use the international format where possible. Example: +491701234567, +989121234567', 'wp-sms'),
                        'show_if' => ['gf_notify_enable_form_sample' => true],
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'gf_notify_message_form_sample',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Write your message. You can use these tags:', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Form title, %2$s: IP address, %3$s: Form url, %4$s: User agent, %5$s: Content form
                                __('Form name: %1$s, IP: %2$s, Form URL: %3$s, User-Agent: %4$s, Form content: %5$s', 'wp-sms'),
                                '<code>%title%</code>',
                                '<code>%ip%</code>',
                                '<code>%source_url%</code>',
                                '<code>%user_agent%</code>',
                                '<code>%content%</code>'
                            ),
                        'show_if' => ['gf_notify_enable_form_sample' => true],
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'gf_notify_enable_field_form_sample',
                        'label' => __('Send to phone field', 'wp-sms'),
                        'type' => 'checkbox',
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'gf_notify_receiver_field_form_sample',
                        'label' => __('Phone field', 'wp-sms'),
                        'type' => 'select',
                        'options' => [],
                        'description' => __('Choose the field that contains the recipient phone number.', 'wp-sms'),
                        'show_if' => ['gf_notify_enable_field_form_sample' => true],
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'gf_notify_message_field_form_sample',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Write your message. You can use these tags:', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Form title, %2$s: IP address, %3$s: Form url, %4$s: User agent, %5$s: Content form
                                __('Form name: %1$s, IP: %2$s, Form URL: %3$s, User-Agent: %4$s, Form content: %5$s', 'wp-sms'),
                                '<code>%title%</code>',
                                '<code>%ip%</code>',
                                '<code>%source_url%</code>',
                                '<code>%user_agent%</code>',
                                '<code>%content%</code>'
                            ),
                        'show_if' => ['gf_notify_enable_field_form_sample' => true],
                        'readonly' => !$isPluginActive
                    ])
                ]
            ]);
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