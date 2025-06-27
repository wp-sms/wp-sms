<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;

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

    public function getIcon(): string
    {
        return LucideIcons::MAIL;
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id' => 'sms_notification_metabox',
                'title' => __('SMS Notification Metabox', 'wp-sms'),
                'subtitle' => __('By this option you can add SMS notification tools in all edit forms.', 'wp-sms'),
                'help_url' => '/resources/integrate-wp-sms-with-contact-form-7/',
                'fields' => [
                    new Field([
                        'key' => 'cf7_metabox',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('This option adds SMS Notification tab in the edit forms.', 'wp-sms')
                    ]),
                ]
            ]),
        ];
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