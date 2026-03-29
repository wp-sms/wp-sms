<?php

namespace WSms\Integration\ContactForm7\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\ContactForm7\ContactForm7Options;

defined('ABSPATH') || exit;

class FormSubmittedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'contactform7.form_submitted';
    }

    public function getName(): string
    {
        return __('Form Submitted', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a Contact Form 7 form is submitted successfully', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('Contact Form 7', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'form_id' => [
                'type'        => 'integer',
                'label'       => __('Form ID', 'wp-sms'),
                'description' => __('The Contact Form 7 form ID', 'wp-sms'),
                'example'     => 123,
            ],
            'form_title' => [
                'type'        => 'string',
                'label'       => __('Form Title', 'wp-sms'),
                'description' => __('The title of the submitted form', 'wp-sms'),
                'example'     => 'Contact Form',
            ],
            'status' => [
                'type'        => 'string',
                'label'       => __('Status', 'wp-sms'),
                'description' => __('Submission status', 'wp-sms'),
                'example'     => 'mail_sent',
            ],
            'fields' => [
                'type'        => 'object',
                'label'       => __('Form Fields', 'wp-sms'),
                'description' => __('All submitted field values', 'wp-sms'),
                'example'     => ['your-name' => 'John Doe', 'your-email' => 'john@example.com', 'your-message' => 'Hello!'],
            ],
            'meta' => [
                'type'        => 'object',
                'label'       => __('Submission Meta', 'wp-sms'),
                'description' => __('Submission metadata', 'wp-sms'),
                'properties'  => [
                    'remote_ip' => ['type' => 'string', 'label' => __('IP Address', 'wp-sms')],
                    'timestamp' => ['type' => 'string', 'label' => __('Timestamp', 'wp-sms')],
                    'url'       => ['type' => 'string', 'label' => __('Page URL', 'wp-sms')],
                ],
                'example'     => ['remote_ip' => '127.0.0.1', 'timestamp' => '2024-01-15 10:30:00', 'url' => 'https://example.com/contact'],
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'form_id' => [
                'type'        => 'integer',
                'label'       => __('Form', 'wp-sms'),
                'description' => __('Only trigger for this form', 'wp-sms'),
                'dynamic'     => true,
            ],
        ];
    }

    public function getFilterOptions(string $fieldKey): array
    {
        if ($fieldKey === 'form_id') {
            return ContactForm7Options::forms();
        }

        return [];
    }

    public function getSamplePayload(): ?array
    {
        $posts = get_posts([
            'post_type'   => 'wpcf7_contact_form',
            'numberposts' => 1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);

        if (empty($posts)) {
            return null;
        }

        $form = \WPCF7_ContactForm::get_instance($posts[0]->ID);
        if (!$form) {
            return null;
        }

        $fields = [];
        $tags = $form->scan_form_tags(['feature' => 'name-attr']);
        foreach ($tags as $tag) {
            if (!empty($tag->name)) {
                $fields[$tag->name] = '';
            }
        }

        return [
            'form_id'    => $form->id(),
            'form_title' => $form->title(),
            'status'     => 'mail_sent',
            'fields'     => $fields,
            'meta'       => [
                'remote_ip' => '127.0.0.1',
                'timestamp' => current_time('Y-m-d H:i:s'),
                'url'       => home_url(),
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wpcf7_mail_sent', function ($contactForm) use ($callback) {
            $submission = \WPCF7_Submission::get_instance();
            if (!$submission) {
                return;
            }

            $postedData = $submission->get_posted_data();

            $fields = array_filter(
                $postedData,
                fn ($key) => strncmp($key, '_wpcf7', 6) !== 0,
                ARRAY_FILTER_USE_KEY
            );

            $callback([
                'form_id'    => $contactForm->id(),
                'form_title' => $contactForm->title(),
                'status'     => 'mail_sent',
                'fields'     => $fields,
                'meta'       => [
                    'remote_ip' => $submission->get_meta('remote_ip') ?: '',
                    'timestamp' => current_time('Y-m-d H:i:s'),
                    'url'       => $submission->get_meta('url') ?: '',
                ],
            ]);
        });
    }
}
