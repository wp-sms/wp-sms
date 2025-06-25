<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;

class JobManagerSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'job_manager';
    }

    public function getLabel(): string
    {
        return 'WP Job Manager Integration Settings';
    }

    public function isAvailable(): bool
    {
        return class_exists('WP_Job_Manager');
    }

    public function getFields(): array
    {
        if (! $this->isAvailable()) {
            return [
                new Field([
                    'key'         => 'job_fields',
                    'type'        => 'notice',
                    'label'       => 'Not active',
                    'description' => 'Job Manager plugin should be installed to show the options.',
                    'group_label' => 'Job Manager',
                ])
            ];
        }

        return [
            new Field([
                'key'         => 'job_fields',
                'type'        => 'header',
                'label'       => 'Mobile field',
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_mobile_field',
                'type'        => 'checkbox',
                'label'       => 'Mobile field',
                'description' => 'Add mobile number field to "Post a Job" form',
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_display_mobile_number',
                'type'        => 'checkbox',
                'label'       => 'Display Mobile',
                'description' => 'Show mobile number on the single job listing page',
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_notify',
                'type'        => 'header',
                'label'       => 'Notify for new job',
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_notify_status',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Send SMS when a new job is submitted',
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_notify_receiver',
                'type'        => 'select',
                'label'       => 'SMS receiver',
                'description' => 'Choose subscriber group or manual number(s)',
                'options'     => [
                    'subscriber' => 'Subscriber(s)',
                    'number'     => 'Number(s)'
                ],
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_notify_receiver_subscribers',
                'type'        => 'select',
                'label'       => 'Subscribe group',
                'description' => 'Group of subscribers to receive job alerts',
                'show_if'     => ['job_notify_receiver' => 'subscriber'],
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_notify_receiver_numbers',
                'type'        => 'text',
                'label'       => 'Number(s)',
                'description' => 'Enter one or more mobile numbers (comma-separated)',
                'show_if'     => ['job_notify_receiver' => 'number'],
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_notify_message',
                'type'        => 'textarea',
                'label'       => 'Message body (Admin)',
                'description' => 'Message content for admin notification. Placeholders: <code>%job_id%</code>, <code>%job_title%</code>, <code>%company_name%</code>, etc.',
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_notify_employer',
                'type'        => 'header',
                'label'       => 'Notify to Employer',
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_notify_employer_status',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Send SMS to employer when job is approved',
                'group_label' => 'Job Manager',
            ]),
            new Field([
                'key'         => 'job_notify_employer_message',
                'type'        => 'textarea',
                'label'       => 'Message body (Employer)',
                'description' => 'Message content for employer. Placeholders: <code>%job_id%</code>, <code>%job_title%</code>, <code>%company_name%</code>, <code>%website%</code>, etc.',
                'group_label' => 'Job Manager',
            ]),
        ];
    }
}
