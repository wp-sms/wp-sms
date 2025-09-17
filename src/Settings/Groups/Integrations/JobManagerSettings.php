<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;

class JobManagerSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'job_manager';
    }

    public function getLabel(): string
    {
        return __('Job Manager', 'wp-sms');
    }

    public function getSections(): array
    {
        $isPluginActive = class_exists('WP_Job_Manager');
        
        $sections = [];
        
        // Always show plugin status notice first when plugin is inactive
        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'job_manager_not_active',
                'title' => __('Job Manager not active', 'wp-sms'),
                'subtitle' => __('Job Manager Integration Status', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'job_manager_not_active_notice',
                        'label' => __('Plugin Status', 'wp-sms'),
                        'type' => 'notice',
                        'description' => __('Install and activate the WP Job Manager plugin to access these settings.', 'wp-sms')
                    ])
                ]
            ]);
        }

        $sections = array_merge($sections, [
            new Section([
                'id' => 'mobile_field_configuration',
                'title' => __('Job form & display', 'wp-sms'),
                'subtitle' => __('Add a phone number field to the Post a Job form and optionally show it on the job page.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'job_mobile_field',
                        'label' => __('Add phone number field', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Add a phone number field to the Post a Job form.', 'wp-sms'),
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'job_display_mobile_number',
                        'label' => __('Show phone number on job page', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Display the submitted phone number on the single job page.', 'wp-sms'),
                        'readonly' => !$isPluginActive
                    ]),
                ]
            ]),
            new Section([
                'id' => 'new_job_notification',
                'title' => __('New job alerts', 'wp-sms'),
                'subtitle' => __('Send an SMS when a new job is submitted.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'job_notify_status',
                        'label' => __('Send SMS on new submission', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send an SMS when a job is submitted.', 'wp-sms'),
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'job_notify_receiver',
                        'label' => __('Recipients', 'wp-sms'),
                        'type' => 'select',
                        'options' => [
                            'subscriber' => __('Subscriber(s)', 'wp-sms'),
                            'number' => __('Custom number(s)', 'wp-sms')
                        ],
                        'description' => __('Choose who should receive the SMS.', 'wp-sms'),
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'job_notify_receiver_subscribers',
                        'label' => __('Subscriber group', 'wp-sms'),
                        'type' => 'select',
                        'options' => $this->getSubscribeGroups(),
                        'description' => __('Select the subscriber group that should receive these alerts.', 'wp-sms'),
                        'show_if' => ['job_notify_receiver' => 'subscriber'],
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'job_notify_receiver_numbers',
                        'label' => __('Custom number(s)', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter one or more phone numbers separated by commas. Example: +49 170 1234567, +1 415 555 0101.', 'wp-sms'),
                        'show_if' => ['job_notify_receiver' => 'number'],
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'job_notify_message',
                        'label' => __('Message text', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Write the SMS content. Available tags: %job_id%, %job_title%, %job_description%, %job_location%, %job_type%, %job_mobile%, %company_name%, %website%.', 'wp-sms'),
                        'readonly' => !$isPluginActive
                    ]),
                ]
            ]),
            new Section([
                'id' => 'employer_notification',
                'title' => __('Employer alert', 'wp-sms'),
                'subtitle' => __('Send an SMS to the employer when their job is approved.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'job_notify_employer_status',
                        'label' => __('Send SMS on approval', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send an SMS to the employer when the job is approved.', 'wp-sms'),
                        'readonly' => !$isPluginActive
                    ]),
                    new Field([
                        'key' => 'job_notify_employer_message',
                        'label' => __('Message text', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Write the SMS content. Available tags: %job_id%, %job_title%, %job_description%, %job_location%, %job_type%, %job_mobile%, %company_name%, %website%.', 'wp-sms'),
                        'readonly' => !$isPluginActive
                    ]),
                ]
            ]),
        ]);
        
        return $sections;
    }

    private function getSubscribeGroups(): array
    {
        global $wpdb;
        
        $groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sms_subscribes_group");
        $options = [];
        
        if ($groups) {
            foreach ($groups as $group) {
                $options[$group->ID] = $group->name;
            }
        }
        
        return $options;
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