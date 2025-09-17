<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;
use WP_SMS\Version;

class AdvancedSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'advanced';
    }

    public function getLabel(): string
    {
        return __('Advanced', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::BADGE_CHECK;
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id' => 'administrative_reporting',
                'title' => __('Monitoring & Reports', 'wp-sms'),
                'subtitle' => __('Set up weekly stats and delivery error alerts', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'report_wpsms_statistics',
                        'label' => __('Weekly SMS Report', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Sends a weekly summary of sent, delivered, and failed messages to the admin email.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notify_errors_to_admin_email',
                        'label' => __('Send Error Alerts', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Emails the admin when an SMS fails to send.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'url_shortening',
                'title' => __('URL Shortening via Bitly', 'wp-sms'),
                'subtitle' => __('Shorten links with Bitly and track clicks', 'wp-sms'),
                'tag' => !$this->proIsInstalled() ? Tags::PRO : null,
                'fields' => [
                    new Field([
                        'key' => 'short_url_status',
                        'label' => __('Enable Link Shortening', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Shortens URLs in messages using Bitly.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled(),
                    ]),
                    new Field([
                        'key' => 'short_url_api_token',
                        'label' => __('Bitly API Token', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Paste your Bitly API token. Get it from your Bitly account settings.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled(),
                        'show_if' => ['short_url_status' => true]
                    ]),
                ]
            ]),
            new Section([
                'id' => 'webhooks_configuration',
                'title' => __('Webhooks', 'wp-sms'),
                'subtitle' => __('Send events to external services. One URL per line.', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/webhooks/',
                'fields' => [
                    new Field([
                        'key' => 'new_sms_webhook',
                        'label' => __('Outgoing SMS Webhook', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Notify this URL after a message is sent. HTTPS required. One URL per line.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'new_subscriber_webhook',
                        'label' => __('New Subscriber Webhook', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Triggered when a user subscribes. HTTPS required. One URL per line.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'new_incoming_sms_webhook',
                        'label' => __('Incoming SMS Webhook', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('For the "<a href="https://wp-sms-pro.com/product/wp-sms-two-way/?utm_source=wp-sms&utm_medium=link&utm_campaign=settings" target="_blank">Two-Way SMS</a>" add-on. Called when an SMS is received. HTTPS required. One URL per line.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'google_recaptcha_integration',
                'title' => __('Google reCAPTCHA Integration', 'wp-sms'),
                'subtitle' => __('Protect request-SMS actions from spam', 'wp-sms'),
                'tag' => (!$this->proIsInstalled() && !$this->wooProIsInstalled()) ? Tags::PRO : null,
                'fields' => [
                    new Field([
                        'key' => 'g_recaptcha_status',
                        'label' => __('Enable reCAPTCHA', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Require reCAPTCHA for request-SMS actions.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled() && !$this->wooProIsInstalled(),
                    ]),
                    new Field([
                        'key' => 'g_recaptcha_site_key',
                        'label' => __('Site Key', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Your public reCAPTCHA site key.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled() && !$this->wooProIsInstalled(),
                        'show_if' => ['g_recaptcha_status' => true]
                    ]),
                    new Field([
                        'key' => 'g_recaptcha_secret_key',
                        'label' => __('Secret Key', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Your private reCAPTCHA secret key. Keep this confidential.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled() && !$this->wooProIsInstalled(),
                        'show_if' => ['g_recaptcha_status' => true]
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