<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Quform;

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
        $isPluginActive = class_exists('Quform_Repository');
        $sections = [];

        // Always show plugin status notice first when plugin is inactive
        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'quform_not_active',
                'title' => __('Quform Integration', 'wp-sms'),
                'subtitle' => __('Set up SMS notifications for Quform submissions.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'quform_not_active_notice',
                        'label' => __('Not active', 'wp-sms'),
                        'type' => 'notice',
                        'description' => __('Install and activate the Quform plugin to see these options.', 'wp-sms')
                    ])
                ]
            ]);
        }

        // Get forms if plugin is active, otherwise use empty array
        $forms = $isPluginActive ? (new \Quform_Repository())->allForms() : [];
        
        // If no forms available, show notice but still show the fields structure
        if ($isPluginActive && empty($forms)) {
            $sections[] = new Section([
                'id' => 'no_forms_available',
                'title' => __('No forms yet', 'wp-sms'),
                'subtitle' => __('No Quform forms were found. Create a form in Quform, then return here to configure SMS notifications.', 'wp-sms'),
                'fields' => []
            ]);
        }

        // If plugin is active, show forms; if inactive, show a sample form structure
        if ($isPluginActive && !empty($forms)) {
            foreach ($forms as $form) {
                $formFields = Quform::get_fields($form['id']);
                $moreQfFields = '';
                
                if (is_array($formFields) && count($formFields)) {
                    $moreQfFields = '<br>Form fields: ';
                    foreach ($formFields as $key => $value) {
                        $moreQfFields .= "Field {$value}: <code>%field-{$key}%</code>, ";
                    }
                    $moreQfFields = rtrim($moreQfFields, ', ');
                }

                $sections[] = new Section([
                    'id' => 'form_notifications_' . $form['id'],
                    'title' => sprintf(__('Notifications for "%s"', 'wp-sms'), $form['name']),
                    'subtitle' => sprintf(__('Send an SMS when the "%s" form is submitted.', 'wp-sms'), $form['name']),
                    'help_url' => WP_SMS_SITE . '/resources/integrate-wp-sms-pro-with-quform/',
                    'fields' => [
                        new Field([
                            'key' => 'qf_notify_enable_form_' . $form['id'],
                            'label' => __('Send to fixed numbers', 'wp-sms'),
                            'type' => 'checkbox',
                            'readonly' => !$isPluginActive
                        ]),
                        new Field([
                            'key' => 'qf_notify_receiver_form_' . $form['id'],
                            'label' => __('Recipient numbers', 'wp-sms'),
                            'type' => 'text',
                            'description' => __('Enter one or more mobile numbers. Separate with a comma. Example: +49 1512345678, +98 9123456789', 'wp-sms'),
                            'show_if' => ['qf_notify_enable_form_' . $form['id'] => true],
                            'readonly' => !$isPluginActive
                        ]),
                        new Field([
                            'key' => 'qf_notify_message_form_' . $form['id'],
                            'label' => __('Message', 'wp-sms'),
                            'type' => 'textarea',
                            'description' => __('Write the SMS content. You can use these tags:', 'wp-sms') . '<br>' .
                                sprintf(
                                    // translators: %1$s: Form name, %2$s: Form URL, %3$s: Referring URL, %4$s: Form content
                                    __('Form name: %1$s, Form URL: %2$s, Referring URL: %3$s, Form content: %4$s', 'wp-sms'),
                                    '<code>%post_title%</code>',
                                    '<code>%form_url%</code>',
                                    '<code>%referring_url%</code>',
                                    '<code>%content%</code>'
                                ) . $moreQfFields . '<br><br>' .
                                __('Example:', 'wp-sms') . '<br>' .
                                __('New submission on %post_title% — %content%', 'wp-sms'),
                            'show_if' => ['qf_notify_enable_form_' . $form['id'] => true],
                            'readonly' => !$isPluginActive
                        ])
                    ]
                ]);

                // Add field-based notifications if form has elements
                if ($form['elements']) {
                    $fieldBasedFields = [
                        new Field([
                            'key' => 'qf_notify_enable_field_form_' . $form['id'],
                            'label' => __('Send to number from a form field', 'wp-sms'),
                            'type' => 'checkbox',
                            'readonly' => !$isPluginActive
                        ]),
                        new Field([
                            'key' => 'qf_notify_receiver_field_form_' . $form['id'],
                            'label' => __('Recipient field', 'wp-sms'),
                            'type' => 'select',
                            'options' => $formFields,
                            'description' => __('Choose the field that contains the phone number.', 'wp-sms'),
                            'show_if' => ['qf_notify_enable_field_form_' . $form['id'] => true],
                            'readonly' => !$isPluginActive
                        ]),
                        new Field([
                            'key' => 'qf_notify_message_field_form_' . $form['id'],
                            'label' => __('Message', 'wp-sms'),
                            'type' => 'textarea',
                            'description' => __('Write the SMS content. You can use these tags:', 'wp-sms') . '<br>' .
                                sprintf(
                                    // translators: %1$s: Form name, %2$s: Form URL, %3$s: Referring URL, %4$s: Form content
                                    __('Form name: %1$s, Form URL: %2$s, Referring URL: %3$s, Form content: %4$s', 'wp-sms'),
                                    '<code>%post_title%</code>',
                                    '<code>%form_url%</code>',
                                    '<code>%referring_url%</code>',
                                    '<code>%content%</code>'
                                ) . $moreQfFields . '<br><br>' .
                                __('Tip: Make sure the selected field stores a valid phone number.', 'wp-sms'),
                            'show_if' => ['qf_notify_enable_field_form_' . $form['id'] => true],
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
                'title' => __('Notifications for "Sample Form"', 'wp-sms'),
                'subtitle' => __('Send an SMS when the "Sample Form" form is submitted.', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/integrate-wp-sms-pro-with-quform/',
                'fields' => [
                    new Field([
                        'key' => 'qf_notify_enable_form_sample',
                        'label' => __('Send to fixed numbers', 'wp-sms'),
                        'type' => 'checkbox',
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'qf_notify_receiver_form_sample',
                        'label' => __('Recipient numbers', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter one or more mobile numbers. Separate with a comma. Example: +49 1512345678, +98 9123456789', 'wp-sms'),
                        'show_if' => ['qf_notify_enable_form_sample' => true],
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'qf_notify_message_form_sample',
                        'label' => __('Message', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Write the SMS content. You can use these tags:', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Form name, %2$s: Form URL, %3$s: Referring URL, %4$s: Form content
                                __('Form name: %1$s, Form URL: %2$s, Referring URL: %3$s, Form content: %4$s', 'wp-sms'),
                                '<code>%post_title%</code>',
                                '<code>%form_url%</code>',
                                '<code>%referring_url%</code>',
                                '<code>%content%</code>'
                            ) . '<br><br>' .
                            __('Example:', 'wp-sms') . '<br>' .
                            __('New submission on %post_title% — %content%', 'wp-sms'),
                        'show_if' => ['qf_notify_enable_form_sample' => true],
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'qf_notify_enable_field_form_sample',
                        'label' => __('Send to number from a form field', 'wp-sms'),
                        'type' => 'checkbox',
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'qf_notify_receiver_field_form_sample',
                        'label' => __('Recipient field', 'wp-sms'),
                        'type' => 'select',
                        'options' => [],
                        'description' => __('Choose the field that contains the phone number.', 'wp-sms'),
                        'show_if' => ['qf_notify_enable_field_form_sample' => true],
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'qf_notify_message_field_form_sample',
                        'label' => __('Message', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Write the SMS content. You can use these tags:', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Form name, %2$s: Form URL, %3$s: Referring URL, %4$s: Form content
                                __('Form name: %1$s, Form URL: %2$s, Referring URL: %3$s, Form content: %4$s', 'wp-sms'),
                                '<code>%post_title%</code>',
                                '<code>%form_url%</code>',
                                '<code>%referring_url%</code>',
                                '<code>%content%</code>'
                            ) . '<br><br>' .
                            __('Tip: Make sure the selected field stores a valid phone number.', 'wp-sms'),
                        'show_if' => ['qf_notify_enable_field_form_sample' => true],
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