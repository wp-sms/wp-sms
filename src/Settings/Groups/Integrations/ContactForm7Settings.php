<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;

class ContactForm7Settings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'contact_form7';
    }

    public function getLabel(): string
    {
        return __('Contact Form 7', 'wp-sms');
    }

    public function getSections(): array
    {
        $isPluginActive = class_exists('WPCF7');
        
        $sections = [];
        
        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'contact_form7_not_active',
                'title' => __('Plugin Status', 'wp-sms'),
                'subtitle' => __('Contact Form 7 Integration Status', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'contact_form7_not_active_notice',
                        'label' => __('Plugin Status', 'wp-sms'),
                        'type' => 'notice',
                        'description' => __('Contact Form 7 plugin is not installed or activated.', 'wp-sms')
                    ])
                ]
            ]);
        }

        $sections[] = new Section([
            'id' => 'sms_notification_panel',
            'title' => __('SMS Tab in Form Editor', 'wp-sms'),
            'subtitle' => __('Add an SMS tab to all Contact Form 7 forms. Configure recipients and messages per form.', 'wp-sms'),
            'help_url' => '/resources/integrate-wp-sms-with-contact-form-7/',
            'fields' => [
                new Field([
                    'key' => 'cf7_metabox',
                    'label' => __('Enable SMS tab', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Adds an SMS tab to the Contact Form 7 form editor for every form. Use it to notify admins or send confirmations to the phone number provided by the user.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ])
            ]
        ]);

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