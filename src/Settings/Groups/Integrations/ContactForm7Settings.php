<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;

class ContactForm7Settings extends AbstractSettingGroup {
    public function getName(): string {
        return 'contact_form7';
    }

    public function getLabel(): string {
        return 'Contact Form 7 Settings';
    }

    public function getFields(): array {
        return [
            new Field([
                'key'         => 'cf7_title',
                'type'        => 'header',
                'label'       => 'SMS Notification Metabox',
                'description' => 'Add SMS notification tools to all Contact Form 7 edit forms.',
                'doc'  => '/resources/integrate-wp-sms-with-contact-form-7/',
                'group_label' => 'Contact Form 7',
            ]),
            new Field([
                'key'         => 'cf7_metabox',
                'type'        => 'checkbox',
                'label'       => 'Status',
                'description' => 'Enables SMS Notification tab in Contact Form 7 form editor.',
                'group_label' => 'Contact Form 7',
            ]),
        ];
    }
}
