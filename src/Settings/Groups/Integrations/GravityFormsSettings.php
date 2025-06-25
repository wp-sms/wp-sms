<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;

class GravityFormsSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'gravityforms';
    }

    public function getLabel(): string
    {
        return 'Gravity Forms Integration Settings';
    }

    public function isAvailable(): bool
    {
        return class_exists('RGFormsModel');
    }

    public function getFields(): array
    {
        if (! $this->isAvailable()) {
            return [
                new Field([
                    'key'         => 'gf_notify_form',
                    'type'        => 'notice',
                    'label'       => 'Not active',
                    'description' => 'Gravity Forms plugin should be enabled to run this tab',
                    'group_label' => 'Gravity Forms',
                ])
            ];
        }

        $forms = \RGFormsModel::get_forms(null, 'title');

        if (empty($forms)) {
            return [
                new Field([
                    'key'         => 'gf_notify_form',
                    'type'        => 'notice',
                    'label'       => 'No data',
                    'description' => 'There is no form available on Gravity Forms plugin, please first add your forms.',
                    'group_label' => 'Gravity Forms',
                ])
            ];
        }

        $fields = [];

        foreach ($forms as $form) {
            $form_fields = \WP_SMS\Gravityforms::get_field($form->id);

            $fieldTokens = '';
            if (!empty($form_fields)) {
                foreach ($form_fields as $key => $value) {
                    $fieldTokens .= "Field {$value}: <code>%field-{$key}%</code>, ";
                }
                $fieldTokens = rtrim($fieldTokens, ', ');
            }

            $fields[] = new Field([
                'key'         => "gf_notify_form_{$form->id}",
                'type'        => 'header',
                'label'       => "Form notifications ({$form->title})",
                'description' => "Send SMS on form submission for \"{$form->title}\".",
                'group_label' => 'Gravity Forms',
            ]);

            $fields[] = new Field([
                'key'         => "gf_notify_enable_form_{$form->id}",
                'type'        => 'checkbox',
                'label'       => 'Send SMS to a number',
                'group_label' => 'Gravity Forms',
            ]);

            $fields[] = new Field([
                'key'         => "gf_notify_receiver_form_{$form->id}",
                'type'        => 'text',
                'label'       => 'Phone number(s)',
                'description' => 'Comma-separated numbers to receive SMS',
                'group_label' => 'Gravity Forms',
            ]);

            $fields[] = new Field([
                'key'         => "gf_notify_message_form_{$form->id}",
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Includes <code>%title%</code>, <code>%ip%</code>, <code>%source_url%</code>, <code>%user_agent%</code>, <code>%content%</code>' . (!empty($fieldTokens) ? ', ' . $fieldTokens : ''),
                'group_label' => 'Gravity Forms',
            ]);

            if (!empty($form_fields)) {
                $fields[] = new Field([
                    'key'         => "gf_notify_enable_field_form_{$form->id}",
                    'type'        => 'checkbox',
                    'label'       => 'Send SMS to field',
                    'group_label' => 'Gravity Forms',
                ]);

                $fields[] = new Field([
                    'key'         => "gf_notify_receiver_field_form_{$form->id}",
                    'type'        => 'select',
                    'label'       => 'A field of the form',
                    'options'     => $form_fields,
                    'description' => 'Choose which field contains the recipient mobile number',
                    'group_label' => 'Gravity Forms',
                ]);

                $fields[] = new Field([
                    'key'         => "gf_notify_message_field_form_{$form->id}",
                    'type'        => 'textarea',
                    'label'       => 'Message body (field SMS)',
                    'description' => 'Same variables as above apply' . (!empty($fieldTokens) ? ', ' . $fieldTokens : ''),
                    'group_label' => 'Gravity Forms',
                ]);
            }
        }

        return $fields;
    }
}
