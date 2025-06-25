<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Notification\NotificationFactory;

class QuformSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'quform';
    }

    public function getLabel(): string
    {
        return 'Quform Integration Settings';
    }

    public function isAvailable(): bool
    {
        return class_exists('Quform_Repository');
    }

    public function getFields(): array
    {
        if (! $this->isAvailable()) {
            return [
                new Field([
                    'key'         => 'qf_notify_form',
                    'type'        => 'notice',
                    'label'       => 'Not active',
                    'description' => 'Quform plugin should be enabled to run this tab',
                    'group_label' => 'Quform',
                ])
            ];
        }

        $fields = [];
        $repo   = new \Quform_Repository();
        $forms  = $repo->allForms();

        if (empty($forms)) {
            return [
                new Field([
                    'key'         => 'qf_notify_form',
                    'type'        => 'notice',
                    'label'       => 'No data',
                    'description' => 'There is no form available on Quform plugin, please first add your forms.',
                    'group_label' => 'Quform',
                ])
            ];
        }

        foreach ($forms as $form) {
            $form_id    = $form['id'];
            $form_name  = $form['name'];
            $form_fields = \WP_SMS\Quform::get_fields($form_id);

            $field_tokens = '';
            foreach ($form_fields as $key => $value) {
                $field_tokens .= "Field {$value}: <code>%field-{$key}%</code>, ";
            }
            $field_tokens = rtrim($field_tokens, ', ');

            $fields[] = new Field([
                'key'         => "qf_notify_form_{$form_id}",
                'type'        => 'header',
                'label'       => "Form notifications: ({$form_name})",
                'description' => "Send SMS on form submission for \"{$form_name}\".",
                'group_label' => 'Quform',
            ]);
            $fields[] = new Field([
                'key'         => "qf_notify_enable_form_{$form_id}",
                'type'        => 'checkbox',
                'label'       => 'Send SMS to a number',
                'group_label' => 'Quform',
            ]);
            $fields[] = new Field([
                'key'         => "qf_notify_receiver_form_{$form_id}",
                'type'        => 'text',
                'label'       => 'Phone number(s)',
                'description' => 'Comma-separated list of numbers to receive SMS',
                'group_label' => 'Quform',
            ]);
            $fields[] = new Field([
                'key'         => "qf_notify_message_form_{$form_id}",
                'type'        => 'textarea',
                'label'       => 'Message body (static)',
                'description' => 'Message content with variables: <code>%post_title%</code>, <code>%form_url%</code>, <code>%referring_url%</code>, <code>%content%</code>' . ($field_tokens ? ", {$field_tokens}" : ''),
                'group_label' => 'Quform',
            ]);

            if (!empty($form['elements'])) {
                $fields[] = new Field([
                    'key'         => "qf_notify_enable_field_form_{$form_id}",
                    'type'        => 'checkbox',
                    'label'       => 'Send SMS to field',
                    'group_label' => 'Quform',
                ]);
                $fields[] = new Field([
                    'key'         => "qf_notify_receiver_field_form_{$form_id}",
                    'type'        => 'select',
                    'label'       => 'A field of the form',
                    'options'     => $form_fields,
                    'description' => 'Select the form field that holds the phone number',
                    'group_label' => 'Quform',
                ]);
                $fields[] = new Field([
                    'key'         => "qf_notify_message_field_form_{$form_id}",
                    'type'        => 'textarea',
                    'label'       => 'Message body (dynamic field)',
                    'description' => 'Message content when sending to a field value. Supports same variables as static plus <code>%field-{x}%</code>' . ($field_tokens ? ", {$field_tokens}" : ''),
                    'group_label' => 'Quform',
                ]);
            }
        }

        return $fields;
    }
}
