<?php

namespace WP_SMS\Settings\Groups;

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

    public function getIcon(): string
    {
        return LucideIcons::BRIEFCASE;
    }

    public function getSections(): array
    {
        if (!class_exists('WP_Job_Manager')) {
            return [
                new Section([
                    'id' => 'job_manager_not_active',
                    'title' => __('Not active', 'wp-sms'),
                    'subtitle' => __('Job Manager plugin should be installed to show the options.', 'wp-sms'),
                    'fields' => []
                ])
            ];
        }

        return [
            new Section([
                'id' => 'mobile_field_configuration',
                'title' => __('Mobile field', 'wp-sms'),
                'subtitle' => __('Configure mobile field integration with Job Manager forms', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'job_mobile_field',
                        'label' => __('Mobile field', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Add Mobile field to Post a job form', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'job_display_mobile_number',
                        'label' => __('Display Mobile', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Display Mobile number on the single job page', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'new_job_notification',
                'title' => __('Notify for new job', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for new job submissions', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'job_notify_status',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS when submit new job', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'job_notify_receiver',
                        'label' => __('SMS receiver', 'wp-sms'),
                        'type' => 'select',
                        'options' => [
                            'subscriber' => __('Subscriber(s)', 'wp-sms'),
                            'number' => __('Number(s)', 'wp-sms')
                        ],
                        'description' => __('Please select the SMS receiver(s).', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'job_notify_receiver_subscribers',
                        'label' => __('Subscribe group', 'wp-sms'),
                        'type' => 'select',
                        'options' => $this->getSubscribeGroups(),
                        'description' => __('Please select the group of subscribers that you want to receive the SMS.', 'wp-sms'),
                        'show_if' => ['job_notify_receiver' => 'subscriber']
                    ]),
                    new Field([
                        'key' => 'job_notify_receiver_numbers',
                        'label' => __('Number(s)', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms'),
                        'show_if' => ['job_notify_receiver' => 'number']
                    ]),
                    new Field([
                        'key' => 'job_notify_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Job ID, %2$s: Job Title, %3$s: Job Description, %4$s: Job Location, %5$s: Job Type, %6$s: Company Mobile, %7$s: Company Name, %8$s: Company Website
                                __('Job ID: %1$s, Job Title: %2$s, Job Description: %3$s, Job Location: %4$s, Job Type: %5$s, Company Mobile: %6$s, Company Name: %7$s, Company Website: %8$s', 'wp-sms'),
                                '<code>%job_id%</code>',
                                '<code>%job_title%</code>',
                                '<code>%job_description%</code>',
                                '<code>%job_location%</code>',
                                '<code>%job_type%</code>',
                                '<code>%job_mobile%</code>',
                                '<code>%company_name%</code>',
                                '<code>%website%</code>'
                            )
                    ]),
                ]
            ]),
            new Section([
                'id' => 'employer_notification',
                'title' => __('Notify to Employer', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications sent to employers when jobs are approved', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'job_notify_employer_status',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to employer when the job approved', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'job_notify_employer_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Job ID, %2$s: Job Title, %3$s: Job Description, %4$s: Job Location, %5$s: Job Type, %6$s: Company Mobile, %7$s: Company Name, %8$s: Company Website
                                __('Job ID: %1$s, Job Title: %2$s, Job Description: %3$s, Job Location: %4$s, Job Type: %5$s, Company Mobile: %6$s, Company Name: %7$s, Company Website: %8$s', 'wp-sms'),
                                '<code>%job_id%</code>',
                                '<code>%job_title%</code>',
                                '<code>%job_description%</code>',
                                '<code>%job_location%</code>',
                                '<code>%job_type%</code>',
                                '<code>%job_mobile%</code>',
                                '<code>%company_name%</code>',
                                '<code>%website%</code>'
                            )
                    ]),
                ]
            ]),
        ];
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